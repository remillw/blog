<?php

namespace App\Console\Commands;

use App\Models\UserBacklinkPoints;
use App\Models\BacklinkSuggestion;
use App\Models\Article;
use App\Models\User;
use App\Jobs\ProcessBacklinkSuggestions;
use Illuminate\Console\Command;

class TestBacklinkSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backlink:test {--user-id=1 : ID de l\'utilisateur pour les tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester le système de backlinks et points utilisateur';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        
        $this->info("🔗 Test du système de backlinks pour l'utilisateur {$userId}");
        $this->newLine();

        // 1. Tester la création/récupération des points utilisateur
        $this->info('1. Test des points utilisateur...');
        $userPoints = UserBacklinkPoints::getOrCreateForUser($userId);
        $this->line("   Points disponibles: {$userPoints->available_points}");
        $this->line("   Points utilisés: {$userPoints->used_points}");
        $this->line("   Total gagné: {$userPoints->total_earned}");
        $this->newLine();

        // 2. Afficher les articles existants
        $this->info('2. Articles existants...');
        $articles = Article::with('site')->limit(5)->get();
        if ($articles->isEmpty()) {
            $this->warn('   Aucun article trouvé. Créez quelques articles d\'abord.');
            return;
        }

        foreach ($articles as $article) {
            $siteName = $article->site ? $article->site->name : 'N/A';
            $this->line("   - [{$article->id}] {$article->title} (Site: {$siteName})");
        }
        $this->newLine();

        // 3. Tester l'utilisation de points
        $this->info('3. Test d\'utilisation de points...');
        if ($userPoints->canUsePoints(2)) {
            $this->line('   ✅ L\'utilisateur peut utiliser 2 points');
            
            if ($this->confirm('Déduire 2 points pour test ?')) {
                $userPoints->usePoints(2);
                $this->line("   Points après déduction: {$userPoints->fresh()->available_points}");
            }
        } else {
            $this->line('   ❌ L\'utilisateur n\'a pas assez de points');
        }
        $this->newLine();

        // 4. Tester la recharge hebdomadaire
        $this->info('4. Test de recharge hebdomadaire...');
        if ($this->confirm('Effectuer une recharge de 3 points ?')) {
            $userPoints->weeklyRecharge(3);
            $this->line("   Points après recharge: {$userPoints->fresh()->available_points}");
        }
        $this->newLine();

        // 5. Afficher les suggestions de backlinks existantes
        $this->info('5. Suggestions de backlinks existantes...');
        $suggestions = BacklinkSuggestion::with(['sourceArticle', 'targetArticle'])
            ->limit(5)
            ->get();

        if ($suggestions->isEmpty()) {
            $this->warn('   Aucune suggestion trouvée.');
            
            if ($this->confirm('Lancer l\'analyse nocturne maintenant ?')) {
                $this->info('   Lancement du job d\'analyse...');
                ProcessBacklinkSuggestions::dispatch();
                $this->line('   ✅ Job lancé en arrière-plan');
            }
        } else {
            foreach ($suggestions as $suggestion) {
                $type = $suggestion->is_same_site ? 'Interne' : 'Externe';
                $score = number_format($suggestion->relevance_score * 100, 1);
                $this->line("   - [{$type}] {$suggestion->sourceArticle->title} → {$suggestion->targetArticle->title} ({$score}%)");
            }
        }
        $this->newLine();

        // 6. Statistiques globales
        $this->info('6. Statistiques globales...');
        $totalUsers = UserBacklinkPoints::count();
        $totalSuggestions = BacklinkSuggestion::count();
        $totalUsedSuggestions = BacklinkSuggestion::where('is_used', true)->count();
        $averageScore = BacklinkSuggestion::avg('relevance_score');

        $this->line("   Utilisateurs avec points: {$totalUsers}");
        $this->line("   Suggestions générées: {$totalSuggestions}");
        $this->line("   Suggestions utilisées: {$totalUsedSuggestions}");
        $this->line("   Score moyen: " . number_format($averageScore * 100, 1) . "%");
        $this->newLine();

        $this->info('✅ Test terminé !');
    }
}
