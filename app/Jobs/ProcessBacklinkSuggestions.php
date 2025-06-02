<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\Site;
use App\Models\BacklinkSuggestion;
use App\Models\UserBacklinkPoints;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProcessBacklinkSuggestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $openaiKey;

    public function __construct()
    {
        $this->openaiKey = env('OPENAI_API_KEY');
    }

    /**
     * Exécuter le job nocturne d'analyse des backlinks
     */
    public function handle(): void
    {
        Log::info('🔗 Starting nightly backlink analysis job');

        try {
            // 1. Recharger les points automatiques (3 points/semaine)
            $this->rechargeUserPoints();

            // 2. Récupérer les articles créés dans les dernières 24h
            $newArticles = Article::where('created_at', '>=', now()->subDay())
                ->whereNotNull('content_html')
                ->where('status', 'published')
                ->with(['site'])
                ->get();

            if ($newArticles->isEmpty()) {
                Log::info('🔗 No new articles to analyze');
                return;
            }

            Log::info("🔗 Analyzing {$newArticles->count()} new articles for backlink suggestions");

            // 3. Analyser chaque nouvel article par lot pour économiser
            $batches = $newArticles->chunk(10); // Traiter par lots de 10

            foreach ($batches as $batch) {
                $this->analyzeArticleBatch($batch);
                
                // Petit délai entre les batches pour éviter la surcharge
                sleep(2);
            }

            Log::info('🔗 Backlink analysis completed successfully');

        } catch (\Exception $e) {
            Log::error('🔗 Backlink analysis failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Recharger automatiquement les points des utilisateurs (3 points/semaine)
     */
    private function rechargeUserPoints(): void
    {
        // Vérifier si c'est lundi (début de semaine)
        if (now()->dayOfWeek !== 1) {
            return;
        }

        Log::info('🎯 Weekly points recharge - adding 3 points to all active users');

        // Recharger 3 points pour tous les utilisateurs ayant des sites
        $activeUsers = Site::distinct('user_id')->pluck('user_id');

        foreach ($activeUsers as $userId) {
            UserBacklinkPoints::updateOrCreate(
                ['user_id' => $userId],
                ['available_points' => \DB::raw('available_points + 3')]
            );
        }

        Log::info("🎯 Points recharged for {$activeUsers->count()} users");
    }

    /**
     * Analyser un lot d'articles pour économiser les appels IA
     */
    private function analyzeArticleBatch($articles): void
    {
        foreach ($articles as $article) {
            try {
                $this->analyzeArticleForBacklinks($article);
            } catch (\Exception $e) {
                Log::error('🔗 Failed to analyze article for backlinks', [
                    'article_id' => $article->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Analyser un article spécifique pour trouver des backlinks pertinents
     */
    private function analyzeArticleForBacklinks(Article $article): void
    {
        // 1. Extraire les mots-clés et sujets principaux de l'article
        $articleSummary = $this->extractArticleSummary($article);

        // 2. Trouver des articles candidats (même langue, catégories proches)
        $candidates = $this->findCandidateArticles($article);

        if ($candidates->isEmpty()) {
            Log::info("🔗 No candidates found for article {$article->id}");
            return;
        }

        // 3. Analyser la pertinence sémantique avec IA (économique)
        $relevantLinks = $this->analyzeSemanticRelevance($article, $candidates, $articleSummary);

        // 4. Sauvegarder les suggestions
        $this->saveBacklinkSuggestions($article, $relevantLinks);
    }

    /**
     * Extraire un résumé intelligent de l'article pour l'analyse
     */
    private function extractArticleSummary(Article $article): array
    {
        // Nettoyer le HTML et extraire le texte principal
        $text = strip_tags($article->content_html);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = substr($text, 0, 500); // Limiter pour économiser les tokens

        return [
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content_preview' => $text,
            'categories' => $article->categories->pluck('name')->toArray(),
            'language' => $article->language_code ?? 'fr'
        ];
    }

    /**
     * Trouver des articles candidats potentiels (pré-filtrage)
     */
    private function findCandidateArticles(Article $article)
    {
        $query = Article::where('id', '!=', $article->id)
            ->where('language_code', $article->language_code)
            ->where('status', 'published')
            ->whereNotNull('content_html');

        // Priorité 1: Articles du même site
        $sameDirectSiteArticles = (clone $query)
            ->where('site_id', $article->site_id)
            ->limit(5)
            ->get();

        // Priorité 2: Articles d'autres sites avec catégories similaires
        $otherSiteArticles = (clone $query)
            ->where('site_id', '!=', $article->site_id)
            ->whereHas('categories', function ($q) use ($article) {
                $categoryIds = $article->categories->pluck('id')->toArray();
                if (!empty($categoryIds)) {
                    $q->whereIn('categories.id', $categoryIds);
                }
            })
            ->with(['site', 'categories'])
            ->limit(10)
            ->get();

        return $sameDirectSiteArticles->concat($otherSiteArticles);
    }

    /**
     * Analyser la pertinence sémantique avec IA (optimisé coûts)
     */
    private function analyzeSemanticRelevance(Article $sourceArticle, $candidates, array $sourceSummary): array
    {
        // Préparer les données pour l'IA
        $candidatesData = $candidates->map(function ($candidate) {
            return [
                'id' => $candidate->id,
                'title' => $candidate->title,
                'excerpt' => $candidate->excerpt,
                'site_id' => $candidate->site_id,
                'is_same_site' => $candidate->site_id === $sourceArticle->site_id,
                'content_preview' => substr(strip_tags($candidate->content_html), 0, 200),
                'categories' => $candidate->categories->pluck('name')->toArray()
            ];
        })->toArray();

        // Prompt optimisé pour l'économie (tokens minimaux)
        $prompt = $this->buildRelevanceAnalysisPrompt($sourceSummary, $candidatesData);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->openaiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini', // Le plus économique
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en analyse de pertinence sémantique pour les backlinks. Réponds UNIQUEMENT en JSON valide.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 800, // Limitation stricte pour économiser
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API error: ' . $response->status());
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? '';

            return $this->parseRelevanceResponse($content);

        } catch (\Exception $e) {
            Log::error('🔗 Semantic analysis failed', [
                'article_id' => $sourceArticle->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Construire le prompt d'analyse de pertinence (optimisé tokens)
     */
    private function buildRelevanceAnalysisPrompt(array $source, array $candidates): string
    {
        $sourceText = json_encode($source, JSON_UNESCAPED_UNICODE);
        $candidatesText = json_encode($candidates, JSON_UNESCAPED_UNICODE);

        return "ARTICLE SOURCE: {$sourceText}

CANDIDATS: {$candidatesText}

Analyse la pertinence sémantique entre l'article source et chaque candidat.
Règles:
- Pertinence > 75% pour créer un backlink
- Max 2 liens externes (sites différents)
- Priorité aux articles du même site
- Éviter les liens forcés/non-naturels

Réponds en JSON:
{
    \"relevant_links\": [
        {
            \"target_id\": 123,
            \"relevance_score\": 0.85,
            \"anchor_suggestion\": \"guide complet du jardinage\",
            \"reasoning\": \"Sujet connexe et complémentaire\"
        }
    ]
}";
    }

    /**
     * Parser la réponse IA et valider
     */
    private function parseRelevanceResponse(string $content): array
    {
        try {
            $content = trim($content);
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);

            $data = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response');
            }

            return $data['relevant_links'] ?? [];

        } catch (\Exception $e) {
            Log::error('🔗 Failed to parse relevance response', [
                'content' => $content,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Sauvegarder les suggestions de backlinks
     */
    private function saveBacklinkSuggestions(Article $sourceArticle, array $relevantLinks): void
    {
        // Nettoyer les anciennes suggestions pour cet article
        BacklinkSuggestion::where('source_article_id', $sourceArticle->id)->delete();

        foreach ($relevantLinks as $link) {
            if (isset($link['target_id']) && isset($link['relevance_score'])) {
                BacklinkSuggestion::create([
                    'source_article_id' => $sourceArticle->id,
                    'target_article_id' => $link['target_id'],
                    'relevance_score' => $link['relevance_score'],
                    'anchor_suggestion' => $link['anchor_suggestion'] ?? '',
                    'reasoning' => $link['reasoning'] ?? '',
                    'is_same_site' => Article::find($link['target_id'])?->site_id === $sourceArticle->site_id,
                ]);
            }
        }

        // Mettre en cache pour accès rapide
        $cacheKey = "backlink_suggestions_{$sourceArticle->id}";
        Cache::put($cacheKey, $relevantLinks, now()->addDays(7));

        Log::info("🔗 Saved " . count($relevantLinks) . " backlink suggestions for article {$sourceArticle->id}");
    }
} 