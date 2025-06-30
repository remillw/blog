<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Site;
use App\Models\AiBatchJob;
use App\Models\BacklinkSuggestion;
use App\Models\UserBacklinkPoints;
use App\Jobs\ProcessAiBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIController extends Controller
{
    private function getOpenAIKey(): string
    {
        $key = env('OPENAI_API_KEY');
        if (!$key) {
            throw new \Exception('Clé API OpenAI non configurée');
        }
        return $key;
    }

    public function generateArticle(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'site_id' => 'nullable|exists:sites,id',
            'language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'word_count' => 'nullable|integer|min:300|max:2000',
        ]);

        try {
            $prompt = $request->input('prompt');
            $siteId = $request->input('site_id');
            $language = $request->input('language', 'fr');
            $wordCount = $request->input('word_count', 700);

            // Clé de cache basée sur le prompt, site, langue et nombre de mots
            $cacheKey = "ai_article_" . md5($prompt . '_' . $siteId . '_' . $language . '_' . $wordCount);
            
            // Vérifier le cache (valide 24h)
            $cachedResult = cache()->get($cacheKey);
            if ($cachedResult) {
                Log::info('Article served from cache', ['cache_key' => $cacheKey]);
                return response()->json($cachedResult);
            }

            // Récupérer les informations du site pour le contexte
            $siteContext = '';
            $availableCategories = [];
            $existingArticles = [];
            
            if ($siteId) {
                $site = Site::with(['articles' => function($query) use ($language) {
                    $query->where('language_code', $language)
                          ->where('status', 'published')
                          ->select('id', 'title', 'excerpt', 'site_id')
                          ->limit(10) // Limiter pour ne pas surcharger le prompt
                          ->latest();
                }])->find($siteId);
                
                if ($site) {
                    $siteContext = "\nContexte du site: {$site->name} - {$site->description}";
                    
                    // **NOUVEAU: Récupérer les catégories globales disponibles**
                    $globalCategories = $site->globalCategories()
                        ->wherePivot('language_code', $language)
                        ->wherePivot('is_active', true)
                        ->orderByPivot('sort_order')
                        ->get();
                        
                    $availableCategories = $globalCategories->map(function ($category) use ($language) {
                        return $category->getTranslatedName($language);
                    })->toArray();
                    
                    // Récupérer les articles existants pour les liens internes
                    $existingArticles = $site->articles->map(function($article) {
                        return [
                            'title' => $article->title,
                            'excerpt' => $article->excerpt
                        ];
                    })->toArray();
                }
            }

            // **NOUVEAU: Récupérer les suggestions de backlinks pour enrichir le contenu**
            $backlinkSuggestions = $this->getBacklinkSuggestionsForPrompt($prompt, $siteId, $language);

            // Construire le prompt optimisé avec le nombre de mots et les backlinks
            $systemPrompt = $this->buildOptimizedSystemPrompt($language, $siteContext, $availableCategories, $existingArticles, $wordCount, $backlinkSuggestions);
            $userPrompt = $this->buildOptimizedUserPrompt($prompt, $language, $wordCount);

            // Appel à l'API OpenAI avec GPT-4o-mini (excellent rapport qualité/prix)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getOpenAIKey(),
                'Content-Type' => 'application/json',
            ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Changé de gpt-3.5-turbo à gpt-4o-mini (meilleur rapport qualité/prix)
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 3000, // Optimisé pour les coûts
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Erreur lors de l\'appel à l\'API OpenAI');
            }

            $aiResponse = $response->json();
            $content = $aiResponse['choices'][0]['message']['content'] ?? '';

            // Parser la réponse JSON de l'IA (les backlinks sont déjà intégrés par l'IA)
            $parsedContent = $this->parseAIResponse($content);

            // Mettre en cache pour 24h
            cache()->put($cacheKey, $parsedContent, now()->addHours(24));
            
            Log::info('Article generated and cached', [
                'cache_key' => $cacheKey,
                'tokens_used' => $aiResponse['usage']['total_tokens'] ?? 0,
                'backlinks_suggested' => count($backlinkSuggestions),
                'backlinks_integrated' => $parsedContent['backlinks_metadata']['total_integrated'] ?? 0
            ]);

            return response()->json($parsedContent);

        } catch (\Exception $e) {
            Log::error('AI Generation Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function translateArticle(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'author_bio' => 'nullable|string',
            'target_language' => 'required|string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'source_language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
        ]);

        try {
            $targetLanguage = $request->input('target_language');
            $sourceLanguage = $request->input('source_language', 'fr');

            if ($targetLanguage === $sourceLanguage) {
                return response()->json([
                    'error' => true,
                    'message' => 'La langue source et la langue cible sont identiques'
                ], 400);
            }

            // Construire le prompt de traduction
            $systemPrompt = $this->buildTranslationSystemPrompt($sourceLanguage, $targetLanguage);
            $userPrompt = $this->buildTranslationUserPrompt($request->all());

            // Appel à l'API OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getOpenAIKey(),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'temperature' => 0.3, // Plus bas pour des traductions plus fidèles
                'max_tokens' => 4000,
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI Translation API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Erreur lors de l\'appel à l\'API OpenAI pour la traduction');
            }

            $aiResponse = $response->json();
            $content = $aiResponse['choices'][0]['message']['content'] ?? '';

            // Parser la réponse JSON de l'IA
            $translatedContent = $this->parseTranslationResponse($content);

            return response()->json($translatedContent);

        } catch (\Exception $e) {
            Log::error('Translation Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un batch de génération d'articles (50% moins cher!)
     */
    public function createBatch(Request $request)
    {
        $request->validate([
            'requests' => 'required|array|min:1|max:50', // Max 50 requêtes par batch
            'requests.*.prompt' => 'required|string|max:1000',
            'requests.*.site_id' => 'nullable|exists:sites,id',
            'requests.*.language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
        ]);

        try {
            $requests = [];
            $estimatedCost = 0;

            foreach ($request->input('requests') as $index => $reqData) {
                $prompt = $reqData['prompt'];
                $siteId = $reqData['site_id'] ?? null;
                $language = $reqData['language'] ?? 'fr';

                // Préparer les données comme pour une génération normale
                $siteContext = '';
                $availableCategories = [];
                $existingArticles = [];
                
                if ($siteId) {
                    $site = Site::with(['articles' => function($query) use ($language) {
                        $query->where('language_code', $language)
                              ->where('status', 'published')
                              ->select('id', 'title', 'excerpt', 'site_id')
                              ->limit(10)
                              ->latest();
                    }])->find($siteId);
                    
                    if ($site) {
                        $siteContext = "\nContexte du site: {$site->name} - {$site->description}";
                        
                        $categoriesQuery = $site->categories();
                        if ($language) {
                            $categoriesQuery->where('categories.language_code', $language);
                        }
                        $availableCategories = $categoriesQuery->pluck('categories.name')->toArray();
                        
                        $existingArticles = $site->articles->map(function($article) {
                            return [
                                'title' => $article->title,
                                'excerpt' => $article->excerpt
                            ];
                        })->toArray();
                    }
                }

                $systemPrompt = $this->buildOptimizedSystemPrompt($language, $siteContext, $availableCategories, $existingArticles);
                $userPrompt = $this->buildOptimizedUserPrompt($prompt, $language);

                $requests[] = [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt
                        ],
                        [
                            'role' => 'user',
                            'content' => $userPrompt
                        ]
                    ],
                    'metadata' => [
                        'prompt' => $prompt,
                        'site_id' => $siteId,
                        'language' => $language,
                        'index' => $index
                    ]
                ];

                // Estimation: ~3000 tokens input + 3000 tokens output = 6000 tokens
                // GPT-4o-mini batch: $0.075 / 1K tokens input + $0.30 / 1K tokens output = $0.00225 par requête
                $estimatedCost += 0.00225; // Nouveau coût avec gpt-4o-mini
            }

            // Créer le batch job
            $batchJob = AiBatchJob::create([
                'requests' => $requests,
                'total_requests' => count($requests),
                'estimated_cost' => $estimatedCost,
                'user_id' => auth()->id(),
            ]);

            // Démarrer le traitement du batch
            ProcessAiBatch::dispatch($batchJob);

            return response()->json([
                'batch_id' => $batchJob->id,
                'total_requests' => count($requests),
                'estimated_cost' => $estimatedCost,
                'estimated_completion' => now()->addHours(6)->format('Y-m-d H:i:s'), // Estimation
                'status' => 'pending',
                'message' => 'Batch créé avec succès. Traitement en cours...'
            ]);

        } catch (\Exception $e) {
            Log::error('Batch creation error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un batch
     */
    public function getBatchStatus(int $batchId)
    {
        try {
            $batchJob = AiBatchJob::findOrFail($batchId);
            
            return response()->json([
                'id' => $batchJob->id,
                'status' => $batchJob->status,
                'progress_percentage' => $batchJob->progress_percentage,
                'total_requests' => $batchJob->total_requests,
                'completed_requests' => $batchJob->completed_requests,
                'estimated_cost' => $batchJob->estimated_cost,
                'actual_cost' => $batchJob->actual_cost,
                'submitted_at' => $batchJob->submitted_at,
                'completed_at' => $batchJob->completed_at,
                'error_message' => $batchJob->error_message,
                'has_results' => $batchJob->isCompleted() && !empty($batchJob->responses),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Batch non trouvé'
            ], 404);
        }
    }

    /**
     * Récupérer les résultats d'un batch
     */
    public function getBatchResults(int $batchId)
    {
        try {
            $batchJob = AiBatchJob::findOrFail($batchId);
            
            if (!$batchJob->isCompleted()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Le batch n\'est pas encore terminé'
                ], 400);
            }

            if (empty($batchJob->responses)) {
                return response()->json([
                    'error' => true,
                    'message' => 'Aucun résultat disponible'
                ], 404);
            }

            $processedResults = [];
            
            foreach ($batchJob->responses as $customId => $response) {
                $requestIndex = intval(str_replace('request-', '', $customId));
                $originalRequest = $batchJob->requests[$requestIndex] ?? null;
                
                if (!$originalRequest) continue;

                $content = $response['body']['choices'][0]['message']['content'] ?? '';
                $parsedContent = $this->parseAIResponse($content);
                
                $processedResults[] = [
                    'index' => $requestIndex,
                    'metadata' => $originalRequest['metadata'],
                    'result' => $parsedContent,
                    'tokens_used' => $response['body']['usage']['total_tokens'] ?? 0,
                ];
            }

            return response()->json([
                'batch_id' => $batchJob->id,
                'status' => $batchJob->status,
                'total_results' => count($processedResults),
                'actual_cost' => $batchJob->actual_cost,
                'results' => $processedResults,
                'completed_at' => $batchJob->completed_at,
            ]);

        } catch (\Exception $e) {
            Log::error('Batch results error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lister tous les batches de l'utilisateur
     */
    public function getUserBatches()
    {
        try {
            $batches = AiBatchJob::where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($batch) {
                    return [
                        'id' => $batch->id,
                        'status' => $batch->status,
                        'total_requests' => $batch->total_requests,
                        'progress_percentage' => $batch->progress_percentage,
                        'estimated_cost' => $batch->estimated_cost,
                        'actual_cost' => $batch->actual_cost,
                        'created_at' => $batch->created_at,
                        'completed_at' => $batch->completed_at,
                    ];
                });

            return response()->json($batches);

        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer des suggestions de backlinks pertinentes basées sur l'analyse sémantique
     */
    private function getBacklinkSuggestionsForPrompt(string $prompt, ?int $siteId, string $language): array
    {
        if (!$siteId) {
            return [];
        }

        try {
            // 1. Analyser le prompt pour extraire les concepts clés
            $promptKeywords = $this->extractKeywordsFromPrompt($prompt);
            
            // 2. Récupérer les suggestions existantes avec score élevé
            $existingSuggestions = BacklinkSuggestion::with(['targetArticle.site'])
                ->whereHas('sourceArticle', function($query) use ($siteId, $language) {
                    $query->where('site_id', $siteId)
                          ->where('language_code', $language);
                })
                ->unused()
                ->highQuality(0.70) // Score >= 70%
                ->orderBy('relevance_score', 'desc')
                ->limit(8)
                ->get();

            // 3. Trouver des articles similaires au prompt via recherche sémantique
            $semanticMatches = $this->findSemanticMatches($prompt, $promptKeywords, $siteId, $language);

            // 4. Combiner et scorer les suggestions
            $allSuggestions = $this->combineAndScoreSuggestions($existingSuggestions, $semanticMatches, $siteId);

            // 5. Appliquer la logique de points utilisateur
            $userPoints = UserBacklinkPoints::getOrCreateForUser(auth()->id());
            $filteredSuggestions = $this->filterSuggestionsByPoints($allSuggestions, $userPoints);

            Log::info('🔗 Generated intelligent backlink suggestions', [
                'prompt_keywords' => $promptKeywords,
                'existing_suggestions' => $existingSuggestions->count(),
                'semantic_matches' => count($semanticMatches),
                'final_suggestions' => count($filteredSuggestions),
                'user_points' => $userPoints->available_points,
            ]);

            return $filteredSuggestions;

        } catch (\Exception $e) {
            Log::error('🔗 Failed to get intelligent backlink suggestions', [
                'prompt' => $prompt,
                'site_id' => $siteId,
                'error' => $e->getMessage()
            ]);
            
            // Fallback vers le système simple
            return $this->getFallbackBacklinkSuggestions($siteId, $language);
        }
    }

    /**
     * Extraire les mots-clés et concepts du prompt
     */
    private function extractKeywordsFromPrompt(string $prompt): array
    {
        // Nettoyer et normaliser le prompt
        $text = strtolower(trim($prompt));
        
        // Mots vides à exclure
        $stopWords = [
            'le', 'la', 'les', 'de', 'des', 'du', 'un', 'une', 'et', 'ou', 'à', 'au', 'aux',
            'dans', 'sur', 'pour', 'avec', 'par', 'ce', 'ces', 'cette', 'comment', 'que',
            'qui', 'quoi', 'où', 'quand', 'pourquoi', 'the', 'and', 'or', 'in', 'on', 'at',
            'to', 'for', 'with', 'by', 'from', 'what', 'how', 'when', 'where', 'why'
        ];
        
        // Extraire les mots significatifs (plus de 3 caractères)
        $words = preg_split('/[\s\.,!?;:"()]+/', $text);
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });
        
        // Garder les 5 mots-clés les plus importants
        return array_slice(array_values($keywords), 0, 5);
    }

    /**
     * Trouver des articles qui correspondent sémantiquement au prompt
     */
    private function findSemanticMatches(string $prompt, array $keywords, int $siteId, string $language): array
    {
        $matches = [];
        
        if (empty($keywords)) {
            return $matches;
        }
        
        // Recherche par titre et excerpt avec mots-clés
        $keywordPattern = implode('|', array_map('preg_quote', $keywords));
        
        $articles = Article::where('language_code', $language)
            ->where('status', 'published')
            ->whereNotNull('content_html')
            ->where(function($query) use ($keywordPattern) {
                $query->whereRaw("LOWER(title) REGEXP ?", [$keywordPattern])
                      ->orWhereRaw("LOWER(excerpt) REGEXP ?", [$keywordPattern]);
            })
            ->with(['site', 'categories'])
            ->limit(10)
            ->get();

        foreach ($articles as $article) {
            // Calculer un score de pertinence basique
            $score = $this->calculateBasicRelevanceScore($prompt, $keywords, $article);
            
            if ($score >= 0.4) { // Seuil minimum de pertinence
                $matches[] = [
                    'id' => $article->id,
                    'title' => $article->title,
                    'excerpt' => $article->excerpt,
                    'is_same_site' => $article->site_id === $siteId,
                    'slug' => $article->slug ?? str()->slug($article->title),
                    'relevance_score' => $score,
                    'site_name' => $article->site->name ?? 'Site inconnu',
                    'reasoning' => $this->generateReasoningForMatch($keywords, $article),
                ];
            }
        }
        
        // Trier par score de pertinence
        usort($matches, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);
        
        return array_slice($matches, 0, 6);
    }

    /**
     * Calculer un score de pertinence basique
     */
    private function calculateBasicRelevanceScore(string $prompt, array $keywords, Article $article): float
    {
        $score = 0;
        $maxScore = 0;
        
        $content = strtolower($article->title . ' ' . $article->excerpt);
        
        foreach ($keywords as $keyword) {
            $maxScore += 1;
            
            // Score pour le titre (plus important)
            if (stripos($article->title, $keyword) !== false) {
                $score += 0.7;
            }
            
            // Score pour l'excerpt
            if (stripos($article->excerpt, $keyword) !== false) {
                $score += 0.3;
            }
        }
        
        // Bonus pour la longueur du contenu (plus c'est détaillé, mieux c'est)
        if (strlen($article->excerpt) > 100) {
            $score += 0.1;
        }
        
        // Bonus pour les catégories similaires (si on peut analyser)
        if ($article->categories->isNotEmpty()) {
            $score += 0.1;
        }
        
        return $maxScore > 0 ? min($score / $maxScore, 1.0) : 0;
    }

    /**
     * Générer un raisonnement pour expliquer la correspondance
     */
    private function generateReasoningForMatch(array $keywords, Article $article): string
    {
        $matches = [];
        
        foreach ($keywords as $keyword) {
            if (stripos($article->title, $keyword) !== false) {
                $matches[] = "'{$keyword}' dans le titre";
            } elseif (stripos($article->excerpt, $keyword) !== false) {
                $matches[] = "'{$keyword}' dans l'excerpt";
            }
        }
        
        if (empty($matches)) {
            return "Contenu complémentaire sur un sujet connexe";
        }
        
        return "Pertinence détectée: " . implode(', ', $matches);
    }

    /**
     * Combiner les suggestions existantes et les nouveaux matches
     */
    private function combineAndScoreSuggestions($existingSuggestions, array $semanticMatches, int $siteId): array
    {
        $combined = [];
        
        // Ajouter les suggestions existantes (elles ont déjà un score validé)
        foreach ($existingSuggestions as $suggestion) {
            $combined[] = [
                'id' => $suggestion->target_article_id,
                'title' => $suggestion->targetArticle->title,
                'excerpt' => $suggestion->targetArticle->excerpt,
                'is_same_site' => $suggestion->is_same_site,
                'slug' => $suggestion->targetArticle->slug ?? str()->slug($suggestion->targetArticle->title),
                'relevance_score' => $suggestion->relevance_score,
                'reasoning' => $suggestion->reasoning ?: 'Suggestion validée par IA',
                'anchor_suggestion' => $suggestion->anchor_suggestion,
                'site_name' => $suggestion->targetArticle->site->name ?? 'Site inconnu',
                'source' => 'existing_suggestion'
            ];
        }
        
        // Ajouter les nouveaux matches sémantiques
        foreach ($semanticMatches as $match) {
            // Éviter les doublons
            $exists = collect($combined)->firstWhere('id', $match['id']);
            if (!$exists) {
                $match['source'] = 'semantic_match';
                $match['anchor_suggestion'] = $this->generateNaturalAnchor($match['title']);
                $combined[] = $match;
            }
        }
        
        // Trier par score de pertinence et priorité (même site d'abord)
        usort($combined, function($a, $b) {
            // Priorité 1: Même site
            if ($a['is_same_site'] && !$b['is_same_site']) return -1;
            if (!$a['is_same_site'] && $b['is_same_site']) return 1;
            
            // Priorité 2: Score de pertinence
            return $b['relevance_score'] <=> $a['relevance_score'];
        });
        
        return $combined;
    }

    /**
     * Filtrer les suggestions selon les points disponibles
     */
    private function filterSuggestionsByPoints(array $suggestions, UserBacklinkPoints $userPoints): array
    {
        $filtered = [];
        $internalCount = 0;
        $externalCount = 0;
        $pointsNeeded = 0;
        
        foreach ($suggestions as $suggestion) {
            // Limite de 3 liens internes max
            if ($suggestion['is_same_site'] && $internalCount >= 3) {
                continue;
            }
            
            // Limite de 2 liens externes max + vérification des points
            if (!$suggestion['is_same_site']) {
                if ($externalCount >= 2) {
                    continue;
                }
                if (!$userPoints->canUsePoints($pointsNeeded + 1)) {
                    continue;
                }
                $pointsNeeded++;
                $externalCount++;
            } else {
                $internalCount++;
            }
            
            $filtered[] = $suggestion;
            
            // Limite totale de 5 suggestions
            if (count($filtered) >= 5) {
                break;
            }
        }
        
        return $filtered;
    }

    /**
     * Système de fallback simple en cas d'erreur
     */
    private function getFallbackBacklinkSuggestions(int $siteId, string $language): array
    {
        $articles = Article::where('site_id', $siteId)
            ->where('language_code', $language)
            ->where('status', 'published')
            ->whereNotNull('content_html')
            ->limit(3)
            ->get();

        return $articles->map(function($article) use ($siteId) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'is_same_site' => true,
                'slug' => $article->slug ?? str()->slug($article->title),
                'relevance_score' => 0.5,
                'reasoning' => 'Suggestion de fallback - même site',
                'source' => 'fallback'
            ];
        })->toArray();
    }

    /**
     * Générer une ancre naturelle à partir du titre
     */
    private function generateNaturalAnchor(string $title): string
    {
        // Simplifier le titre pour créer une ancre naturelle
        $anchor = strtolower($title);
        $anchor = preg_replace('/\b(guide|comment|pourquoi|comment faire|tutoriel)\b/i', '', $anchor);
        $anchor = trim($anchor);
        
        if (strlen($anchor) > 50) {
            $words = explode(' ', $anchor);
            $anchor = implode(' ', array_slice($words, 0, 6));
        }

        return ucfirst($anchor);
    }

    /**
     * Insérer un lien de manière naturelle dans le contenu HTML
     */
    private function insertLinkNaturally(string $html, string $anchor, string $url, string $title): string
    {
        // Chercher les mots-clés du titre dans le contenu
        $keywords = explode(' ', strtolower($title));
        $keywords = array_filter($keywords, fn($word) => strlen($word) > 4);

        foreach ($keywords as $keyword) {
            // Chercher le premier paragraphe qui contient ce mot-clé
            if (preg_match('/(<p[^>]*>.*?' . preg_quote($keyword, '/') . '.*?<\/p>)/i', $html, $matches)) {
                $paragraph = $matches[1];
                
                // Vérifier que ce paragraphe n'a pas déjà un lien
                if (strpos($paragraph, '<a ') === false) {
                    $linkHtml = "<a href=\"{$url}\" title=\"{$title}\">{$anchor}</a>";
                    
                    // Insérer le lien après la première phrase du paragraphe
                    $newParagraph = preg_replace(
                        '/(<p[^>]*>.*?\.)\s*/',
                        '$1 Pour en savoir plus, consultez notre ' . $linkHtml . '. ',
                        $paragraph,
                        1
                    );
                    
                    $html = str_replace($paragraph, $newParagraph, $html);
                    break;
                }
            }
        }

        return $html;
    }

    private function buildOptimizedSystemPrompt(string $language, string $siteContext, array $availableCategories, array $existingArticles, int $wordCount, array $backlinkSuggestions): string
    {
        $languageNames = [
            'fr' => 'français',
            'en' => 'anglais',
            'es' => 'espagnol',
            'de' => 'allemand',
            'it' => 'italien',
            'pt' => 'portugais',
            'nl' => 'néerlandais',
            'ru' => 'russe',
            'ja' => 'japonais',
            'zh' => 'chinois',
        ];

        $targetLanguage = $languageNames[$language] ?? 'français';
        $categoriesText = !empty($availableCategories) ? "\nCatégories disponibles: " . implode(', ', $availableCategories) : '';

        // **NOUVEAU: Préparer les suggestions de backlinks pour l'IA**
        $backlinkInstructions = '';
        if (!empty($backlinkSuggestions)) {
            $backlinkInstructions = "\n\n**BACKLINKS INTELLIGENTS À INTÉGRER:**\n";
            
            foreach ($backlinkSuggestions as $suggestion) {
                $type = $suggestion['is_same_site'] ? '🔗 Interne (gratuit)' : '🌐 Externe (1 point)';
                $score = round($suggestion['relevance_score'] * 100);
                
                $backlinkInstructions .= "• [{$type}] \"{$suggestion['title']}\" (Score: {$score}%)\n";
                $backlinkInstructions .= "  URL: /articles/{$suggestion['slug']}\n";
                $backlinkInstructions .= "  Raison: {$suggestion['reasoning']}\n";
                $backlinkInstructions .= "  Ancre suggérée: \"{$suggestion['anchor_suggestion']}\"\n\n";
            }
            
            $backlinkInstructions .= "**RÈGLES D'INTÉGRATION DES BACKLINKS - STRICTES:**\n";
            $backlinkInstructions .= "1. 🔄 DISPERSER obligatoirement dans TOUT l'article : 1 lien en intro + 1-2 dans le corps + max 1 en conclusion\n";
            $backlinkInstructions .= "2. ❌ INTERDICTION de mettre tous les liens en conclusion - OBLIGATOIRE de les répartir\n";
            $backlinkInstructions .= "3. 🎯 Placer chaque lien là où il enrichit VRAIMENT le paragraphe\n";
            $backlinkInstructions .= "4. 💰 Priorité absolue : liens internes (gratuits) puis externes si très pertinents\n";
            $backlinkInstructions .= "5. 🏷️ Ancres naturelles VARIÉES : 'notre guide détaillé sur X', 'découvrez comment Y', 'approfondissez Z'\n";
            $backlinkInstructions .= "6. ⚠️ LIMITES STRICTES : Maximum 3 liens total, maximum 2 externes\n";
            $backlinkInstructions .= "7. 💻 Format HTML EXACT: <a href=\"URL\" title=\"Titre complet\">Ancre naturelle</a>\n";
            $backlinkInstructions .= "8. 📝 OBLIGATION: Chaque lien doit améliorer la phrase où il est placé\n";
            $backlinkInstructions .= "9. 🚫 JAMAIS forcer un lien - si pas naturel, ne pas l'inclure\n";
            $backlinkInstructions .= "10. ✅ VÉRIFICATION finale : liens répartis sur tout l'article\n";
        }

        // Ajouter les articles existants pour créer des liens internes supplémentaires
        $existingArticlesText = '';
        if (!empty($existingArticles)) {
            $existingArticlesText = "\n\n**ARTICLES EXISTANTS (pour liens internes supplémentaires):**\n";
            foreach (array_slice($existingArticles, 0, 5) as $article) {
                $existingArticlesText .= "• \"{$article['title']}\" - " . substr($article['excerpt'], 0, 80) . "...\n";
            }
        }

        return "Tu es un rédacteur web expert spécialisé dans la création d'articles informatifs et optimisés SEO.

**MISSION CRITIQUE:** Créer un article professionnel de EXACTEMENT entre " . ($wordCount - 50) . " et " . ($wordCount + 50) . " mots en {$targetLanguage}.

⚠️ **CONTRAINTE ABSOLUE DE MOTS:** 
- Objectif: {$wordCount} mots (±50 max)
- Compter CHAQUE mot après génération
- Si trop court: ajouter des détails, exemples, données
- Si trop long: condenser sans perdre la qualité
- IMPÉRATIF: Respecter cette limite

**STYLE D'EXPERTISE REQUIS:**
- Données chiffrées et statistiques récentes
- Méthodologies spécifiques et techniques avancées
- Exemples concrets et cas d'usage
- Éviter les généralités - être précis et technique
- Ton d'expert reconnu dans le domaine

**STRUCTURE OBLIGATOIRE:**
1. **Titre H1** : Accrocheur et optimisé SEO
2. **Introduction** : Problématique + ce que va apporter l'article (100-150 mots)
3. **Corps principal** : 3-5 sections avec sous-titres H2/H3
4. **Conclusion** : Synthèse + appel à action (80-100 mots)

**FORMAT DE RÉPONSE JSON STRICT:**
```json
{
    \"title\": \"Titre H1 principal\",
    \"excerpt\": \"Résumé de 120-150 mots\",
    \"content_html\": \"<h1>Titre</h1><p>Contenu avec backlinks intégrés...</p>\",
    \"word_count\": nombre_de_mots_exact_calculé,
    \"categories\": [\"catégorie1\", \"catégorie2\"],
    \"integrated_backlinks\": [
        {\"url\": \"/articles/slug\", \"anchor\": \"texte du lien\", \"title\": \"Titre article\", \"context\": \"phrase où le lien est placé\"}
    ]
}
```

{$siteContext}
{$categoriesText}
{$backlinkInstructions}
{$existingArticlesText}

**🚨 VÉRIFICATIONS FINALES CRITIQUES - AUCUNE EXCEPTION:**
1. ✅ COMPTER PRÉCISÉMENT les mots dans content_html (ignorer balises HTML)
2. ✅ RESPECTER ABSOLUMENT {$wordCount} ±50 mots - AJUSTER si nécessaire
3. ✅ BACKLINKS DISPERSÉS : intro + corps + conclusion (JAMAIS tous au même endroit)
4. ✅ HTML VALIDE et liens fonctionnels
5. ✅ JSON valide sans erreurs de syntaxe

❌ ÉCHEC AUTOMATIQUE SI:
- Nombre de mots hors limite ±50
- Tous les backlinks concentrés en conclusion
- JSON malformé
- HTML cassé";
    }

    private function buildOptimizedUserPrompt(string $prompt, string $language, int $wordCount): string
    {
        $languageNames = [
            'fr' => 'français',
            'en' => 'anglais',
            'es' => 'espagnol',
            'de' => 'allemand',
            'it' => 'italien',
            'pt' => 'portugais',
            'nl' => 'néerlandais',
            'ru' => 'russe',
            'ja' => 'japonais',
            'zh' => 'chinois',
        ];

        $targetLanguage = $languageNames[$language] ?? 'français';

        return "**SUJET :** {$prompt}

**INSTRUCTIONS SPÉCIFIQUES :**
- Rédiger en {$targetLanguage} de façon experte et technique
- EXACTEMENT entre " . ($wordCount - 50) . " et " . ($wordCount + 50) . " mots
- Intégrer naturellement les backlinks suggérés ci-dessus
- Style informatif et professionnel avec données chiffrées
- Optimisation SEO naturelle
- Structure claire avec titres H2/H3

**CONTRAINTE CRITIQUE :** Respecter impérativement le nombre de mots demandé et inclure la liste des backlinks intégrés dans le JSON de réponse.";
    }

    private function buildTranslationSystemPrompt(string $sourceLanguage, string $targetLanguage): string
    {
        // Implementation of buildTranslationSystemPrompt method
        // This method should return a string representing the translation system prompt
        // based on the provided parameters.
        // You can implement this method based on your specific requirements.
        // For example, you can use the parameters to construct a more personalized prompt.
        return "This is a placeholder for the buildTranslationSystemPrompt method. It should be implemented based on your specific requirements.";
    }

    private function buildTranslationUserPrompt(array $data): string
    {
        // Implementation of buildTranslationUserPrompt method
        // This method should return a string representing the translation user prompt
        // based on the provided parameters.
        // You can implement this method based on your specific requirements.
        // For example, you can use the parameters to construct a more personalized prompt.
        return "This is a placeholder for the buildTranslationUserPrompt method. It should be implemented based on your specific requirements.";
    }

    private function parseAIResponse(string $content): array
    {
        try {
            // Nettoyer le contenu de la réponse IA
            $content = trim($content);
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            // Valider la structure de base
            $required = ['title', 'excerpt', 'content_html'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    throw new \Exception("Missing required field: {$field}");
                }
            }

            // **NOUVEAU: Traiter les backlinks intégrés par l'IA**
            $integratedBacklinks = $data['integrated_backlinks'] ?? [];
            
            if (!empty($integratedBacklinks)) {
                // Déduire les points pour les liens externes
                $userPoints = UserBacklinkPoints::getOrCreateForUser(auth()->id());
                $externalLinksCount = 0;
                
                foreach ($integratedBacklinks as $backlink) {
                    // Vérifier si c'est un lien externe (par convention, les internes commencent par /articles/)
                    if (isset($backlink['url']) && !str_starts_with($backlink['url'], '/articles/')) {
                        $externalLinksCount++;
                    }
                }
                
                // Déduire les points pour les liens externes utilisés
                if ($externalLinksCount > 0 && $userPoints->canUsePoints($externalLinksCount)) {
                    $userPoints->usePoints($externalLinksCount);
                    
                    Log::info('🔗 Points déducted for external backlinks', [
                        'external_links_count' => $externalLinksCount,
                        'points_remaining' => $userPoints->fresh()->available_points,
                        'user_id' => auth()->id()
                    ]);
                }
                
                // Ajouter des métadonnées sur les backlinks
                $data['backlinks_metadata'] = [
                    'total_integrated' => count($integratedBacklinks),
                    'external_links_count' => $externalLinksCount,
                    'points_used' => $externalLinksCount,
                    'internal_links_count' => count($integratedBacklinks) - $externalLinksCount,
                ];
            }

            // Nettoyer et valider le HTML
            if (isset($data['content_html'])) {
                $data['content_html'] = $this->cleanAndValidateHtml($data['content_html']);
            }

            // Compter les mots réels
            $actualWordCount = $this->countWordsInHtml($data['content_html']);
            $data['actual_word_count'] = $actualWordCount;

            // Valeurs par défaut
            $data['categories'] = $data['categories'] ?? [];
            $data['word_count'] = $data['word_count'] ?? $actualWordCount;
            $data['integrated_backlinks'] = $integratedBacklinks;

            Log::info('✅ AI response parsed successfully', [
                'title' => substr($data['title'], 0, 50) . '...',
                'word_count' => $data['word_count'],
                'actual_word_count' => $actualWordCount,
                'categories_count' => count($data['categories']),
                'backlinks_integrated' => count($integratedBacklinks),
            ]);

            return $data;

        } catch (\Exception $e) {
            Log::error('❌ Failed to parse AI response', [
                'error' => $e->getMessage(),
                'content_preview' => substr($content, 0, 200)
            ]);
            
            // Retourner un contenu de fallback
            return $this->createFallbackContent($content);
        }
    }

    /**
     * Nettoyer et valider le HTML
     */
    private function cleanAndValidateHtml(string $html): string
    {
        // Supprimer les scripts et styles dangereux
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        // Autoriser seulement les balises sécurisées
        $allowedTags = '<h1><h2><h3><h4><h5><h6><p><strong><em><ul><ol><li><a><br><blockquote><code><pre>';
        $html = strip_tags($html, $allowedTags);
        
        // Nettoyer les attributs des liens
        $html = preg_replace('/<a\s+([^>]*?)>/i', '<a $1>', $html);
        
        return trim($html);
    }

    /**
     * Compter les mots dans le HTML
     */
    private function countWordsInHtml(string $html): int
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        $words = explode(' ', trim($text));
        return count(array_filter($words, fn($word) => !empty(trim($word))));
    }

    private function parseTranslationResponse(string $content): array
    {
        // Implementation of parseTranslationResponse method
        // This method should return an array representing the parsed translation response
        // based on the provided content.
        // You can implement this method based on your specific requirements.
        // For example, you can use the content to parse and return the structured data.
        return [];
    }

    private function createFallbackContent(string $rawContent): array
    {
        // Implementation of createFallbackContent method
        // This method should return an array representing the fallback content
        // based on the provided raw content.
        // You can implement this method based on your specific requirements.
        // For example, you can use the raw content to create a fallback content structure.
        return [];
    }
} 