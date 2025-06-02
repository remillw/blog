<?php

namespace App\Console\Commands;

use App\Models\GlobalCategory;
use App\Models\CategorySuggestion;
use App\Models\Site;
use App\Models\User;
use Illuminate\Console\Command;

class TestGlobalCategorySystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:test-global-system {--user-id=1 : ID de l\'utilisateur pour les tests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester le système complet des catégories globales avec IA anti-doublons';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        
        $this->info('🧪 Test du système de catégories globales');
        $this->newLine();

        // 1. Vérifier les catégories de base
        $this->info('1. Vérification des catégories de base...');
        $totalCategories = GlobalCategory::count();
        $rootCategories = GlobalCategory::roots()->count();
        $withChildren = GlobalCategory::whereHas('children')->count();
        
        $this->line("   Total: {$totalCategories} catégories");
        $this->line("   Racines: {$rootCategories}");
        $this->line("   Avec enfants: {$withChildren}");
        $this->newLine();

        // 2. Test des traductions
        $this->info('2. Test des traductions...');
        $techCategory = GlobalCategory::where('name', 'Technologie')->first();
        if ($techCategory) {
            $this->line("   FR: " . $techCategory->getTranslatedName('fr'));
            $this->line("   EN: " . $techCategory->getTranslatedName('en'));
            $this->line("   ES: " . $techCategory->getTranslatedName('es'));
            $this->line("   DE: " . $techCategory->getTranslatedName('de'));
            $this->line("   IT: " . $techCategory->getTranslatedName('it'));
        }
        $this->newLine();

        // 3. Test de la recherche
        $this->info('3. Test de recherche par nom...');
        $searchResults = GlobalCategory::searchByName('tech', 'fr', 5);
        $this->line("   Résultats pour 'tech': {$searchResults->count()}");
        foreach ($searchResults as $result) {
            $this->line("     - {$result->name} (usage: {$result->usage_count})");
        }
        $this->newLine();

        // 4. Test de l'IA anti-doublons avec différents cas
        $this->info('4. Test de l\'IA anti-doublons...');
        
        $testCases = [
            'Technologie Moderne' => 'Très similaire à Technologie',
            'Intelligence Artificielle Avancée' => 'Similaire à IA existante',
            'Blockchain' => 'Nouveau concept, pas de similarité',
            'Développement Logiciel' => 'Proche de Développement Web',
            'Cuisine Française' => 'Proche de Cuisine existante',
        ];

        foreach ($testCases as $testName => $description) {
            $this->line("   Test: {$testName} ({$description})");
            
            try {
                $suggestion = CategorySuggestion::createWithAI($testName, 'fr', $userId);
                $score = $suggestion->similarity_score ?? 0;
                $status = $score >= 0.70 ? '🚨 BLOQUÉE' : '✅ AUTORISÉE';
                
                $this->line("     {$status} - Similarité: " . number_format($score * 100, 1) . "%");
                
                if ($suggestion->similarCategory) {
                    $this->line("     Similaire à: {$suggestion->similarCategory->name}");
                }
                
                if ($suggestion->ai_reasoning) {
                    $this->line("     IA: " . substr($suggestion->ai_reasoning, 0, 80) . "...");
                }
                
            } catch (\Exception $e) {
                $this->error("     ❌ Erreur: {$e->getMessage()}");
            }
            
            $this->newLine();
        }

        // 5. Test de hiérarchie et arborescence
        $this->info('5. Test de l\'arborescence...');
        $tree = GlobalCategory::getTree('fr');
        $this->line("   Arborescence générée: " . count($tree) . " catégories racines");
        
        foreach (array_slice($tree, 0, 2) as $rootCategory) {
            $childrenCount = count($rootCategory['children']);
            $this->line("   📁 {$rootCategory['name']} ({$childrenCount} enfants)");
            foreach (array_slice($rootCategory['children'], 0, 2) as $child) {
                $this->line("     └─ {$child['name']}");
            }
        }
        $this->newLine();

        // 6. Test de liaison avec un site (si disponible)
        $this->info('6. Test de liaison avec un site...');
        $site = Site::first();
        if ($site) {
            $this->line("   Site trouvé: {$site->name}");
            
            // Lier quelques catégories
            $categoriesToLink = GlobalCategory::roots()->limit(2)->pluck('id')->toArray();
            
            foreach ($categoriesToLink as $categoryId) {
                $site->globalCategories()->syncWithoutDetaching([
                    $categoryId => [
                        'language_code' => 'fr',
                        'is_active' => true,
                        'sort_order' => 0,
                    ]
                ]);
            }
            
            $linkedCount = $site->globalCategories()->count();
            $this->line("   Catégories liées: {$linkedCount}");
            
            // Afficher les catégories liées
            $linkedCategories = $site->getCategoriesForLanguage('fr');
            foreach ($linkedCategories as $linked) {
                $this->line("     🔗 {$linked->name}");
            }
            
        } else {
            $this->warn("   Aucun site disponible pour le test");
        }
        $this->newLine();

        // 7. Statistiques finales
        $this->info('7. Statistiques finales...');
        $pendingSuggestions = CategorySuggestion::pending()->count();
        $highSimilarity = CategorySuggestion::highSimilarity(0.70)->count();
        $totalSuggestions = CategorySuggestion::count();
        $approvedSuggestions = CategorySuggestion::where('status', 'approved')->count();
        
        $this->line("   Suggestions totales: {$totalSuggestions}");
        $this->line("   En attente: {$pendingSuggestions}");
        $this->line("   Forte similarité: {$highSimilarity}");
        $this->line("   Approuvées: {$approvedSuggestions}");
        $this->newLine();

        // 8. Recommandations
        $this->info('🎯 Recommandations pour la suite:');
        $this->line('   • Intégrer l\'interface dans ArticleForm.vue');
        $this->line('   • Ajouter un dashboard admin pour gérer les suggestions');
        $this->line('   • Créer des webhooks pour notifier les changements');
        $this->line('   • Implémenter la migration automatique des catégories existantes');
        $this->newLine();

        $this->info('✅ Test du système terminé avec succès !');
        $this->line('🔗 API disponible sur: /api/global-categories/*');
    }
}
