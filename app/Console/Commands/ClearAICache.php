<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearAICache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ai:clear-cache {--force : Force delete all AI cache}';

    /**
     * The console command description.
     */
    protected $description = 'Clear AI generated content cache to optimize costs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $cacheKeys = [];
        $cleared = 0;

        if ($this->option('force')) {
            // Supprimer tout le cache commençant par "ai_article_"
            $this->info('🗑️ Clearing ALL AI cache...');
            
            // Note: Laravel ne fournit pas une méthode directe pour lister les clés
            // On peut utiliser Redis directement si disponible
            if (config('cache.default') === 'redis') {
                $redis = app('redis');
                $keys = $redis->keys('*ai_article_*');
                foreach ($keys as $key) {
                    $redis->del($key);
                    $cleared++;
                }
            } else {
                // Pour les autres drivers, on ne peut pas lister facilement
                $this->warn('⚠️ Cannot list cache keys with current driver. Cache will expire naturally after 24h.');
            }
        } else {
            $this->info('💡 Use --force to clear all AI cache immediately');
            $this->info('📋 Current cache strategy:');
            $this->line('   - Cache duration: 24 hours');
            $this->line('   - Cache key format: ai_article_{hash}');
            $this->line('   - Model: GPT-3.5-turbo (10x cheaper than GPT-4)');
        }

        if ($cleared > 0) {
            $this->info("✅ Cleared {$cleared} AI cache entries");
        }

        $this->info('💰 Cost optimization tips:');
        $this->line('   1. ✅ Using GPT-3.5-turbo instead of GPT-4');
        $this->line('   2. ✅ 24h cache for identical prompts');
        $this->line('   3. ✅ Optimized prompts for better efficiency');
        $this->line('   4. ✅ Limited to 3000 tokens output');

        return Command::SUCCESS;
    }
}
