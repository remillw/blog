<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GlobalCategory;
use App\Models\CategorySuggestion;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class GlobalCategoryController extends Controller
{
    /**
     * Obtenir l'arborescence complète des catégories globales
     */
    public function getTree(Request $request)
    {
        $request->validate([
            'language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'site_id' => 'nullable|exists:sites,id',
        ]);

        $language = $request->input('language', 'fr');
        $siteId = $request->input('site_id');

        try {
            // Obtenir l'arborescence complète
            $tree = GlobalCategory::getTree($language);

            // Si un site est spécifié, marquer les catégories liées
            if ($siteId) {
                $linkedCategories = Site::find($siteId)
                    ->globalCategories()
                    ->wherePivot('language_code', $language)
                    ->wherePivot('is_active', true)
                    ->pluck('global_categories.id')
                    ->toArray();

                $tree = $this->markLinkedCategories($tree, $linkedCategories);
            }

            return response()->json([
                'success' => true,
                'data' => $tree,
                'meta' => [
                    'language' => $language,
                    'total_categories' => GlobalCategory::approved()->count(),
                    'linked_to_site' => $siteId ? count($linkedCategories ?? []) : null,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get category tree', [
                'error' => $e->getMessage(),
                'language' => $language,
                'site_id' => $siteId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des catégories',
            ], 500);
        }
    }

    /**
     * Rechercher des catégories par nom
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:2|max:50',
            'language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'limit' => 'integer|min:1|max:50',
        ]);

        $query = $request->input('query');
        $language = $request->input('language', 'fr');
        $limit = $request->input('limit', 10);

        try {
            $categories = GlobalCategory::searchByName($query, $language, $limit);

            return response()->json([
                'success' => true,
                'data' => $categories->map(function ($category) use ($language) {
                    return [
                        'id' => $category->id,
                        'name' => $category->getTranslatedName($language),
                        'slug' => $category->slug,
                        'path' => $category->getPath($language),
                        'icon' => $category->icon,
                        'color' => $category->color,
                        'usage_count' => $category->usage_count,
                        'depth' => $category->depth,
                    ];
                }),
                'meta' => [
                    'query' => $query,
                    'language' => $language,
                    'results_count' => $categories->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Category search failed', [
                'error' => $e->getMessage(),
                'query' => $query,
                'language' => $language,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
            ], 500);
        }
    }

    /**
     * Suggérer une nouvelle catégorie avec IA anti-doublons
     */
    public function suggest(Request $request)
    {
        $request->validate([
            'name' => 'required|string|min:2|max:100',
            'language' => 'required|string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'parent_id' => 'nullable|exists:global_categories,id',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $suggestedName = trim($request->input('name'));
            $language = $request->input('language');
            $parentId = $request->input('parent_id');

            // Créer la suggestion avec analyse IA
            $suggestion = CategorySuggestion::createWithAI(
                $suggestedName,
                $language,
                auth()->id()
            );

            // Analyser le résultat
            if ($suggestion->similarity_score >= 0.70) {
                // Similarité élevée détectée
                return response()->json([
                    'success' => false,
                    'type' => 'similarity_detected',
                    'message' => 'Une catégorie similaire existe déjà',
                    'data' => [
                        'suggestion_id' => $suggestion->id,
                        'suggested_name' => $suggestion->suggested_name,
                        'similar_category' => $suggestion->similarCategory ? [
                            'id' => $suggestion->similarCategory->id,
                            'name' => $suggestion->similarCategory->getTranslatedName($language),
                            'path' => $suggestion->similarCategory->getPath($language),
                        ] : null,
                        'similarity_score' => $suggestion->similarity_score,
                        'ai_reasoning' => $suggestion->ai_reasoning,
                        'recommendations' => [
                            'merge' => 'Utiliser la catégorie existante',
                            'create' => 'Créer quand même une nouvelle catégorie',
                            'modify' => 'Modifier le nom proposé',
                        ]
                    ]
                ], 409); // Conflict status

            } else {
                // Pas de similarité élevée, créer directement la catégorie
                $category = $suggestion->approve(auth()->id());

                if ($parentId) {
                    $category->update(['parent_id' => $parentId]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Catégorie créée avec succès',
                    'data' => [
                        'category' => [
                            'id' => $category->id,
                            'name' => $category->getTranslatedName($language),
                            'slug' => $category->slug,
                            'path' => $category->getPath($language),
                        ],
                        'suggestion_id' => $suggestion->id,
                    ]
                ], 201);
            }

        } catch (\Exception $e) {
            Log::error('Category suggestion failed', [
                'error' => $e->getMessage(),
                'name' => $request->input('name'),
                'language' => $request->input('language'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suggestion de catégorie',
            ], 500);
        }
    }

    /**
     * Lier/délier des catégories à un site
     */
    public function linkToSite(Request $request)
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'exists:global_categories,id',
            'language' => 'required|string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'action' => 'required|in:link,unlink',
        ]);

        try {
            $site = Site::findOrFail($request->input('site_id'));
            $categoryIds = $request->input('category_ids');
            $language = $request->input('language');
            $action = $request->input('action');

            // Vérifier les permissions
            if ($site->user_id !== auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous n\'avez pas les permissions pour ce site',
                ], 403);
            }

            if ($action === 'link') {
                // Lier les catégories
                foreach ($categoryIds as $categoryId) {
                    $site->globalCategories()->syncWithoutDetaching([
                        $categoryId => [
                            'language_code' => $language,
                            'is_active' => true,
                            'sort_order' => 0,
                        ]
                    ]);
                }

                // Incrémenter le compteur d'utilisation
                GlobalCategory::whereIn('id', $categoryIds)->increment('usage_count');

                $message = 'Catégories liées avec succès';

            } else {
                // Délier les catégories
                $site->globalCategories()
                    ->wherePivot('language_code', $language)
                    ->detach($categoryIds);

                $message = 'Catégories déliées avec succès';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'site_id' => $site->id,
                    'affected_categories' => count($categoryIds),
                    'language' => $language,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to link/unlink categories', [
                'error' => $e->getMessage(),
                'site_id' => $request->input('site_id'),
                'category_ids' => $request->input('category_ids'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la liaison des catégories',
            ], 500);
        }
    }

    /**
     * Obtenir les catégories liées à un site
     */
    public function getSiteCategories(Request $request)
    {
        $request->validate([
            'site_id' => 'required|exists:sites,id',
            'language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
        ]);

        try {
            $site = Site::findOrFail($request->input('site_id'));
            $language = $request->input('language', 'fr');

            $categories = $site->globalCategories()
                ->wherePivot('language_code', $language)
                ->wherePivot('is_active', true)
                ->orderByPivot('sort_order')
                ->get()
                ->map(function ($category) use ($language) {
                    return [
                        'id' => $category->id,
                        'name' => $category->getTranslatedName($language),
                        'slug' => $category->slug,
                        'path' => $category->getPath($language),
                        'icon' => $category->icon,
                        'color' => $category->color,
                        'custom_name' => $category->pivot->custom_name,
                        'sort_order' => $category->pivot->sort_order,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $categories,
                'meta' => [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'language' => $language,
                    'categories_count' => $categories->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get site categories', [
                'error' => $e->getMessage(),
                'site_id' => $request->input('site_id'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des catégories du site',
            ], 500);
        }
    }

    /**
     * Obtenir les suggestions en attente
     */
    public function getPendingSuggestions(Request $request)
    {
        $request->validate([
            'language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'high_similarity_only' => 'boolean',
        ]);

        try {
            $language = $request->input('language');
            $highSimilarityOnly = $request->boolean('high_similarity_only', true);

            $query = CategorySuggestion::pending()
                ->with(['similarCategory', 'suggestedBy']);

            if ($language) {
                $query->forLanguage($language);
            }

            if ($highSimilarityOnly) {
                $query->highSimilarity(0.70);
            }

            $suggestions = $query->orderBy('similarity_score', 'desc')
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $suggestions->map(function ($suggestion) {
                    return [
                        'id' => $suggestion->id,
                        'suggested_name' => $suggestion->suggested_name,
                        'language_code' => $suggestion->language_code,
                        'similarity_score' => $suggestion->similarity_score,
                        'ai_reasoning' => $suggestion->ai_reasoning,
                        'similar_category' => $suggestion->similarCategory ? [
                            'id' => $suggestion->similarCategory->id,
                            'name' => $suggestion->similarCategory->getTranslatedName($suggestion->language_code),
                            'path' => $suggestion->similarCategory->getPath($suggestion->language_code),
                        ] : null,
                        'suggested_by' => [
                            'id' => $suggestion->suggestedBy->id,
                            'name' => $suggestion->suggestedBy->name,
                        ],
                        'created_at' => $suggestion->created_at,
                    ];
                }),
                'meta' => [
                    'total_pending' => CategorySuggestion::pending()->count(),
                    'high_similarity' => CategorySuggestion::pending()->highSimilarity(0.70)->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get pending suggestions', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des suggestions',
            ], 500);
        }
    }

    /**
     * Marquer les catégories liées dans l'arborescence
     */
    private function markLinkedCategories(array $tree, array $linkedIds): array
    {
        return array_map(function ($category) use ($linkedIds) {
            $category['is_linked'] = in_array($category['id'], $linkedIds);
            
            if (isset($category['children'])) {
                $category['children'] = $this->markLinkedCategories($category['children'], $linkedIds);
            }
            
            return $category;
        }, $tree);
    }
}
