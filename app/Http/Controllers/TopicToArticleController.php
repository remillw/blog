<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SiteTopic;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TopicToArticleController extends Controller
{
    /**
     * Génère un article directement depuis un topic spécifique
     */
    public function generateFromTopic(Request $request, SiteTopic $topic)
    {
        try {
            // Vérifier que l'utilisateur a accès au topic
            if (Auth::id() !== $topic->site->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accès non autorisé'
                ], 403);
            }

            // Vérifier la configuration OpenAI
            if (!config('services.openai.key')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Configuration OpenAI manquante (OPENAI_API_KEY non définie)'
                ], 500);
            }

            Log::info('Starting article generation from topic', [
                'topic_id' => $topic->id,
                'topic_title' => $topic->title,
                'site_id' => $topic->site_id,
                'user_id' => Auth::id()
            ]);

            // Générer le contenu avec l'IA
            $articleContent = $this->generateArticleWithAI($topic);
            
            if (!$articleContent) {
                throw new \Exception("Échec de la génération du contenu avec l'IA");
            }

            // Préparer les données de l'article
            $articleData = [
                'site_id' => $topic->site_id,
                'user_id' => $topic->site->user_id,
                'title' => $articleContent['title'] ?? $topic->title,
                'content' => $articleContent['content'],
                'content_html' => $articleContent['content'],
                'excerpt' => $articleContent['excerpt'],
                'meta_title' => $articleContent['meta_title'] ?? $topic->title,
                'meta_description' => $articleContent['meta_description'] ?? $topic->description,
                'meta_keywords' => !empty($articleContent['meta_keywords']) ? implode(',', $articleContent['meta_keywords']) : null,
                'status' => 'draft',
                'published_at' => null,
                'author_name' => $articleContent['author_name'] ?? 'IA Content Generator',
                'language_code' => $topic->language_code,
                'source' => 'generated_from_topic',
                'external_id' => 'topic_' . $topic->id . '_' . time(),
                'is_synced' => false,
                'word_count' => str_word_count(strip_tags($articleContent['content'])),
                'reading_time' => ceil(str_word_count(strip_tags($articleContent['content'])) / 200),
            ];

            // Générer un slug unique
            $slug = Str::slug($articleData['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Article::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $articleData['slug'] = $slug;

            // Créer l'article
            $article = Article::create($articleData);

            // Associer les catégories du topic original
            if (!empty($topic->categories)) {
                $this->syncCategories($article, $topic->categories, 'topic_categories');
            }

            // Associer les catégories suggérées par l'IA
            if (!empty($articleContent['suggested_categories'])) {
                $this->syncCategories($article, $articleContent['suggested_categories'], 'ai_suggestions');
            }

            // Marquer le topic comme utilisé et lier à l'article créé
            $topic->update([
                'status' => 'published',
                'last_used_at' => now(),
                'article_id' => $article->id,
            ]);
            $topic->increment('usage_count');

            Log::info('Article generated successfully from topic', [
                'topic_id' => $topic->id,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'word_count' => $article->word_count,
            ]);

            // Déterminer le format de réponse selon le type de requête
            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Article généré avec succès ! 🎉',
                    'article' => [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'word_count' => $article->word_count,
                        'reading_time' => $article->reading_time,
                        'edit_url' => route('articles.edit', $article->id)
                    ]
                ]);
            } else {
                // Pour Inertia, utiliser back() avec les données dans la session
                return back()->with([
                    'success' => 'Article généré avec succès ! 🎉',
                    'article_data' => [
                        'id' => $article->id,
                        'title' => $article->title,
                        'slug' => $article->slug,
                        'word_count' => $article->word_count,
                        'reading_time' => $article->reading_time,
                        'edit_url' => route('articles.edit', $article->id)
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to generate article from topic', [
                'topic_id' => $topic->id,
                'error' => $e->getMessage(),
            ]);

            $errorMessage = 'Erreur lors de la génération : ' . $e->getMessage();
            
            if (str_contains($e->getMessage(), 'cURL error')) {
                $errorMessage = 'Erreur de connexion à l\'API OpenAI. Vérifiez votre connexion internet.';
            } elseif (str_contains($e->getMessage(), '401')) {
                $errorMessage = 'Clé API OpenAI invalide. Vérifiez votre configuration.';
            } elseif (str_contains($e->getMessage(), '429')) {
                $errorMessage = 'Limite de requêtes OpenAI atteinte. Essayez plus tard.';
            }

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], 500);
            } else {
                return back()->with('error', $errorMessage);
            }
        }
    }

    private function generateArticleWithAI(SiteTopic $topic): ?array
    {
        try {
            $site = $topic->site;
            $site->load(['languages', 'articles' => function($query) use ($topic) {
                $query->where('language_code', $topic->language_code)
                      ->where('status', 'published')
                      ->select('id', 'title', 'excerpt', 'site_id')
                      ->limit(10)
                      ->latest();
            }]);

            $siteContext = '';
            $availableCategories = [];
            $existingArticles = [];

            if ($site) {
                $siteContext = "\nContexte du site: {$site->name}";
                if ($site->description) {
                    $siteContext .= " - {$site->description}";
                }
                if ($site->auto_content_guidelines) {
                    $siteContext .= "\nDirectives de contenu: {$site->auto_content_guidelines}";
                }
                
                $categoriesQuery = $site->categories();
                if ($topic->language_code) {
                    $categoriesQuery->where('categories.language_code', $topic->language_code);
                }
                $availableCategories = $categoriesQuery->pluck('categories.name')->toArray();
                
                $existingArticles = $site->articles->map(function($article) {
                    return [
                        'title' => $article->title,
                        'excerpt' => $article->excerpt
                    ];
                })->toArray();
            }

            $systemPrompt = $this->buildOptimizedSystemPrompt($topic->language_code, $siteContext, $availableCategories, $existingArticles);
            $userPrompt = $this->buildOptimizedUserPrompt($topic);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.openai.key'),
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini',
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
                    'max_tokens' => 3000,
                    'temperature' => 0.7,
                ]);

            if (!$response->successful()) {
                throw new \Exception("Erreur API OpenAI : " . $response->status() . " - " . $response->body());
            }

            $data = $response->json();
            
            if (isset($data['error'])) {
                throw new \Exception("Erreur OpenAI : " . $data['error']['message']);
            }

            $content = $data['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                throw new \Exception("Contenu vide reçu de l'API OpenAI");
            }

            return $this->parseAIResponse($content);

        } catch (\Exception $e) {
            Log::error('OpenAI API error in TopicToArticleController', [
                'topic_id' => $topic->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function buildOptimizedSystemPrompt(string $language, string $siteContext, array $availableCategories, array $existingArticles): string
    {
        $languageNames = [
            'fr' => 'français',
            'en' => 'anglais', 
            'es' => 'espagnol',
            'de' => 'allemand',
            'it' => 'italien',
        ];
        
        $languageName = $languageNames[$language] ?? $language;
        
        $prompt = "Tu es un rédacteur web professionnel et expert SEO. Tu dois créer un article complet et engageant en {$languageName}.";
        
        if ($siteContext) {
            $prompt .= $siteContext;
        }
        
        if (!empty($availableCategories)) {
            $categories = implode(', ', array_slice($availableCategories, 0, 10));
            $prompt .= "\n\nCatégories disponibles sur le site : {$categories}";
        }
        
        $prompt .= "\n\nFormat de réponse OBLIGATOIRE (JSON uniquement) :";
        $prompt .= "\n{";
        $prompt .= "\n  \"title\": \"Titre optimisé SEO\",";
        $prompt .= "\n  \"content\": \"Contenu HTML complet\",";
        $prompt .= "\n  \"excerpt\": \"Résumé en 160 caractères max\",";
        $prompt .= "\n  \"meta_title\": \"Titre SEO (60 chars max)\",";
        $prompt .= "\n  \"meta_description\": \"Description SEO (160 chars max)\",";
        $prompt .= "\n  \"meta_keywords\": [\"mot-clé1\", \"mot-clé2\"],";
        $prompt .= "\n  \"author_name\": \"IA Content Generator\",";
        $prompt .= "\n  \"suggested_categories\": [\"catégorie1\", \"catégorie2\"]";
        $prompt .= "\n}";
        
        return $prompt;
    }

    private function buildOptimizedUserPrompt(SiteTopic $topic): string
    {
        $prompt = "Génère un article professionnel complet sur le sujet : {$topic->title}";
        
        if ($topic->description) {
            $prompt .= "\n\nDescription du sujet : {$topic->description}";
        }
        
        if (!empty($topic->keywords)) {
            $keywords = is_array($topic->keywords) ? implode(', ', $topic->keywords) : $topic->keywords;
            $prompt .= "\n\nMots-clés à intégrer naturellement : {$keywords}";
        }
        
        $prompt .= "\n\nCrée un article informatif, bien structuré et optimisé SEO qui apporte une vraie valeur ajoutée aux lecteurs.";
        
        return $prompt;
    }

    private function parseAIResponse(string $content): array
    {
        $content = trim($content);
        
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }
        
        $parsedContent = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'title' => 'Article généré automatiquement',
                'content' => $content,
                'excerpt' => Str::limit(strip_tags($content), 160),
                'meta_title' => 'Article généré automatiquement',
                'meta_description' => Str::limit(strip_tags($content), 160),
                'meta_keywords' => [],
                'author_name' => 'IA Content Generator',
                'suggested_categories' => []
            ];
        }

        return [
            'title' => $parsedContent['title'] ?? 'Article généré automatiquement',
            'content' => $parsedContent['content'] ?? $content,
            'excerpt' => $parsedContent['excerpt'] ?? Str::limit(strip_tags($parsedContent['content'] ?? $content), 160),
            'meta_title' => $parsedContent['meta_title'] ?? ($parsedContent['title'] ?? 'Article généré automatiquement'),
            'meta_description' => $parsedContent['meta_description'] ?? Str::limit(strip_tags($parsedContent['content'] ?? $content), 160),
            'meta_keywords' => $parsedContent['meta_keywords'] ?? [],
            'author_name' => $parsedContent['author_name'] ?? 'IA Content Generator',
            'suggested_categories' => $parsedContent['suggested_categories'] ?? []
        ];
    }

    private function syncCategories(Article $article, array $categoryNames, string $source): void
    {
        $categoryIds = [];

        foreach ($categoryNames as $categoryName) {
            if (empty($categoryName)) continue;

            $categoryName = trim((string) $categoryName);
            if (empty($categoryName)) continue;

            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                [
                    'slug' => Str::slug($categoryName),
                    'language_code' => $article->language_code,
                ]
            );

            $categoryIds[] = $category->id;
        }

        if (!empty($categoryIds)) {
            if ($source === 'topic_categories') {
                $article->categories()->sync($categoryIds);
            } else {
                $article->categories()->syncWithoutDetaching($categoryIds);
            }
        }
    }
}
