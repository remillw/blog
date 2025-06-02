<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class GlobalCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'translations',
        'icon',
        'color',
        'parent_id',
        'lft',
        'rgt',
        'depth',
        'usage_count',
        'similarity_threshold',
        'is_approved',
        'created_by',
    ];

    protected $casts = [
        'translations' => 'array',
        'similarity_threshold' => 'decimal:2',
        'is_approved' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        // Générer automatiquement le slug
        static::creating(function ($category) {
            if (empty($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });

        // Mettre à jour le compteur d'utilisation
        static::created(function ($category) {
            $category->increment('usage_count');
        });
    }

    /**
     * Relation vers le parent
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(GlobalCategory::class, 'parent_id');
    }

    /**
     * Relation vers les enfants directs
     */
    public function children(): HasMany
    {
        return $this->hasMany(GlobalCategory::class, 'parent_id')
            ->where('is_approved', true)
            ->orderBy('usage_count', 'desc')
            ->orderBy('name');
    }

    /**
     * Tous les descendants (récursif)
     */
    public function descendants(): HasMany
    {
        return $this->hasMany(GlobalCategory::class, 'parent_id')->with('descendants');
    }

    /**
     * Relation vers les sites qui utilisent cette catégorie
     */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_global_categories')
            ->withPivot(['language_code', 'custom_name', 'is_active', 'sort_order'])
            ->withTimestamps();
    }

    /**
     * Utilisateur qui a créé la catégorie
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Suggestions liées à cette catégorie
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(CategorySuggestion::class, 'similar_to_id');
    }

    /**
     * Obtenir le nom traduit dans une langue donnée
     */
    public function getTranslatedName(string $languageCode): string
    {
        $translations = $this->translations ?? [];
        return $translations[$languageCode] ?? $this->name;
    }

    /**
     * Définir une traduction pour une langue
     */
    public function setTranslation(string $languageCode, string $translation): void
    {
        $translations = $this->translations ?? [];
        $translations[$languageCode] = $translation;
        $this->translations = $translations;
        $this->save();
    }

    /**
     * Obtenir toutes les traductions disponibles
     */
    public function getAvailableLanguages(): array
    {
        return array_keys($this->translations ?? []);
    }

    /**
     * Vérifier si une traduction existe
     */
    public function hasTranslation(string $languageCode): bool
    {
        $translations = $this->translations ?? [];
        return isset($translations[$languageCode]);
    }

    /**
     * Obtenir le chemin complet (breadcrumb)
     */
    public function getPath(string $languageCode = 'fr', string $separator = ' > '): string
    {
        $path = [];
        $current = $this;

        while ($current) {
            array_unshift($path, $current->getTranslatedName($languageCode));
            $current = $current->parent;
        }

        return implode($separator, $path);
    }

    /**
     * Vérifier si c'est une catégorie racine
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Vérifier si c'est une feuille (pas d'enfants)
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    /**
     * Scope pour les catégories racines
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')
            ->where('is_approved', true)
            ->orderBy('usage_count', 'desc')
            ->orderBy('name');
    }

    /**
     * Scope pour les catégories approuvées
     */
    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    /**
     * Scope pour les catégories populaires
     */
    public function scopePopular($query, int $minUsage = 5)
    {
        return $query->where('usage_count', '>=', $minUsage);
    }

    /**
     * Scope pour recherche par langue
     */
    public function scopeWithTranslation($query, string $languageCode)
    {
        return $query->where(function ($q) use ($languageCode) {
            $q->whereJsonContains('translations->' . $languageCode, '!=', null)
              ->orWhereNotNull('name'); // Fallback sur le nom principal
        });
    }

    /**
     * Recherche intelligente par nom (avec traductions)
     */
    public static function searchByName(string $search, string $languageCode = 'fr', int $limit = 10)
    {
        return static::approved()
            ->where(function ($query) use ($search, $languageCode) {
                $query->where('name', 'LIKE', "%{$search}%")
                      ->orWhereJsonContains('translations->' . $languageCode, $search);
            })
            ->orderBy('usage_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Créer une arborescence complète pour affichage
     */
    public static function getTree(string $languageCode = 'fr'): array
    {
        $categories = static::approved()
            ->with('children.children.children') // 3 niveaux de profondeur
            ->roots()
            ->get();

        return $categories->map(function ($category) use ($languageCode) {
            return $category->toTreeArray($languageCode);
        })->toArray();
    }

    /**
     * Convertir en format arbre pour l'API
     */
    public function toTreeArray(string $languageCode = 'fr'): array
    {
        return [
            'id' => $this->id,
            'name' => $this->getTranslatedName($languageCode),
            'slug' => $this->slug,
            'icon' => $this->icon,
            'color' => $this->color,
            'usage_count' => $this->usage_count,
            'depth' => $this->depth,
            'path' => $this->getPath($languageCode),
            'children' => $this->children->map(function ($child) use ($languageCode) {
                return $child->toTreeArray($languageCode);
            })->toArray(),
        ];
    }
}
