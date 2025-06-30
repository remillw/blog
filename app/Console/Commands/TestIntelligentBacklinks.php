<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Site;
use App\Models\UserBacklinkPoints;
use App\Http\Controllers\Api\AIController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestIntelligentBacklinks extends Command
{
    protected $signature = 'backlinks:test-intelligent {--site-id=1 : ID du site} {--prompt="Guide complet du référencement naturel SEO" : Prompt de test}';
    protected $description = 'Tester le système de backlinks intelligents intégrés par l\'IA';

    public function handle()
    {
        $siteId = $this->option('site-id');
        $prompt = $this->option('prompt');

        $this->info("🔗 Test du système de backlinks intelligents");
        $this->info("Site ID: {$siteId}");
        $this->info("Prompt: {$prompt}");
        $this->newLine();

        // 1. Vérifier le site et ses articles
        $site = Site::with('articles')->find($siteId);
        if (!$site) {
            $this->error("Site {$siteId} introuvable");
            return 1;
        }

        $this->info("📍 Site: {$site->name}");
        $this->info("📚 Articles disponibles: {$site->articles->count()}");
        
        if ($site->articles->isNotEmpty()) {
            $this->line("Articles existants:");
            foreach ($site->articles->take(3) as $article) {
                $this->line("  - {$article->title}");
            }
        }
        $this->newLine();

        // 2. Vérifier les points utilisateur
        $userPoints = UserBacklinkPoints::getOrCreateForUser(1); // User admin
        $this->info("💰 Points utilisateur: {$userPoints->available_points} disponibles");
        $this->newLine();

        // 3. Simuler la récupération des suggestions intelligentes
        $this->info("🧠 Analyse sémantique des suggestions...");
        
        try {
            // Créer une instance du controller pour tester
            $controller = new AIController();
            
            // Utiliser la réflexion pour accéder à la méthode privée
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('getBacklinkSuggestionsForPrompt');
            $method->setAccessible(true);
            
            // Simuler l'authentification
            auth()->loginUsingId(1);
            
            $suggestions = $method->invoke($controller, $prompt, $siteId, 'fr');
            
            $this->info("📊 Suggestions trouvées: " . count($suggestions));
            
            if (!empty($suggestions)) {
                $this->line("Suggestions de backlinks:");
                foreach ($suggestions as $suggestion) {
                    $type = $suggestion['is_same_site'] ? '🔗 Interne' : '🌐 Externe';
                    $score = round($suggestion['relevance_score'] * 100);
                    $this->line("  {$type} [{$score}%] {$suggestion['title']}");
                    $this->line("    URL: {$suggestion['slug']}");
                    $this->line("    Raison: {$suggestion['reasoning']}");
                    $this->line("");
                }
            } else {
                $this->warn("Aucune suggestion trouvée. Créez plus d'articles pour de meilleures suggestions.");
            }
            
        } catch (\Exception $e) {
            $this->error("Erreur lors du test: " . $e->getMessage());
            return 1;
        }

        $this->newLine();
        $this->info("✅ Test terminé avec succès!");
        $this->line("");
        $this->line("💡 Pour voir le système en action:");
        $this->line("  1. Allez dans ArticleForm.vue");
        $this->line("  2. Utilisez le prompt: '{$prompt}'");
        $this->line("  3. L'IA intégrera automatiquement les backlinks pertinents");
        
        return 0;
    }
} 