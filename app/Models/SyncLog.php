<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'saas_url',
        'api_key_hash',
        'last_sync_at',
        'articles_fetched',
        'articles_created',
        'articles_updated',
        'sync_data',
        'sync_notes',
        'sync_success',
    ];

    protected $casts = [
        'last_sync_at' => 'datetime',
        'sync_data' => 'array',
        'sync_success' => 'boolean',
    ];

    /**
     * Obtenir le dernier log de sync pour une URL et clé API
     */
    public static function getLastSync(string $saasUrl, string $apiKey): ?self
    {
        $apiKeyHash = hash('sha256', $apiKey);
        
        return static::where('saas_url', $saasUrl)
                    ->where('api_key_hash', $apiKeyHash)
                    ->orderBy('last_sync_at', 'desc')
                    ->first();
    }

    /**
     * Créer un nouveau log de synchronisation
     */
    public static function createSyncLog(
        string $saasUrl, 
        string $apiKey, 
        array $syncData = [],
        string $notes = null
    ): self {
        return static::create([
            'saas_url' => $saasUrl,
            'api_key_hash' => hash('sha256', $apiKey),
            'last_sync_at' => now(),
            'sync_data' => $syncData,
            'sync_notes' => $notes,
        ]);
    }

    /**
     * Mettre à jour les statistiques de synchronisation
     */
    public function updateStats(int $fetched, int $created, int $updated, bool $success = true): void
    {
        $this->update([
            'articles_fetched' => $fetched,
            'articles_created' => $created,
            'articles_updated' => $updated,
            'sync_success' => $success,
        ]);
    }

    /**
     * Vérifier si une synchronisation est nécessaire
     */
    public function needsSync(int $intervalMinutes = 60): bool
    {
        if (!$this->last_sync_at) {
            return true;
        }

        return $this->last_sync_at->addMinutes($intervalMinutes)->isPast();
    }

    /**
     * Obtenir la date de dernière modification pour l'API
     */
    public function getLastModifiedForApi(): ?string
    {
        return $this->last_sync_at?->toISOString();
    }
}
