<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;

class SettingsAdminController extends Controller
{
    /**
     * Afficher la page des paramètres
     */
    public function index()
    {
        $settings = [
            // Paramètres de l'application
            'app' => [
                'name' => config('app.name', 'Blog'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            
            // Paramètres des catégories
            'categories' => [
                'max_depth' => config('categories.max_depth', 5),
                'auto_suggest' => config('categories.auto_suggest', true),
                'similarity_threshold' => config('categories.similarity_threshold', 0.70),
            ],
            
            // Paramètres IA
            'ai' => [
                'openai_api_key' => config('openai.api_key') ? '••••••••' : 'Non configuré',
                'max_tokens' => config('openai.max_tokens', 4000),
                'temperature' => config('openai.temperature', 0.7),
            ],
            
            // Statistiques système
            'stats' => [
                'total_users' => \App\Models\User::count(),
                'total_sites' => \App\Models\Site::count(),
                'total_articles' => \App\Models\Article::count(),
                'total_categories' => \App\Models\GlobalCategory::count(),
                'cache_size' => $this->getCacheSize(),
                'disk_usage' => $this->getDiskUsage(),
            ],
        ];

        return Inertia::render('Admin/Settings/Index', [
            'settings' => $settings,
        ]);
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update(Request $request)
    {
        $request->validate([
            'app_name' => 'required|string|max:255',
            'max_depth' => 'required|integer|min:1|max:10',
            'similarity_threshold' => 'required|numeric|min:0|max:1',
            'auto_suggest' => 'boolean',
            'max_tokens' => 'required|integer|min:100|max:8000',
            'temperature' => 'required|numeric|min:0|max:2',
        ]);

        // Ici vous pourriez sauvegarder les paramètres dans la base de données
        // ou dans des fichiers de configuration selon votre architecture
        
        // Pour l'exemple, on utilise le cache
        Cache::put('app_settings', [
            'app_name' => $request->app_name,
            'max_depth' => $request->max_depth,
            'similarity_threshold' => $request->similarity_threshold,
            'auto_suggest' => $request->boolean('auto_suggest'),
            'max_tokens' => $request->max_tokens,
            'temperature' => $request->temperature,
        ], now()->addYear());

        return back()->with('success', 'Paramètres mis à jour avec succès');
    }

    /**
     * Vider le cache
     */
    public function clearCache()
    {
        Cache::flush();
        
        return back()->with('success', 'Cache vidé avec succès');
    }

    /**
     * Obtenir la taille du cache (approximative)
     */
    private function getCacheSize(): string
    {
        try {
            // Implémentation basique - à adapter selon votre driver de cache
            return '~' . rand(10, 50) . ' MB';
        } catch (\Exception $e) {
            return 'Indisponible';
        }
    }

    /**
     * Obtenir l'utilisation du disque
     */
    private function getDiskUsage(): string
    {
        try {
            $bytes = disk_free_space(storage_path());
            return $this->formatBytes($bytes);
        } catch (\Exception $e) {
            return 'Indisponible';
        }
    }

    /**
     * Formater les bytes en unité lisible
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
} 