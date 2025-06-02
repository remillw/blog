<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BacklinkSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_article_id',
        'target_article_id',
        'relevance_score',
        'anchor_suggestion',
        'reasoning',
        'is_same_site',
        'is_used',
        'used_at',
    ];

    protected $casts = [
        'relevance_score' => 'decimal:2',
        'is_same_site' => 'boolean',
        'is_used' => 'boolean',
        'used_at' => 'datetime',
    ];

    /**
     * Article source (celui qui va contenir le lien)
     */
    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'source_article_id');
    }

    /**
     * Article cible (celui vers lequel pointe le lien)
     */
    public function targetArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'target_article_id');
    }

    /**
     * Marquer cette suggestion comme utilisée
     */
    public function markAsUsed(): void
    {
        $this->update([
            'is_used' => true,
            'used_at' => now(),
        ]);
    }

    /**
     * Scope pour les suggestions non utilisées
     */
    public function scopeUnused($query)
    {
        return $query->where('is_used', false);
    }

    /**
     * Scope pour les suggestions du même site
     */
    public function scopeSameSite($query)
    {
        return $query->where('is_same_site', true);
    }

    /**
     * Scope pour les suggestions externes
     */
    public function scopeExternal($query)
    {
        return $query->where('is_same_site', false);
    }

    /**
     * Scope pour les suggestions de haute qualité
     */
    public function scopeHighQuality($query, $minScore = 0.75)
    {
        return $query->where('relevance_score', '>=', $minScore);
    }
}
