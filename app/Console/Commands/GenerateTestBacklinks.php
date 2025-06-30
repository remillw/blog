<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\BacklinkSuggestion;
use Illuminate\Console\Command;

class GenerateTestBacklinks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backlinks:generate-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Générer des suggestions de backlinks de test pour remplir la table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔗 Génération des suggestions de backlinks de test...');

        // Supprimer les anciennes suggestions
        BacklinkSuggestion::truncate();
        $this->line('   Table backlink_suggestions vidée');

        // Récupérer les articles existants
        $articles = Article::with('site')->get();
        $this->line("   Articles trouvés: {$articles->count()}");

        if ($articles->count() < 2) {
            $this->error('Pas assez d\'articles pour créer des suggestions. Créez au moins 2 articles.');
            return 1;
        }

        $suggestionsCreated = 0;

        // Créer des suggestions entre les articles
        foreach ($articles as $sourceArticle) {
            foreach ($articles as $targetArticle) {
                if ($sourceArticle->id !== $targetArticle->id) {
                    // Calculer un score basique basé sur la similarité des titres et contenus
                    $score = $this->calculateSimilarityScore($sourceArticle, $targetArticle);
                    
                    if ($score >= 0.4) { // Seuil minimum
                        BacklinkSuggestion::create([
                            'source_article_id' => $sourceArticle->id,
                            'target_article_id' => $targetArticle->id,
                            'relevance_score' => $score,
                            'anchor_suggestion' => $this->generateAnchor($targetArticle->title),
                            'reasoning' => $this->generateReasoning($sourceArticle, $targetArticle, $score),
                            'is_same_site' => $sourceArticle->site_id === $targetArticle->site_id,
                        ]);
                        
                        $suggestionsCreated++;
                    }
                }
            }
        }

        $this->info("✅ {$suggestionsCreated} suggestions de backlinks créées !");
        
        // Afficher un résumé
        $internalCount = BacklinkSuggestion::where('is_same_site', true)->count();
        $externalCount = BacklinkSuggestion::where('is_same_site', false)->count();
        $highQualityCount = BacklinkSuggestion::where('relevance_score', '>=', 0.75)->count();
        
        $this->line("   Liens internes: {$internalCount}");
        $this->line("   Liens externes: {$externalCount}");
        $this->line("   Haute qualité (≥75%): {$highQualityCount}");

        return 0;
    }

    private function calculateSimilarityScore(Article $source, Article $target): float
    {
        $score = 0;
        
        // Similarité des titres
        $sourceTitleWords = $this->extractWords($source->title);
        $targetTitleWords = $this->extractWords($target->title);
        $titleSimilarity = $this->calculateWordSimilarity($sourceTitleWords, $targetTitleWords);
        $score += $titleSimilarity * 0.4;
        
        // Similarité des excerpts
        if ($source->excerpt && $target->excerpt) {
            $sourceExcerptWords = $this->extractWords($source->excerpt);
            $targetExcerptWords = $this->extractWords($target->excerpt);
            $excerptSimilarity = $this->calculateWordSimilarity($sourceExcerptWords, $targetExcerptWords);
            $score += $excerptSimilarity * 0.3;
        }
        
        // Bonus pour même site
        if ($source->site_id === $target->site_id) {
            $score += 0.2;
        }
        
        // Bonus pour même langue
        if ($source->language_code === $target->language_code) {
            $score += 0.1;
        }
        
        // Ajout de randomness pour varier les scores
        $score += (rand(-10, 20) / 100);
        
        return min(max($score, 0), 1); // Entre 0 et 1
    }
    
    private function extractWords(string $text): array
    {
        $text = strtolower($text);
        $words = preg_split('/[\s\.,!?;:"()]+/', $text);
        return array_filter($words, fn($word) => strlen($word) > 3);
    }
    
    private function calculateWordSimilarity(array $words1, array $words2): float
    {
        if (empty($words1) || empty($words2)) {
            return 0;
        }
        
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        
        return count($intersection) / count($union);
    }
    
    private function generateAnchor(string $title): string
    {
        $anchors = [
            'découvrez notre ' . strtolower($title),
            'consultez notre guide sur ' . strtolower($title),
            'en savoir plus sur ' . strtolower($title),
            'notre article sur ' . strtolower($title),
            'voir aussi : ' . strtolower($title),
        ];
        
        return $anchors[array_rand($anchors)];
    }
    
    private function generateReasoning(Article $source, Article $target, float $score): string
    {
        $reasons = [
            'Contenu complémentaire détecté',
            'Sujet connexe identifié',
            'Thématique similaire',
            'Ressource pertinente pour approfondissement',
            'Article connexe recommandé',
        ];
        
        $reason = $reasons[array_rand($reasons)];
        $percentage = round($score * 100);
        
        return "{$reason} (Score: {$percentage}%)";
    }
}
