<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;

class MarkArticlesAsSynced extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:mark-synced 
                            {--ids= : IDs des articles séparés par des virgules}
                            {--all : Marquer tous les articles comme synchronisés}
                            {--published : Marquer seulement les articles publiés}
                            {--dry-run : Afficher ce qui serait modifié sans modifier}
                            {--force : Forcer sans confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Marque les articles comme synchronisés (is_synced = true)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $ids = $this->option('ids');
        $all = $this->option('all');
        $publishedOnly = $this->option('published');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("🔄 Marquage des articles comme synchronisés");

        // Construire la requête
        $query = Article::query();

        if ($ids) {
            $articleIds = array_map('trim', explode(',', $ids));
            $query->whereIn('id', $articleIds);
            $this->info("📋 Articles spécifiques : " . implode(', ', $articleIds));
        } elseif ($all) {
            $this->info("📋 Tous les articles");
        } elseif ($publishedOnly) {
            $query->where('status', 'published');
            $this->info("📋 Articles publiés uniquement");
        } else {
            $this->error("❌ Vous devez spécifier --ids, --all ou --published");
            return 1;
        }

        $articles = $query->get();
        $count = $articles->count();

        if ($count === 0) {
            $this->info("✅ Aucun article trouvé");
            return 0;
        }

        // Affichage des articles à modifier
        $this->info("\n📋 Articles à marquer comme synchronisés :");
        $this->table(
            ['ID', 'Titre', 'Status', 'Actuellement Synced', 'Source'],
            $articles->map(function ($article) {
                return [
                    $article->id,
                    \Str::limit($article->title, 30),
                    $article->status,
                    $article->is_synced ? '✅ Oui' : '❌ Non',
                    $article->source,
                ];
            })
        );

        if ($dryRun) {
            $this->warn("🔍 Mode dry-run : {$count} articles seraient marqués comme synchronisés");
            return 0;
        }

        // Confirmation
        if (!$force && !$this->confirm("Voulez-vous marquer ces {$count} articles comme synchronisés ?")) {
            $this->info("❌ Opération annulée");
            return 0;
        }

        // Mise à jour
        $updated = $query->update([
            'is_synced' => true,
            'webhook_sent_at' => now(),
        ]);

        $this->info("✅ {$updated} articles marqués comme synchronisés");
        $this->info("📅 Date de synchronisation : " . now()->format('d/m/Y H:i:s'));

        return 0;
    }
}
