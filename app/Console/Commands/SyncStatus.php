<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\SyncLog;
use Illuminate\Console\Command;

class SyncStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:sync-status 
                           {--saas-url=http://localhost:8000}
                           {--api-key= : Clé API pour filtrer}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Affiche le statut des synchronisations avec le SaaS';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $saasUrl = $this->option('saas-url');
        $apiKey = $this->option('api-key');

        $this->info("📊 Statut des synchronisations SaaS");
        $this->info("🌐 URL: {$saasUrl}");
        $this->newLine();

        // Statistiques générales
        $totalArticles = Article::count();
        $saasArticles = Article::where('source', 'saas_sync')->count();
        $webhookArticles = Article::where('source', 'webhook')->count();

        $this->info("📈 Statistiques des articles:");
        $this->table(['Type', 'Nombre'], [
            ['Total articles', $totalArticles],
            ['Articles SaaS (sync)', $saasArticles],
            ['Articles Webhook', $webhookArticles],
        ]);

        $this->newLine();

        // Historique des synchronisations
        $query = SyncLog::orderBy('last_sync_at', 'desc');
        
        if ($apiKey) {
            $apiKeyHash = hash('sha256', $apiKey);
            $query->where('api_key_hash', $apiKeyHash);
            $this->info("🔑 Filtré par clé API: " . substr($apiKey, 0, 8) . '...');
        }
        
        $query->where('saas_url', $saasUrl);
        
        $syncLogs = $query->take(10)->get();

        if ($syncLogs->isEmpty()) {
            $this->warn("⚠️  Aucune synchronisation trouvée");
            return 0;
        }

        $this->info("📅 Historique des synchronisations (10 dernières):");
        
        $tableData = [];
        foreach ($syncLogs as $log) {
            $tableData[] = [
                'Date' => $log->last_sync_at->format('d/m/Y H:i'),
                'Récupérés' => $log->articles_fetched,
                'Créés' => $log->articles_created,
                'MAJ' => $log->articles_updated,
                'Succès' => $log->sync_success ? '✅' : '❌',
                'Notes' => $log->sync_notes ? substr($log->sync_notes, 0, 30) . '...' : '-',
            ];
        }

        $this->table(['Date', 'Récupérés', 'Créés', 'MAJ', 'Succès', 'Notes'], $tableData);

        // Dernière synchronisation
        $lastSync = $syncLogs->first();
        if ($lastSync) {
            $this->newLine();
            $this->info("🕐 Dernière synchronisation:");
            $this->info("📅 Date: {$lastSync->last_sync_at->format('d/m/Y H:i:s')}");
            $this->info("⏱️  Il y a: {$lastSync->last_sync_at->diffForHumans()}");
            
            if ($lastSync->needsSync(60)) {
                $this->warn("⚠️  Synchronisation recommandée (plus de 60 minutes)");
            } else {
                $nextSync = $lastSync->last_sync_at->addMinutes(60);
                $this->info("⏳ Prochaine sync recommandée: {$nextSync->format('d/m/Y H:i')}");
            }

            // Détails de la dernière sync
            if ($lastSync->sync_data) {
                $this->newLine();
                $this->info("🔧 Paramètres de la dernière sync:");
                foreach ($lastSync->sync_data as $key => $value) {
                    $this->info("  • {$key}: {$value}");
                }
            }
        }

        $this->newLine();
        $this->info("💡 Commandes utiles:");
        $this->info("php artisan saas:sync-articles --api-key=votre-cle");
        $this->info("php artisan saas:sync-articles --api-key=votre-cle --force");
        $this->info("php artisan saas:sync-articles --api-key=votre-cle --dry-run");

        return 0;
    }
}
