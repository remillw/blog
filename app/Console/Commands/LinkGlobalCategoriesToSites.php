<?php

namespace App\Console\Commands;

use App\Models\GlobalCategory;
use App\Models\Site;
use Illuminate\Console\Command;

class LinkGlobalCategoriesToSites extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:link-global-to-sites {--site-id= : ID du site spécifique} {--language=fr : Code de langue} {--all : Lier toutes les catégories populaires}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lier les catégories globales aux sites pour permettre leur utilisation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $siteId = $this->option('site-id');
        $language = $this->option('language');
        $linkAll = $this->option('all');

        $this->info('🔗 Liaison des catégories globales aux sites');
        $this->newLine();

        if ($siteId) {
            $this->linkCategoriesToSpecificSite($siteId, $language, $linkAll);
        } else {
            $this->linkCategoriesToAllSites($language, $linkAll);
        }

        $this->newLine();
        $this->info('✅ Opération terminée avec succès !');
    }

    private function linkCategoriesToSpecificSite(int $siteId, string $language, bool $linkAll)
    {
        $site = Site::find($siteId);
        if (!$site) {
            $this->error("❌ Site avec l'ID {$siteId} introuvable");
            return;
        }

        $this->info("🏢 Traitement du site: {$site->name}");
        
        if ($linkAll) {
            $categories = GlobalCategory::approved()
                ->popular(2) // Catégories avec au moins 2 utilisations
                ->limit(20)
                ->get();
        } else {
            $categories = $this->selectCategoriesInteractively();
        }

        $this->linkCategoriesToSite($site, $categories, $language);
    }

    private function linkCategoriesToAllSites(string $language, bool $linkAll)
    {
        $sites = Site::where('is_active', true)->get();
        
        $this->info("🏢 Traitement de {$sites->count()} sites actifs");
        
        foreach ($sites as $site) {
            $this->line("  📍 Site: {$site->name}");
            
            if ($linkAll) {
                // Lier automatiquement les 10 catégories les plus populaires
                $categories = GlobalCategory::approved()
                    ->popular(3)
                    ->limit(10)
                    ->get();
            } else {
                // Lier quelques catégories de base essentielles
                $categories = GlobalCategory::approved()
                    ->whereIn('name', [
                        'Technologie', 'Business', 'Marketing', 'Design', 'Développement',
                        'Santé', 'Finance', 'Éducation', 'Lifestyle', 'Actualités'
                    ])
                    ->limit(5)
                    ->get();
            }

            $this->linkCategoriesToSite($site, $categories, $language);
        }
    }

    private function selectCategoriesInteractively()
    {
        $this->info('📋 Sélection interactive des catégories');
        
        $rootCategories = GlobalCategory::roots()->limit(15)->get();
        $choices = $rootCategories->pluck('name', 'id')->toArray();
        
        $selectedIds = [];
        
        foreach ($choices as $id => $name) {
            if ($this->confirm("Lier la catégorie '{$name}' ?", false)) {
                $selectedIds[] = $id;
            }
        }

        return GlobalCategory::whereIn('id', $selectedIds)->get();
    }

    private function linkCategoriesToSite(Site $site, $categories, string $language)
    {
        $linkedCount = 0;
        
        foreach ($categories as $category) {
            try {
                // Vérifier si déjà liée
                $exists = $site->globalCategories()
                    ->where('global_category_id', $category->id)
                    ->where('language_code', $language)
                    ->exists();

                if (!$exists) {
                    $site->globalCategories()->attach($category->id, [
                        'language_code' => $language,
                        'is_active' => true,
                        'sort_order' => $linkedCount,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $linkedCount++;
                    $this->line("    ✅ Liée: {$category->name}");
                } else {
                    $this->line("    ⏭️  Déjà liée: {$category->name}");
                }

            } catch (\Exception $e) {
                $this->error("    ❌ Erreur liaison {$category->name}: {$e->getMessage()}");
            }
        }

        $this->info("  📊 Total liées: {$linkedCount} catégories pour la langue '{$language}'");
    }
}
