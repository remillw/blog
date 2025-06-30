<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'title',
        'description',
        'keywords',
        'categories',
        'language_code',
        'priority',
        'is_active',
        'usage_count',
        'last_used_at',
        'source',
        'ai_context',
        'scheduled_date',
        'scheduled_time',
        'status',
        'editorial_notes',
        'assigned_to_user_id',
        'article_id',
    ];

    protected $casts = [
        'keywords' => 'array',
        'categories' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'scheduled_date' => 'date',
        'scheduled_time' => 'datetime:H:i',
    ];

    /**
     * Relation vers le site
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Relation vers l'article généré à partir de ce topic
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Scope pour les sujets actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope pour filtrer par langue
     */
    public function scopeByLanguage($query, string $languageCode)
    {
        return $query->where('language_code', $languageCode);
    }

    /**
     * Scope pour ordonner par priorité et dernière utilisation
     */
    public function scopeByPriority($query)
    {
        return $query->orderBy('priority', 'desc')
                    ->orderBy('last_used_at', 'asc')
                    ->orderBy('usage_count', 'asc');
    }

    /**
     * Scope pour les topics programmés
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Scope pour les topics à publier aujourd'hui
     */
    public function scopeDueToday($query)
    {
        return $query->where('scheduled_date', today())
                    ->where('status', 'scheduled');
    }

    /**
     * Scope pour filtrer par période de date
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }

    /**
     * Scope pour filtrer par statut
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Marque le sujet comme utilisé
     */
    public function markAsUsed(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Génère un prompt IA basé sur ce sujet
     */
    public function generatePrompt(array $siteContext = []): string
    {
        $prompt = "Génère un article professionnel sur le sujet : {$this->title}";
        
        if ($this->description) {
            $prompt .= "\n\nDescription du sujet : {$this->description}";
        }
        
        if (!empty($this->keywords)) {
            $keywords = implode(', ', $this->keywords);
            $prompt .= "\n\nMots-clés à intégrer : {$keywords}";
        }
        
        if (!empty($this->categories)) {
            $categories = implode(', ', $this->categories);
            $prompt .= "\n\nCatégories suggérées : {$categories}";
        }
        
        if ($this->ai_context) {
            $prompt .= "\n\nContexte spécifique : {$this->ai_context}";
        }
        
        if (!empty($siteContext)) {
            if (isset($siteContext['description'])) {
                $prompt .= "\n\nContexte du site : {$siteContext['description']}";
            }
            if (isset($siteContext['guidelines'])) {
                $prompt .= "\n\nDirectives du site : {$siteContext['guidelines']}";
            }
        }
        
        return $prompt;
    }

    /**
     * Sélectionne le prochain sujet à utiliser pour un site
     */
    public static function getNextTopicForSite(int $siteId, string $languageCode = null): ?self
    {
        $query = static::where('site_id', $siteId)
            ->active()
            ->byPriority();
            
        if ($languageCode) {
            $query->byLanguage($languageCode);
        }
        
        return $query->first();
    }

    /**
     * Génère des sujets automatiquement avec l'IA
     */
    public static function generateTopicsWithAI(Site $site, string $languageCode, int $count = 10): array
    {
        // Cette méthode sera implémentée pour générer des sujets avec l'IA
        // en se basant sur la description du site et les directives de contenu
        return [];
    }
}
