<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBacklinkPoints extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'available_points',
        'used_points',
        'total_earned',
        'last_recharge_at',
    ];

    protected $casts = [
        'last_recharge_at' => 'datetime',
    ];

    /**
     * Relation vers l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Utiliser des points (pour créer un backlink externe)
     */
    public function usePoints(int $amount = 1): bool
    {
        if ($this->available_points < $amount) {
            return false;
        }

        $this->decrement('available_points', $amount);
        $this->increment('used_points', $amount);

        return true;
    }

    /**
     * Gagner des points (quand quelqu'un lie vers nos articles)
     */
    public function earnPoints(int $amount = 1): void
    {
        $this->increment('available_points', $amount);
        $this->increment('total_earned', $amount);
    }

    /**
     * Recharge automatique hebdomadaire
     */
    public function weeklyRecharge(int $amount = 3): void
    {
        $this->increment('available_points', $amount);
        $this->increment('total_earned', $amount);
        $this->update(['last_recharge_at' => now()]);
    }

    /**
     * Vérifier si l'utilisateur peut utiliser des points
     */
    public function canUsePoints(int $amount = 1): bool
    {
        return $this->available_points >= $amount;
    }

    /**
     * Obtenir ou créer les points pour un utilisateur
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'available_points' => 20,
                'used_points' => 0,
                'total_earned' => 20,
            ]
        );
    }

    /**
     * Scope pour les utilisateurs avec des points disponibles
     */
    public function scopeWithAvailablePoints($query, int $minPoints = 1)
    {
        return $query->where('available_points', '>=', $minPoints);
    }
}
