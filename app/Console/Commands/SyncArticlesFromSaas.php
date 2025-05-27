<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Category;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncArticlesFromSaas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:sync-articles 
                           {--saas-url=http://localhost:8000} 
                           {--endpoint=/api/articles}
                           {--api-key= : Clé API pour authentification}
                           {--force : Forcer la synchronisation même si pas nécessaire}
                           {--interval=60 : Intervalle en minutes entre les syncs}
                           {--dry-run : Simuler sans insérer en base}
                           {--status= : Filtrer par status}
                           {--per-page=50 : Nombre d\'articles par page}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise intelligemment les articles depuis le SaaS (seulement les nouveaux/modifiés)';

    private int $articlesCreated = 0;
    private int $articlesUpdated = 0;
    private int $articlesFetched = 0;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $saasUrl = $this->option('saas-url');
        $endpoint = $this->option('endpoint');
        $apiKey = $this->option('api-key');
        $force = $this->option('force');
        $interval = (int) $this->option('interval');
        $dryRun = $this->option('dry-run');
        $status = $this->option('status');
        $perPage = (int) $this->option('per-page');

        if (!$apiKey) {
            $this->error("❌ Clé API requise. Utilisez --api-key=votre-cle");
            return 1;
        }

        $this->info("🔄 Synchronisation intelligente des articles");
        $this->info("🌐 SaaS: {$saasUrl}");
        $this->info("🔑 API Key: " . substr($apiKey, 0, 8) . '...');
        
        if ($dryRun) {
            $this->warn("🧪 Mode DRY-RUN activé - Aucune modification en base");
        }
        
        $this->newLine();

        try {
            // Vérifier le dernier sync
            $lastSync = SyncLog::getLastSync($saasUrl, $apiKey);
            
            if ($lastSync && !$force) {
                if (!$lastSync->needsSync($interval)) {
                    $nextSync = $lastSync->last_sync_at->addMinutes($interval);
                    $this->info("⏰ Synchronisation pas nécessaire");
                    $this->info("📅 Dernière sync: {$lastSync->last_sync_at->format('d/m/Y H:i')}");
                    $this->info("⏳ Prochaine sync: {$nextSync->format('d/m/Y H:i')}");
                    $this->info("💡 Utilisez --force pour forcer la synchronisation");
                    return 0;
                }
            }

            // Créer un nouveau log de sync
            if (!$dryRun) {
                $syncLog = SyncLog::createSyncLog($saasUrl, $apiKey, [
                    'status' => $status,
                    'per_page' => $perPage,
                    'interval' => $interval,
                ], 'Synchronisation automatique');
            }

            // Préparer les paramètres de requête
            $queryParams = [
                'per_page' => $perPage,
            ];
            
            if ($status) {
                $queryParams['status'] = $status;
            }

            // Ajouter le paramètre "since" si on a un dernier sync
            if ($lastSync && $lastSync->last_sync_at) {
                $queryParams['since'] = $lastSync->getLastModifiedForApi();
                $this->info("📅 Récupération des articles modifiés depuis: {$lastSync->last_sync_at->format('d/m/Y H:i')}");
            } else {
                $this->info("🆕 Première synchronisation - Récupération de tous les articles");
            }

            $this->newLine();
            $this->info("📡 Récupération des articles...");

            // Faire la requête à l'API
            $response = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-API-Key' => $apiKey,
                ])
                ->get($saasUrl . $endpoint, $queryParams);

            if (!$response->successful()) {
                $this->error("❌ Erreur API: " . $response->status());
                if (isset($syncLog)) {
                    $syncLog->updateStats(0, 0, 0, false);
                }
                return 1;
            }

            $data = $response->json();
            
            if (!isset($data['success']) || !$data['success']) {
                $this->error("❌ Erreur: " . ($data['message'] ?? 'Erreur inconnue'));
                if (isset($syncLog)) {
                    $syncLog->updateStats(0, 0, 0, false);
                }
                return 1;
            }

            $articles = $data['articles'] ?? [];
            $this->articlesFetched = count($articles);

            $this->info("📄 Articles récupérés: {$this->articlesFetched}");

            if (empty($articles)) {
                $this->info("✅ Aucun nouvel article à synchroniser");
                if (isset($syncLog)) {
                    $syncLog->updateStats(0, 0, 0, true);
                }
                return 0;
            }

            // Traiter les articles
            $this->newLine();
            $this->info("🔄 Traitement des articles...");
            
            $progressBar = $this->output->createProgressBar(count($articles));
            $progressBar->start();

            foreach ($articles as $articleData) {
                if (!$dryRun) {
                    $this->processArticle($articleData);
                } else {
                    // En mode dry-run, juste simuler
                    $existingArticle = Article::where('external_id', $articleData['external_id'])->first();
                    if ($existingArticle) {
                        $this->articlesUpdated++;
                    } else {
                        $this->articlesCreated++;
                    }
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // Afficher les résultats
            $this->newLine();
            $this->info("📊 Résultats de la synchronisation:");
            $this->table(['Métrique', 'Valeur'], [
                ['Articles récupérés', $this->articlesFetched],
                ['Articles créés', $this->articlesCreated],
                ['Articles mis à jour', $this->articlesUpdated],
                ['Mode', $dryRun ? 'DRY-RUN' : 'RÉEL'],
            ]);

            // Mettre à jour le log de sync
            if (isset($syncLog) && !$dryRun) {
                $syncLog->updateStats(
                    $this->articlesFetched,
                    $this->articlesCreated,
                    $this->articlesUpdated,
                    true
                );
            }

            $this->newLine();
            $this->info("✅ Synchronisation terminée avec succès !");

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Erreur lors de la synchronisation:");
            $this->error($e->getMessage());
            
            if (isset($syncLog) && !$dryRun) {
                $syncLog->updateStats(0, 0, 0, false);
            }
            
            return 1;
        }
    }

    private function processArticle(array $articleData): void
    {
        try {
            DB::transaction(function () use ($articleData) {
                $externalId = $articleData['external_id'];
                
                // Vérifier que l'external_id existe
                if (empty($externalId)) {
                    throw new \Exception("External ID manquant pour l'article: " . ($articleData['title'] ?? 'Sans titre'));
                }
                
                // Chercher l'article existant
                $article = Article::where('external_id', $externalId)->first();

                // Gérer le contenu null
                $content = $articleData['content'];
                if (empty($content)) {
                    $content = $articleData['excerpt'] ?? 'Contenu non disponible';
                }

                $data = [
                    'title' => $articleData['title'] ?? 'Sans titre',
                    'content' => $content,
                    'excerpt' => $articleData['excerpt'] ?? null,
                    'featured_image_url' => $articleData['featured_image_url'] ?? null,
                    'meta_title' => $articleData['meta_title'] ?? null,
                    'meta_description' => $articleData['meta_description'] ?? null,
                    'status' => $articleData['status'] ?? 'draft',
                    'author_name' => $articleData['author_name'] ?? null,
                    'author_bio' => $articleData['author_bio'] ?? null,
                    'external_id' => $externalId,
                    'source' => 'saas_sync',
                    'webhook_received_at' => now(),
                    'webhook_data' => $articleData,
                    'is_synced' => true,
                ];

                // Gérer la date de publication
                if (isset($articleData['published_at'])) {
                    $data['published_at'] = $articleData['published_at'];
                }

                if ($article) {
                    // Mise à jour
                    $article->update($data);
                    $this->articlesUpdated++;
                } else {
                    // Création
                    $article = Article::create($data);
                    $this->articlesCreated++;
                }

                // Gérer les catégories
                if (isset($articleData['categories']) && is_array($articleData['categories'])) {
                    $this->syncCategories($article, $articleData['categories']);
                }
            });

        } catch (\Exception $e) {
            $externalId = $articleData['external_id'] ?? 'ID manquant';
            $this->warn("⚠️  Erreur lors du traitement de l'article {$externalId}: " . $e->getMessage());
        }
    }

    private function syncCategories(Article $article, array $categoryNames): void
    {
        $categoryIds = [];

        foreach ($categoryNames as $categoryName) {
            if (empty($categoryName)) continue;

            $category = Category::firstOrCreate(
                ['name' => $categoryName],
                ['slug' => Str::slug($categoryName)]
            );

            $categoryIds[] = $category->id;
        }

        $article->categories()->sync($categoryIds);
    }
}
