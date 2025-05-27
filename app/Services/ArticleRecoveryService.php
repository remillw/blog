<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ArticleRecoveryService
{
    private ?string $saasUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->saasUrl = Setting::get('saas_url', config('services.saas.url')) ?? 'http://localhost';
        $this->apiKey = Setting::get('saas_api_key', config('services.saas.api_key')) ?? 'test-key';
    }

    /**
     * Récupère un article spécifique depuis le SaaS par son external_id
     */
    public function recoverArticleByExternalId(string $externalId): ?Article
    {
        try {
            Log::info("🔄 Tentative de récupération de l'article", ['external_id' => $externalId]);

            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->saasUrl}/api/articles", [
                'external_id' => $externalId,
                'per_page' => 1
            ]);

            if (!$response->successful()) {
                Log::error("❌ Erreur API lors de la récupération", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

            $data = $response->json();
            
            if (empty($data['data'])) {
                Log::warning("⚠️ Article non trouvé sur le SaaS", ['external_id' => $externalId]);
                return null;
            }

            $articleData = $data['data'][0];
            
            return $this->createArticleFromSaasData($articleData);

        } catch (\Exception $e) {
            Log::error("❌ Exception lors de la récupération d'article", [
                'external_id' => $externalId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Récupère un article par son ID local (pour réessayer après suppression)
     */
    public function recoverArticleById(int $articleId): ?Article
    {
        try {
            // Chercher dans les logs de suppression
            $cleanupLog = DB::table('sync_logs')
                ->where('api_key_hash', 'cleanup')
                ->whereRaw("JSON_EXTRACT(sync_data, '$.action') = 'cleanup'")
                ->whereRaw("JSON_CONTAINS(JSON_EXTRACT(sync_data, '$.deleted_ids'), ?)", [json_encode($articleId)])
                ->latest('last_sync_at')
                ->first();

            if (!$cleanupLog) {
                Log::warning("⚠️ Aucun log de suppression trouvé", ['article_id' => $articleId]);
                return null;
            }

            $syncData = json_decode($cleanupLog->sync_data, true);
            $deletedIds = $syncData['deleted_ids'] ?? [];
            $deletedTitles = $syncData['deleted_titles'] ?? [];
            
            $index = array_search($articleId, $deletedIds);
            if ($index === false) {
                return null;
            }

            $title = $deletedTitles[$index] ?? null;
            
            if (!$title) {
                return null;
            }

            // Rechercher par titre sur le SaaS
            return $this->recoverArticleByTitle($title);

        } catch (\Exception $e) {
            Log::error("❌ Exception lors de la récupération par ID", [
                'article_id' => $articleId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Récupère un article par son titre
     */
    public function recoverArticleByTitle(string $title): ?Article
    {
        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->saasUrl}/api/articles", [
                'search' => $title,
                'per_page' => 5
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            
            // Chercher une correspondance exacte
            foreach ($data['data'] as $articleData) {
                if (strtolower($articleData['title']) === strtolower($title)) {
                    return $this->createArticleFromSaasData($articleData);
                }
            }

            // Si pas de correspondance exacte, prendre le premier résultat
            if (!empty($data['data'])) {
                return $this->createArticleFromSaasData($data['data'][0]);
            }

            return null;

        } catch (\Exception $e) {
            Log::error("❌ Exception lors de la récupération par titre", [
                'title' => $title,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Crée un article local à partir des données du SaaS
     */
    private function createArticleFromSaasData(array $articleData): Article
    {
        return DB::transaction(function () use ($articleData) {
            
            // Synchroniser les catégories d'abord
            $categoryIds = $this->syncCategories($articleData['categories'] ?? []);

            // Créer l'article
            $article = Article::create([
                'title' => $articleData['title'],
                'slug' => $articleData['slug'],
                'content' => $articleData['content'],
                'content_html' => $articleData['content_html'] ?? '',
                'excerpt' => $articleData['excerpt'] ?? '',
                'status' => $articleData['status'] ?? 'draft',
                'featured_image' => $articleData['featured_image'] ?? null,
                'meta_title' => $articleData['meta_title'] ?? null,
                'meta_description' => $articleData['meta_description'] ?? null,
                'published_at' => $articleData['published_at'] ? now()->parse($articleData['published_at']) : null,
                'site_id' => $this->getDefaultSiteId(),
                'user_id' => auth()->id() ?? 1,
                'source' => 'recovered',
                'external_id' => $articleData['external_id'] ?? null,
                'webhook_received_at' => now(),
                'is_synced' => true,
            ]);

            // Attacher les catégories
            if (!empty($categoryIds)) {
                $article->categories()->attach($categoryIds);
            }

            Log::info("✅ Article récupéré avec succès", [
                'article_id' => $article->id,
                'title' => $article->title,
                'external_id' => $article->external_id
            ]);

            return $article;
        });
    }

    /**
     * Synchronise les catégories depuis le SaaS
     */
    private function syncCategories(array $categoriesData): array
    {
        $categoryIds = [];

        foreach ($categoriesData as $categoryData) {
            $category = Category::firstOrCreate(
                ['slug' => $categoryData['slug']],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'] ?? null,
                ]
            );
            $categoryIds[] = $category->id;
        }

        return $categoryIds;
    }

    /**
     * Récupère l'ID du site par défaut
     */
    private function getDefaultSiteId(): ?int
    {
        return DB::table('sites')->where('is_default', true)->value('id') 
            ?? DB::table('sites')->first()?->id;
    }

    /**
     * Vérifie si le service est configuré
     */
    public function isConfigured(): bool
    {
        return !empty($this->saasUrl) && !empty($this->apiKey);
    }
} 