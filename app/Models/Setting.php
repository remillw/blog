<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Récupère une valeur de configuration
     */
    public static function get(string $key, $default = null)
    {
        // Pour l'instant, retourner les valeurs par défaut
        // Plus tard, vous pourrez créer une vraie table settings
        $settings = [
            'saas_url' => config('services.saas.url', env('SAAS_URL')),
            'saas_api_key' => config('services.saas.api_key', env('SAAS_API_KEY')),
        ];

        return $settings[$key] ?? $default;
    }

    /**
     * Définit une valeur de configuration
     */
    public static function set(string $key, $value): void
    {
        // Pour l'instant, ne rien faire
        // Plus tard, vous pourrez sauvegarder en base
    }
}
