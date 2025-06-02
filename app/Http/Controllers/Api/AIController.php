<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                    
                    // Récupérer les catégories disponibles
                    $categoriesQuery = $site->categories();
                    if ($language) {
                        $categoriesQuery->where('categories.language_code', $language);
                    }
                    $availableCategories = $categoriesQuery->pluck('categories.name')->toArray();
                    
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

            // Parser la réponse JSON de l'IA
            $parsedContent = $this->parseAIResponse($content);

            // **NOUVEAU: Post-traiter pour intégrer les backlinks intelligemment**
            if ($siteId && !empty($backlinkSuggestions)) {
                $parsedContent = $this->integrateBacklinks($parsedContent, $backlinkSuggestions, $siteId);
            }

            // Mettre en cache pour 24h
            cache()->put($cacheKey, $parsedContent, now()->addHours(24));
            
            Log::info('Article generated and cached', [
                'cache_key' => $cacheKey,
                'tokens_used' => $aiResponse['usage']['total_tokens'] ?? 0,
                'backlinks_integrated' => count($backlinkSuggestions)
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
     * Récupérer les suggestions de backlinks pertinentes pour le prompt
     */
    private function getBacklinkSuggestionsForPrompt(string $prompt, ?int $siteId, string $language): array
    {
        if (!$siteId) {
            return [];
        }

        // Rechercher des articles existants qui pourraient être pertinents
        $relevantArticles = Article::where('site_id', $siteId)
            ->where('language_code', $language)
            ->where('status', 'published')
            ->whereNotNull('content_html')
            ->limit(5)
            ->get();

        // Rechercher également des articles d'autres sites (si l'utilisateur a des points)
        $userPoints = UserBacklinkPoints::getOrCreateForUser(auth()->id());
        $externalArticles = [];

        if ($userPoints->canUsePoints(2)) {
            $externalArticles = Article::where('site_id', '!=', $siteId)
                ->where('language_code', $language)
                ->where('status', 'published')
                ->whereNotNull('content_html')
                ->limit(3)
                ->get();
        }

        $allArticles = $relevantArticles->concat($externalArticles);

        return $allArticles->map(function($article) use ($siteId) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'is_same_site' => $article->site_id === $siteId,
                'slug' => $article->slug ?? str()->slug($article->title),
            ];
        })->toArray();
    }

    /**
     * Intégrer intelligemment les backlinks dans le contenu généré
     */
    private function integrateBacklinks(array $parsedContent, array $suggestions, int $siteId): array
    {
        if (empty($suggestions) || empty($parsedContent['content_html'])) {
            return $parsedContent;
        }

        $html = $parsedContent['content_html'];
        $userPoints = UserBacklinkPoints::getOrCreateForUser(auth()->id());
        
        // Séparer les suggestions par priorité
        $sameSiteLinks = array_filter($suggestions, fn($s) => $s['is_same_site']);
        $externalLinks = array_filter($suggestions, fn($s) => !$s['is_same_site']);

        $linksAdded = 0;
        $externalLinksAdded = 0;

        // Priorité 1: Ajouter les liens internes (gratuits)
        foreach ($sameSiteLinks as $link) {
            if ($linksAdded >= 3) break; // Max 3 liens total

            $anchor = $this->generateNaturalAnchor($link['title']);
            $url = "/articles/{$link['slug']}";
            
            // Chercher un endroit naturel dans le contenu pour insérer le lien
            $html = $this->insertLinkNaturally($html, $anchor, $url, $link['title']);
            $linksAdded++;
        }

        // Priorité 2: Ajouter des liens externes (coûtent des points)
        foreach ($externalLinks as $link) {
            if ($linksAdded >= 3 || $externalLinksAdded >= 2) break;
            if (!$userPoints->canUsePoints(1)) break;

            $anchor = $this->generateNaturalAnchor($link['title']);
            $url = "/articles/{$link['slug']}"; // URL relative pour l'instant
            
            $html = $this->insertLinkNaturally($html, $anchor, $url, $link['title']);
            $userPoints->usePoints(1); // Déduire un point
            $linksAdded++;
            $externalLinksAdded++;
        }

        $parsedContent['content_html'] = $html;
        $parsedContent['backlinks_count'] = $linksAdded;
        $parsedContent['external_backlinks_count'] = $externalLinksAdded;
        $parsedContent['points_used'] = $externalLinksAdded;

        return $parsedContent;
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

    private function buildOptimizedSystemPrompt(string $language, string $siteContext, array $categories, array $existingArticles, int $wordCount, array $backlinkSuggestions = []): string
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
        $categoriesText = !empty($categories) ? "\nCatégories disponibles: " . implode(', ', $categories) : '';

        // Ajouter les articles existants pour les liens internes
        $existingArticlesText = '';
        $hasExistingArticles = !empty($existingArticles);
        if ($hasExistingArticles) {
            $existingArticlesText = "\nArticles existants sur le site (pour créer des liens internes):";
            foreach ($existingArticles as $article) {
                $existingArticlesText .= "\n- " . $article['title'] . " (" . substr($article['excerpt'], 0, 100) . "...)";
            }
        }

        // **NOUVEAU: Ajouter les suggestions de backlinks**
        $backlinkText = '';
        if (!empty($backlinkSuggestions)) {
            $backlinkText = "\nArticles recommandés pour backlinks:";
            foreach ($backlinkSuggestions as $suggestion) {
                $type = $suggestion['is_same_site'] ? 'Interne' : 'Externe';
                $backlinkText .= "\n- [{$type}] " . $suggestion['title'] . " (" . substr($suggestion['excerpt'], 0, 80) . "...)";
            }
        }

        // Instructions conditionnelles pour les liens
        $linkInstructions = $hasExistingArticles 
            ? "- **Liens internes**: Créer 2-3 liens vers articles existants avec ancres naturelles"
            : "- **Liens internes**: Pas d'articles existants disponibles, se concentrer sur le contenu";

        return "Tu es un rédacteur web expert qui crée des articles informatifs et professionnels de MINIMUM {$wordCount} mots en {$targetLanguage}.
Ton style : informatif, accessible, utile, avec un ton naturel mais professionnel.

{$siteContext}
{$categoriesText}
{$existingArticlesText}
{$backlinkText}

🎯 RÈGLES ESSENTIELLES:
1. Article MINIMUM {$wordCount} mots - contenu dense et informatif
2. Ton professionnel mais accessible, comme un expert qui explique clairement
3. JAMAIS utiliser les mots 'introduction' ou 'conclusion' dans le contenu
4. Entrer directement dans le vif du sujet dès la première phrase
5. Intégrer des éléments interactifs UNIQUEMENT si pertinents pour l'action utilisateur
6. Optimisation SEO complète avec bonne hiérarchie de titres H1-H6
7. HTML sémantique et structure EditorJS
8. Réponse JSON uniquement

📝 STYLE D'ÉCRITURE:
- Ton professionnel et informatif (comme l'exemple fourni)
- Phrases claires et structurées
- Explications détaillées et pratiques
- Utilise 'vous' pour s'adresser au lecteur
- Conseils concrets et actionables
- Évite le jargon technique excessif
- Reste factuel et utile

🔗 GESTION DES LIENS ET CTA (OPTIONNELS):
{$linkInstructions}
- **Call-to-actions**: Ajouter UNIQUEMENT si une action utilisateur logique existe (contact, téléchargement, inscription, etc.)
- **Liens externes**: Liens vers ressources utiles avec attribut target='_blank' UNIQUEMENT si pertinents

Types de liens à créer (si appropriés):
- Liens inline: <a href='/article-slug'>texte de lien naturel</a>
- Boutons CTA: Utiliser le format JSON 'cta_buttons' SEULEMENT pour actions importantes
- Liens externes: <a href='https://site.com' target='_blank' rel='noopener'>ressource externe</a>

⚠️ IMPORTANT: Ne pas forcer les CTA ou liens internes s'ils ne sont pas naturels ou pertinents pour le contenu.

🏗️ STRUCTURE ATTENDUE:
- H1: Titre principal unique de l'article
- H2: 4-6 sections principales avec titres descriptifs
- H3: 2-3 sous-sections par section H2
- H4-H6: Pour structurer davantage si nécessaire
- Paragraphes bien développés avec informations pratiques
- Listes à puces pour clarifier les étapes ou points importants
- Call-to-actions intégrés stratégiquement SEULEMENT si pertinents
- Terminer par des conseils pratiques ou points à retenir

FORMAT JSON REQUIS:
{
    \"title\": \"Titre informatif et précis (50-60 char)\",
    \"excerpt\": \"Résumé clair et utile (150-200 char)\",
    \"content_html\": \"<h1>Titre principal</h1><p>Contenu avec <a href='/article-lié'>liens internes</a> et <a href='https://exemple.com' target='_blank'>liens externes</a>...</p><h2>Section importante</h2>...\",
    \"meta_title\": \"Titre SEO optimisé (50-60 char)\",
    \"meta_description\": \"Description SEO informative (150-160 char)\",
    \"meta_keywords\": \"mot-clé1, mot-clé2, mot-clé3\",
    \"author_name\": \"Expert [Domaine]\",
    \"author_bio\": \"Spécialiste avec expertise approfondie\",
    \"suggested_categories\": [\"catégorie1\", \"catégorie2\"],
    \"internal_links\": [{\"anchor\": \"texte de lien naturel\", \"target_title\": \"Titre article existant\"}],
    \"cta_buttons\": [
        {
            \"text\": \"🎯 Découvrir notre solution\",
            \"link\": \"/page-importante\",
            \"style\": \"primary\",
            \"position_after_paragraph\": 3
        }
    ]
}

⚠️ RAPPEL: Les champs 'internal_links' et 'cta_buttons' peuvent être vides [] si non pertinents.";
    }

    private function buildOptimizedUserPrompt(string $prompt, string $language, int $wordCount): string
    {
        return "Rédigez un article complet et informatif sur: {$prompt}

🎯 OBJECTIF:
✅ MINIMUM {$wordCount} mots de contenu utile et détaillé
✅ Ton professionnel mais accessible
✅ Éviter absolument 'introduction' et 'conclusion'
✅ Commencer directement par l'information principale
✅ Inclure des conseils pratiques et actionables
✅ Intégrer des liens internes de manière naturelle
✅ Optimisation SEO complète

📖 Créez un contenu de référence sur ce sujet.";
    }

    private function buildTranslationSystemPrompt(string $sourceLanguage, string $targetLanguage): string
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

        $sourceLang = $languageNames[$sourceLanguage] ?? 'français';
        $targetLang = $languageNames[$targetLanguage] ?? 'anglais';

        return "Tu es un traducteur professionnel spécialisé dans la traduction de contenu web et marketing.
Tu dois traduire fidèlement du {$sourceLang} vers le {$targetLang} en conservant:
- Le sens et le ton original
- La structure HTML des contenus
- L'optimisation SEO
- Le style et la voix de la marque

IMPORTANT: Tu dois répondre UNIQUEMENT avec un JSON valide dans ce format:
{
    \"title\": \"Titre traduit\",
    \"excerpt\": \"Résumé traduit\",
    \"content_html\": \"Contenu HTML traduit en conservant la structure\",
    \"meta_title\": \"Titre SEO traduit\",
    \"meta_description\": \"Description SEO traduite\",
    \"meta_keywords\": \"mots-clés traduits\",
    \"author_bio\": \"Biographie traduite\"
}

Pour le contenu HTML, conserve exactement la même structure de balises mais traduis uniquement les textes.";
    }

    private function buildTranslationUserPrompt(array $data): string
    {
        // Utiliser content_html s'il existe, sinon content
        $contentToTranslate = $data['content_html'] ?? $data['content'] ?? '';
        
        return "Traduis ce contenu:\n\n" . json_encode([
            'title' => $data['title'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'content_html' => $contentToTranslate,
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'meta_keywords' => $data['meta_keywords'] ?? '',
            'author_bio' => $data['author_bio'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
    }

    private function parseAIResponse(string $content): array
    {
        // Nettoyer le contenu (enlever markdown, etc.)
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        try {
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Réponse JSON invalide de l\'IA');
            }

            // S'assurer que les champs obligatoires existent
            $decoded['internal_links'] = $decoded['internal_links'] ?? [];
            $decoded['suggested_categories'] = $decoded['suggested_categories'] ?? [];
            $decoded['cta_buttons'] = $decoded['cta_buttons'] ?? [];

            // Traiter les boutons CTA et les intégrer dans le contenu HTML
            if (!empty($decoded['cta_buttons']) && !empty($decoded['content_html'])) {
                $decoded['content_html'] = $this->insertCtaButtons($decoded['content_html'], $decoded['cta_buttons']);
            }

            return $decoded;
        } catch (\Exception $e) {
            // Fallback: créer une structure par défaut avec HTML simple
            return $this->createFallbackContent($content);
        }
    }

    /**
     * Insérer les boutons CTA dans le contenu HTML aux positions spécifiées
     */
    private function insertCtaButtons(string $htmlContent, array $ctaButtons): string
    {
        // Diviser le contenu en paragraphes
        $paragraphs = preg_split('/(<\/p>)/', $htmlContent, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Reconstruire avec les boutons CTA insérés
        $result = '';
        $paragraphCount = 0;
        
        for ($i = 0; $i < count($paragraphs); $i++) {
            $result .= $paragraphs[$i];
            
            // Si c'est une fermeture de paragraphe
            if ($paragraphs[$i] === '</p>') {
                $paragraphCount++;
                
                // Vérifier s'il faut insérer un bouton CTA après ce paragraphe
                foreach ($ctaButtons as $cta) {
                    if (isset($cta['position_after_paragraph']) && $cta['position_after_paragraph'] == $paragraphCount) {
                        $buttonHtml = $this->generateCtaButtonHtml($cta);
                        $result .= "\n\n" . $buttonHtml . "\n\n";
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * Générer le HTML pour un bouton CTA compatible avec EditorJS
     */
    private function generateCtaButtonHtml(array $cta): string
    {
        $text = $cta['text'] ?? 'En savoir plus';
        $link = $cta['link'] ?? '#';
        $style = $cta['style'] ?? 'primary';
        
        // Détecter les liens externes
        $target = '';
        $rel = '';
        if (str_starts_with($link, 'http')) {
            $target = ' target="_blank"';
            $rel = ' rel="noopener noreferrer"';
        }

        // Générer le HTML sous format compatible EditorJS Button Tool
        return '<div class="button-tool">
    <div class="button-tool__preview">
        <a href="' . htmlspecialchars($link) . '" class="button-tool__btn button-tool__btn--' . htmlspecialchars($style) . '"' . $target . $rel . '>
            ' . htmlspecialchars($text) . '
        </a>
    </div>
</div>';
    }

    private function parseTranslationResponse(string $content): array
    {
        // Même logique que parseAIResponse mais pour la traduction
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        try {
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Réponse de traduction JSON invalide');
            }

            return $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors du parsing de la traduction: ' . $e->getMessage());
        }
    }

    private function createFallbackContent(string $rawContent): array
    {
        // Créer un contenu HTML simple par défaut
        $htmlContent = "<h2>Article généré par IA</h2><p>Contenu généré automatiquement</p><p>" . htmlspecialchars($rawContent) . "</p>";
        
        return [
            'title' => 'Article généré par IA',
            'excerpt' => 'Contenu généré automatiquement',
            'content_html' => $htmlContent,
            'meta_title' => 'Article généré par IA',
            'meta_description' => 'Contenu généré automatiquement',
            'meta_keywords' => 'article, ia, automatique',
            'author_name' => 'Assistant IA',
            'author_bio' => 'Contenu généré par intelligence artificielle',
            'suggested_categories' => [],
            'internal_links' => [],
            'cta_buttons' => []
        ];
    }
} 