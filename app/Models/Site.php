<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Collection;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'platform_type',
        'api_key',
        'webhook_url',
        'is_active',
        'primary_color',
        'secondary_color',
        'accent_color',
        'description',
        'auto_delete_after_sync',
        'auto_article_generation',
        'auto_schedule',
        'auto_content_guidelines',
        'auto_content_language',
        'auto_word_count',
        'last_auto_generation',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'auto_delete_after_sync' => 'boolean',
        'auto_article_generation' => 'boolean',
        'auto_schedule' => 'array',
        'last_auto_generation' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('default_permissions', 'is_active')
            ->withTimestamps();
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class)
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_site')
            ->withTimestamps();
    }

    /**
     * **NOUVEAU: Relation vers les catégories globales**
     */
    public function globalCategories(): BelongsToMany
    {
        return $this->belongsToMany(GlobalCategory::class, 'site_global_categories')
            ->withPivot(['language_code', 'custom_name', 'is_active', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * **NOUVEAU: Obtenir les catégories globales pour une langue spécifique**
     */
    public function getCategoriesForLanguage(string $languageCode): Collection
    {
        return $this->globalCategories()
            ->wherePivot('language_code', $languageCode)
            ->wherePivot('is_active', true)
            ->orderByPivot('sort_order')
            ->get();
    }

    /**
     * **NOUVEAU: Relation vers les sujets de génération automatique**
     */
    public function topics(): HasMany
    {
        return $this->hasMany(SiteTopic::class);
    }

    /**
     * **NOUVEAU: Obtenir les sujets actifs pour une langue spécifique**
     */
    public function getActiveTopicsForLanguage(string $languageCode): Collection
    {
        return $this->topics()
            ->active()
            ->byLanguage($languageCode)
            ->byPriority()
            ->get();
    }
}
