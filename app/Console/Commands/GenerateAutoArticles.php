<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Site;
use App\Models\Article;
use App\Models\Language;
use App\Models\SiteTopic;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\Category;

class GenerateAutoArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:generate-auto 
                           {--site-id= : ID du site spécifique}
                           {--dry-run : Simuler sans créer d\'articles}
                           {--force : Forcer même si pas programmé pour aujourd\'hui}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Génère automatiquement les articles à partir des topics programmés pour aujourd\'hui';

    private int $articlesGenerated = 0;
    private int $topicsProcessed = 0;
    private array $errors = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $siteId = $this->option('site-id');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info('🤖 Génération automatique d\'articles à partir des topics programmés');
        
        if ($dryRun) {
            $this->warn('🧪 Mode DRY-RUN activé - Aucun article ne sera créé');
        }
        
        $this->newLine();

        try {
            // Vérifier la configuration OpenAI
            if (!config('services.openai.key')) {
                $this->error('❌ Configuration OpenAI manquante (OPENAI_API_KEY non définie)');
                return 1;
            }

            // Récupérer les topics programmés pour aujourd'hui
            $query = SiteTopic::with(['site'])
                ->where('status', 'scheduled')
                ->where('scheduled_date', today());

            if (!$force) {
                // Vérifier aussi l'heure si pas en mode force
                $query->where('scheduled_time', '<=', now()->format('H:i'));
            }

            if ($siteId) {
                $query->where('site_id', $siteId);
            }

            $topics = $query->get();

            $this->info("📋 Topics programmés trouvés : {$topics->count()}");

            if ($topics->isEmpty()) {
                $this->info('✅ Aucun topic programmé pour le moment');
                return 0;
            }

            $this->newLine();
            $progressBar = $this->output->createProgressBar($topics->count());
            $progressBar->start();

            foreach ($topics as $topic) {
                if (!$dryRun) {
                    $this->processTopicToArticle($topic);
                } else {
                    $this->info("🧪 [DRY-RUN] Topic : {$topic->title}");
                    $this->articlesGenerated++;
                }
                
                $this->topicsProcessed++;
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // Afficher les résultats
            $this->newLine();
            $this->info('📊 Résultats de la génération :');
            $this->table(['Métrique', 'Valeur'], [
                ['Topics traités', $this->topicsProcessed],
                ['Articles générés', $this->articlesGenerated],
                ['Erreurs', count($this->errors)],
                ['Mode', $dryRun ? 'DRY-RUN' : 'RÉEL'],
            ]);

            if (!empty($this->errors)) {
                $this->newLine();
                $this->error('❌ Erreurs rencontrées :');
                foreach ($this->errors as $error) {
                    $this->line("  • {$error}");
                }
            }

            $this->newLine();
            $this->info('✅ Génération terminée !');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la génération : {$e->getMessage()}");
            Log::error('GenerateAutoArticles failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    private function processTopicToArticle(SiteTopic $topic): void
    {
        try {
            $this->line("🔄 Génération de l'article pour : {$topic->title}");

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
                'content_html' => $articleContent['content'], // Le contenu est déjà en HTML
                'excerpt' => $articleContent['excerpt'],
                'meta_title' => $articleContent['meta_title'] ?? $topic->title,
                'meta_description' => $articleContent['meta_description'] ?? $topic->description,
                'meta_keywords' => !empty($articleContent['meta_keywords']) ? implode(',', $articleContent['meta_keywords']) : null,
                'status' => 'published',
                'published_at' => now(),
                'author_name' => $articleContent['author_name'] ?? 'IA Content Generator',
                'language_code' => $topic->language_code,
                'source' => 'auto_generated_from_topic',
                'external_id' => 'topic_' . $topic->id . '_' . time(),
                'is_synced' => false, // Pour qu'il soit pris par la synchronisation
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

            // Marquer le topic comme publié
            $topic->update([
                'status' => 'published',
                'last_used_at' => now(),
                'article_id' => $article->id, // Lier le topic à l'article créé
            ]);
            $topic->increment('usage_count');

            $this->articlesGenerated++;
            
            Log::info('Article auto-generated from topic', [
                'topic_id' => $topic->id,
                'article_id' => $article->id,
                'site_id' => $topic->site_id,
                'title' => $article->title,
                'word_count' => $article->word_count,
                'categories_from_topic' => count($topic->categories ?? []),
                'categories_from_ai' => count($articleContent['suggested_categories'] ?? []),
            ]);

        } catch (\Exception $e) {
            $error = "Topic {$topic->id} ({$topic->title}) : {$e->getMessage()}";
            $this->errors[] = $error;
            
            Log::error('Failed to generate article from topic', [
                'topic_id' => $topic->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function generateArticleWithAI(SiteTopic $topic): ?array
    {
        try {
            // Charger le site avec ses relations pour le contexte
            $site = $topic->site;
            $site->load(['languages', 'articles' => function($query) use ($topic) {
                $query->where('language_code', $topic->language_code)
                      ->where('status', 'published')
                      ->select('id', 'title', 'excerpt', 'site_id')
                      ->limit(10)
                      ->latest();
            }]);

            // Préparer le contexte du site et les articles existants
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
                
                // Récupérer les catégories disponibles pour ce site
                $categoriesQuery = $site->categories();
                if ($topic->language_code) {
                    $categoriesQuery->where('categories.language_code', $topic->language_code);
                }
                $availableCategories = $categoriesQuery->pluck('categories.name')->toArray();
                
                // Articles existants pour le contexte
                $existingArticles = $site->articles->map(function($article) {
                    return [
                        'title' => $article->title,
                        'excerpt' => $article->excerpt
                    ];
                })->toArray();
            }

            // Construire le prompt optimisé (similaire à AIController)
            $systemPrompt = $this->buildOptimizedSystemPrompt($topic->language_code, $siteContext, $availableCategories, $existingArticles);
            $userPrompt = $this->buildOptimizedUserPrompt($topic);

            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . config('services.openai.key'),
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4o-mini', // Utiliser le même modèle que AIController
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

            // Parser la réponse JSON de l'IA
            $parsedContent = $this->parseAIResponse($content);

            Log::info('Article generated from topic with AI', [
                'topic_id' => $topic->id,
                'tokens_used' => $data['usage']['total_tokens'] ?? 0,
                'title' => $parsedContent['title'] ?? 'N/A'
            ]);

            return $parsedContent;

        } catch (\Exception $e) {
            Log::error('OpenAI API error in GenerateAutoArticles', [
                'topic_id' => $topic->id,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Construit le prompt système optimisé (adapté de AIController)
     */
    private function buildOptimizedSystemPrompt(string $language, string $siteContext, array $availableCategories, array $existingArticles): string
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
            'zh' => 'chinois'
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
        
        if (!empty($existingArticles)) {
            $prompt .= "\n\nArticles existants sur le site (pour éviter la duplication) :";
            foreach (array_slice($existingArticles, 0, 5) as $article) {
                $prompt .= "\n- {$article['title']}";
            }
        }
        
        $prompt .= "\n\nExigences pour l'article :";
        $prompt .= "\n- 800-1200 mots minimum";
        $prompt .= "\n- Contenu original et unique";
        $prompt .= "\n- Structure claire avec titres H2 et H3";
        $prompt .= "\n- HTML bien formaté et sémantique";
        $prompt .= "\n- Optimisé pour le SEO";
        $prompt .= "\n- Ton professionnel mais accessible";
        $prompt .= "\n- Intégration naturelle des mots-clés";
        
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

    /**
     * Construit le prompt utilisateur optimisé à partir du topic
     */
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
        
        if (!empty($topic->categories)) {
            $categories = is_array($topic->categories) ? implode(', ', $topic->categories) : $topic->categories;
            $prompt .= "\n\nCatégories suggérées : {$categories}";
        }
        
        if ($topic->ai_context) {
            $prompt .= "\n\nContexte spécifique : {$topic->ai_context}";
        }
        
        $prompt .= "\n\nCrée un article informatif, bien structuré et optimisé SEO qui apporte une vraie valeur ajoutée aux lecteurs.";
        
        return $prompt;
    }

    /**
     * Parse la réponse JSON de l'IA (adapté de AIController)
     */
    private function parseAIResponse(string $content): array
    {
        // Nettoyer le contenu et essayer de parser le JSON
        $content = trim($content);
        
        // Extraire le JSON s'il est entouré de texte
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/```\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        }
        
        $parsedContent = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Fallback : créer une structure JSON basique
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

        // Valider et nettoyer les données
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

    private function buildPrompt(SiteTopic $topic): string
    {
        // Cette méthode est maintenant dépréciée au profit de buildOptimizedUserPrompt
        // On la garde pour la compatibilité
        return $this->buildOptimizedUserPrompt($topic);
    }

    private function syncCategories(Article $article, array $categoryNames, string $source): void
    {
        $categoryIds = [];

        foreach ($categoryNames as $categoryName) {
            if (empty($categoryName)) continue;

            // Assurer que categoryName est une chaîne de caractères
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
            // Utiliser syncWithoutDetaching pour ajouter les catégories sans supprimer les existantes
            if ($source === 'topic_categories') {
                $article->categories()->sync($categoryIds);
            } else {
                // Pour les suggestions IA, on ajoute sans supprimer les catégories du topic
                $article->categories()->syncWithoutDetaching($categoryIds);
            }

            Log::info("Categories synced for article {$article->id}", [
                'source' => $source,
                'categories' => $categoryNames,
                'category_ids' => $categoryIds,
            ]);
        }
    }
}
