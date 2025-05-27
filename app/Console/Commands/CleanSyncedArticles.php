<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\SyncLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanSyncedArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:clean-synced 
                            {--days=7 : Articles synchronisés depuis X jours}
                            {--dry-run : Afficher ce qui serait supprimé sans supprimer}
                            {--force : Forcer la suppression sans confirmation}
                            {--keep-recent=24 : Garder les articles modifiés dans les X dernières heures}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supprime les articles synchronisés pour économiser l\'espace disque';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $keepRecentHours = (int) $this->option('keep-recent');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🧹 Nettoyage des articles synchronisés");
        $this->info("📅 Articles synchronisés depuis plus de {$days} jours");
        $this->info("⏰ Garder les articles modifiés dans les {$keepRecentHours} dernières heures");

        // Critères de suppression
        $cutoffDate = now()->subDays($days);
        $recentCutoff = now()->subHours($keepRecentHours);

        // Articles éligibles à la suppression
        $query = Article::where('is_synced', true)
            ->where('updated_at', '<=', $recentCutoff)
            ->where('updated_at', '<=', $cutoffDate); // Articles pas modifiés depuis X jours

        $articlesToDelete = $query->get();
        $count = $articlesToDelete->count();

        if ($count === 0) {
            $this->info("✅ Aucun article à supprimer");
            return 0;
        }

        // Affichage des articles à supprimer
        $this->info("\n📋 Articles éligibles à la suppression :");
        $this->table(
            ['ID', 'Titre', 'Source', 'Site', 'Dernière modification', 'Synced'],
            $articlesToDelete->map(function ($article) {
                $sourceIcon = match($article->source) {
                    'webhook' => '📥 Webhook',
                    'saas_sync' => '🔄 Sync SaaS',
                    'created' => '📤 Créé',
                    'recovered' => '🔄 Récupéré',
                    default => '❓ ' . $article->source
                };
                
                return [
                    $article->id,
                    \Str::limit($article->title, 30),
                    $sourceIcon,
                    $article->site->name ?? 'N/A',
                    $article->updated_at->format('d/m/Y H:i'),
                    $article->is_synced ? '✅' : '❌',
                ];
            })
        );

        if ($dryRun) {
            $this->warn("🔍 Mode dry-run : {$count} articles seraient supprimés");
            return 0;
        }

        // Confirmation
        if (!$force && !$this->confirm("Voulez-vous supprimer ces {$count} articles ?")) {
            $this->info("❌ Suppression annulée");
            return 0;
        }

        // Suppression avec transaction
        DB::beginTransaction();
        try {
            $deletedIds = $articlesToDelete->pluck('id')->toArray();
            $deletedTitles = $articlesToDelete->pluck('title')->toArray();

            // Supprimer les relations many-to-many d'abord
            foreach ($articlesToDelete as $article) {
                $article->categories()->detach();
            }

            // Supprimer les articles
            Article::whereIn('id', $deletedIds)->delete();

            // Log de la suppression
            $this->logCleanup($deletedIds, $deletedTitles);

            DB::commit();

            $this->info("✅ {$count} articles supprimés avec succès");
            $this->info("💾 Espace disque libéré !");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Erreur lors de la suppression : " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function logCleanup(array $deletedIds, array $deletedTitles): void
    {
        // Créer un log de nettoyage
        SyncLog::create([
            'saas_url' => config('app.url'),
            'api_key_hash' => 'cleanup',
            'last_sync_at' => now(),
            'articles_fetched' => count($deletedIds),
            'articles_created' => 0,
            'articles_updated' => 0,
            'sync_data' => json_encode([
                'action' => 'cleanup',
                'deleted_ids' => $deletedIds,
                'deleted_titles' => $deletedTitles,
                'cleanup_reason' => 'Automatic cleanup of synced articles'
            ]),
            'sync_notes' => 'Automatic cleanup of ' . count($deletedIds) . ' synced articles',
            'sync_success' => true,
        ]);
    }
}
