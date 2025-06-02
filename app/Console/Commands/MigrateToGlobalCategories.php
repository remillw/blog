<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\GlobalCategory;
use App\Models\CategorySuggestion;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateToGlobalCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:migrate-to-global 
                            {--dry-run : Afficher ce qui serait fait sans l\'exécuter}
                            {--auto-approve : Approuver automatiquement les suggestions à faible similarité}
                            {--threshold=0.70 : Seuil de similarité pour les suggestions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrer les catégories existantes vers le système de catégories globales avec IA anti-doublons';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $autoApprove = $this->option('auto-approve');
        $threshold = (float) $this->option('threshold');

        $this->info('🔄 Début de la migration vers les catégories globales');
        $this->newLine();

        if ($dryRun) {
            $this->warn('🔍 Mode DRY-RUN : aucune modification ne sera apportée');
            $this->newLine();
        }

        // 1. Analyser les catégories existantes
        $this->info('1. Analyse des catégories existantes...');
        $categories = Category::with('sites')->get();
        
        if ($categories->isEmpty()) {
            $this->warn('   Aucune catégorie existante trouvée.');
            return;
        }

        $this->line("   Trouvées: {$categories->count()} catégories");
        $this->newLine();

        // 2. Grouper par similarité et langue
        $this->info('2. Groupement par similarité et langue...');
        $grouped = $this->groupCategoriesByLanguageAndSimilarity($categories, $threshold);
        
        $this->line("   Groupes créés: " . count($grouped));
        $this->newLine();

        // 3. Traiter chaque groupe
        $this->info('3. Traitement des groupes...');
        $stats = [
            'created' => 0,
            'merged' => 0,
            'suggestions' => 0,
            'errors' => 0,
        ];

        foreach ($grouped as $groupKey => $group) {
            try {
                $result = $this->processGroup($group, $dryRun, $autoApprove, $threshold);
                
                $stats['created'] += $result['created'];
                $stats['merged'] += $result['merged'];
                $stats['suggestions'] += $result['suggestions'];
                
                $this->line("   ✅ Groupe '{$groupKey}': {$result['created']} créées, {$result['merged']} fusionnées, {$result['suggestions']} suggestions");
                
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->error("   ❌ Erreur pour le groupe '{$groupKey}': {$e->getMessage()}");
            }
        }

        $this->newLine();

        // 4. Lier les catégories aux sites
        if (!$dryRun) {
            $this->info('4. Liaison des catégories aux sites...');
            $linkedCount = $this->linkCategoriesToSites();
            $this->line("   Liaisons créées: {$linkedCount}");
        }

        // 5. Résumé final
        $this->newLine();
        $this->info('📊 Résumé de la migration:');
        $this->line("   Catégories globales créées: {$stats['created']}");
        $this->line("   Catégories fusionnées: {$stats['merged']}");
        $this->line("   Suggestions en attente: {$stats['suggestions']}");
        $this->line("   Erreurs: {$stats['errors']}");

        if ($dryRun) {
            $this->newLine();
            $this->warn('🔍 C\'était un dry-run. Pour exécuter réellement, lancez sans --dry-run');
        } else {
            $this->newLine();
            $this->info('✅ Migration terminée avec succès !');
        }
    }

    /**
     * Grouper les catégories par langue et similarité
     */
    private function groupCategoriesByLanguageAndSimilarity($categories, float $threshold): array
    {
        $grouped = [];

        foreach ($categories as $category) {
            $languageCode = $category->language_code ?? 'fr';
            $name = trim($category->name);
            
            // Chercher un groupe existant similaire
            $foundGroup = null;
            foreach ($grouped as $key => $group) {
                if (str_contains($key, $languageCode)) {
                    $similarity = $this->calculateSimilarity($name, $group[0]->name);
                    if ($similarity >= $threshold) {
                        $foundGroup = $key;
                        break;
                    }
                }
            }

            if ($foundGroup) {
                $grouped[$foundGroup][] = $category;
            } else {
                $groupKey = $languageCode . '_' . Str::slug($name);
                $grouped[$groupKey] = [$category];
            }
        }

        return $grouped;
    }

    /**
     * Traiter un groupe de catégories similaires
     */
    private function processGroup(array $group, bool $dryRun, bool $autoApprove, float $threshold): array
    {
        $stats = ['created' => 0, 'merged' => 0, 'suggestions' => 0];

        // Prendre la catégorie la plus utilisée comme référence
        $primaryCategory = collect($group)->sortByDesc(function ($cat) {
            return $cat->articles()->count() + $cat->sites()->count();
        })->first();

        $languageCode = $primaryCategory->language_code ?? 'fr';
        $name = $primaryCategory->name;

        if ($dryRun) {
            $this->line("     [DRY-RUN] Créerait catégorie globale: '{$name}' ({$languageCode})");
            $stats['created'] = 1;
            return $stats;
        }

        // Vérifier si une catégorie globale similaire existe déjà
        $existingGlobal = GlobalCategory::searchByName($name, $languageCode, 1)->first();

        if ($existingGlobal) {
            $similarity = $this->calculateSimilarity($name, $existingGlobal->getTranslatedName($languageCode));
            
            if ($similarity >= $threshold) {
                // Fusionner avec l'existante
                $existingGlobal->setTranslation($languageCode, $name);
                $existingGlobal->increment('usage_count', count($group));
                
                $stats['merged'] = 1;
                $this->line("     🔗 Fusionné avec catégorie existante: {$existingGlobal->name}");
                
                // Mapper vers la catégorie existante
                $this->mapCategoriesToGlobal($group, $existingGlobal);
                
                return $stats;
            }
        }

        // Créer une nouvelle catégorie globale
        $globalCategory = GlobalCategory::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'translations' => [$languageCode => $name],
            'usage_count' => count($group),
            'created_by' => 1, // Admin par défaut
            'is_approved' => true,
        ]);

        $stats['created'] = 1;
        $this->line("     ✨ Créée: {$globalCategory->name} (ID: {$globalCategory->id})");

        // Mapper les catégories locales vers la globale
        $this->mapCategoriesToGlobal($group, $globalCategory);

        return $stats;
    }

    /**
     * Mapper les catégories locales vers une catégorie globale
     */
    private function mapCategoriesToGlobal(array $localCategories, GlobalCategory $globalCategory): void
    {
        foreach ($localCategories as $localCategory) {
            // Stocker la correspondance pour référence future
            $localCategory->update([
                'global_category_id' => $globalCategory->id,
                'migrated_at' => now(),
            ]);
        }
    }

    /**
     * Lier les catégories globales aux sites
     */
    private function linkCategoriesToSites(): int
    {
        $linkedCount = 0;

        $categories = Category::whereNotNull('global_category_id')->with(['sites'])->get();

        foreach ($categories as $category) {
            foreach ($category->sites as $site) {
                try {
                    $site->globalCategories()->syncWithoutDetaching([
                        $category->global_category_id => [
                            'language_code' => $category->language_code ?? 'fr',
                            'custom_name' => null,
                            'is_active' => true,
                            'sort_order' => 0,
                        ]
                    ]);
                    
                    $linkedCount++;
                } catch (\Exception $e) {
                    $this->warn("     ⚠️ Erreur liaison site {$site->id} -> catégorie {$category->global_category_id}: {$e->getMessage()}");
                }
            }
        }

        return $linkedCount;
    }

    /**
     * Calculer la similarité entre deux chaînes
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));

        if ($str1 === $str2) {
            return 1.0;
        }

        // Utiliser similar_text pour une similarité approximative
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }
}
