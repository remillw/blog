<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GlobalCategory;
use App\Models\CategorySuggestion;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CategoryAdminController extends Controller
{
    /**
     * Dashboard principal des catégories
     */
    public function dashboard(Request $request)
    {
        try {
            $stats = [
                'total_categories' => GlobalCategory::count(),
                'root_categories' => GlobalCategory::roots()->count(),
                'pending_suggestions' => CategorySuggestion::pending()->count(),
                'high_similarity_suggestions' => CategorySuggestion::pending()->highSimilarity(0.70)->count(),
                'categories_with_sites' => GlobalCategory::whereHas('sites')->count(),
                'total_sites_linked' => DB::table('site_global_categories')->distinct('site_id')->count(),
                'most_used_categories' => GlobalCategory::orderBy('usage_count', 'desc')->limit(5)->get(['id', 'name', 'usage_count']),
                'recent_suggestions' => CategorySuggestion::with(['similarCategory', 'suggestedBy'])
                    ->orderBy('created_at', 'desc')
                    ->limit(5)
                    ->get(),
            ];

            // Analytics par langue
            $languageStats = DB::table('category_suggestions')
                ->select('language_code', DB::raw('COUNT(*) as count'))
                ->groupBy('language_code')
                ->orderBy('count', 'desc')
                ->get();

            // Activité récente
            $recentActivity = $this->getRecentActivity();

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'language_stats' => $languageStats,
                    'recent_activity' => $recentActivity,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard admin categories failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard'
            ], 500);
        }
    }

    /**
     * Gérer les suggestions en attente
     */
    public function getSuggestions(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:pending,approved,rejected,merged',
            'language' => 'nullable|string|in:fr,en,es,de,it',
            'high_similarity' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $query = CategorySuggestion::with(['similarCategory', 'suggestedBy', 'reviewedBy']);

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('language')) {
                $query->where('language_code', $request->language);
            }

            if ($request->boolean('high_similarity')) {
                $query->highSimilarity(0.70);
            }

            $suggestions = $query->orderBy('similarity_score', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $suggestions,
            ]);

        } catch (\Exception $e) {
            Log::error('Get suggestions failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des suggestions'
            ], 500);
        }
    }

    /**
     * Approuver une suggestion
     */
    public function approveSuggestion(Request $request, CategorySuggestion $suggestion)
    {
        $request->validate([
            'parent_id' => 'nullable|exists:global_categories,id',
            'custom_name' => 'nullable|string|max:100',
        ]);

        try {
            if (!$suggestion->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette suggestion a déjà été traitée'
                ], 400);
            }

            $category = $suggestion->approve(auth()->id());

            // Définir le parent si spécifié
            if ($request->filled('parent_id')) {
                $category->update(['parent_id' => $request->parent_id]);
            }

            // Utiliser un nom personnalisé si fourni
            if ($request->filled('custom_name')) {
                $category->update(['name' => $request->custom_name]);
                $category->setTranslation($suggestion->language_code, $request->custom_name);
            }

            return response()->json([
                'success' => true,
                'message' => 'Suggestion approuvée avec succès',
                'data' => [
                    'suggestion' => $suggestion,
                    'category' => $category->toTreeArray($suggestion->language_code),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Approve suggestion failed', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'approbation'
            ], 500);
        }
    }

    /**
     * Rejeter une suggestion
     */
    public function rejectSuggestion(Request $request, CategorySuggestion $suggestion)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            if (!$suggestion->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette suggestion a déjà été traitée'
                ], 400);
            }

            $suggestion->reject(auth()->id(), $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Suggestion rejetée avec succès',
                'data' => $suggestion
            ]);

        } catch (\Exception $e) {
            Log::error('Reject suggestion failed', [
                'suggestion_id' => $suggestion->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du rejet'
            ], 500);
        }
    }

    /**
     * Fusionner une suggestion avec une catégorie existante
     */
    public function mergeSuggestion(Request $request, CategorySuggestion $suggestion)
    {
        $request->validate([
            'merge_with_id' => 'required|exists:global_categories,id',
        ]);

        try {
            if (!$suggestion->isPending()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cette suggestion a déjà été traitée'
                ], 400);
            }

            $targetCategory = GlobalCategory::findOrFail($request->merge_with_id);
            $suggestion->mergeWith($targetCategory, auth()->id());

            return response()->json([
                'success' => true,
                'message' => 'Suggestion fusionnée avec succès',
                'data' => [
                    'suggestion' => $suggestion,
                    'target_category' => $targetCategory->toTreeArray($suggestion->language_code),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Merge suggestion failed', [
                'suggestion_id' => $suggestion->id,
                'merge_with_id' => $request->merge_with_id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la fusion'
            ], 500);
        }
    }

    /**
     * Gérer les catégories existantes
     */
    public function getCategories(Request $request)
    {
        $request->validate([
            'language' => 'nullable|string|in:fr,en,es,de,it',
            'search' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:global_categories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $language = $request->input('language', 'fr');
            $query = GlobalCategory::approved()->with(['parent', 'children']);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search, $language) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhereJsonContains('translations->' . $language, $search);
                });
            }

            if ($request->has('parent_id')) {
                $query->where('parent_id', $request->parent_id);
            }

            $categories = $query->orderBy('usage_count', 'desc')
                ->orderBy('name')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);

        } catch (\Exception $e) {
            Log::error('Get categories failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des catégories'
            ], 500);
        }
    }

    /**
     * Mettre à jour une catégorie
     */
    public function updateCategory(Request $request, GlobalCategory $category)
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'parent_id' => 'nullable|exists:global_categories,id',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:10',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'translations' => 'nullable|array',
            'translations.*' => 'string|max:100',
            'is_approved' => 'boolean',
        ]);

        try {
            $updateData = $request->only(['name', 'parent_id', 'description', 'icon', 'color', 'is_approved']);
            
            if ($request->filled('translations')) {
                $updateData['translations'] = array_merge($category->translations ?? [], $request->translations);
            }

            $category->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Catégorie mise à jour avec succès',
                'data' => $category->toTreeArray('fr')
            ]);

        } catch (\Exception $e) {
            Log::error('Update category failed', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour'
            ], 500);
        }
    }

    /**
     * Supprimer une catégorie
     */
    public function deleteCategory(GlobalCategory $category)
    {
        try {
            // Vérifier s'il y a des liens avec des sites
            $sitesCount = $category->sites()->count();
            if ($sitesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de supprimer : {$sitesCount} site(s) utilisent cette catégorie"
                ], 400);
            }

            // Si c'est un parent, déplacer les enfants
            if ($category->children()->count() > 0) {
                $category->children()->update(['parent_id' => $category->parent_id]);
            }

            $category->delete();

            return response()->json([
                'success' => true,
                'message' => 'Catégorie supprimée avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete category failed', [
                'category_id' => $category->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression'
            ], 500);
        }
    }

    /**
     * Analytics des catégories
     */
    public function getAnalytics(Request $request)
    {
        try {
            $analytics = [
                'category_usage' => GlobalCategory::orderBy('usage_count', 'desc')
                    ->limit(10)
                    ->get(['id', 'name', 'usage_count', 'color']),
                
                'language_distribution' => DB::table('category_suggestions')
                    ->select('language_code', DB::raw('COUNT(*) as total'))
                    ->groupBy('language_code')
                    ->orderBy('total', 'desc')
                    ->get(),
                
                'suggestion_trends' => DB::table('category_suggestions')
                    ->select(
                        DB::raw('DATE(created_at) as date'),
                        DB::raw('COUNT(*) as suggestions'),
                        DB::raw('AVG(similarity_score) as avg_similarity')
                    )
                    ->where('created_at', '>=', now()->subDays(30))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get(),
                
                'site_adoption' => DB::table('site_global_categories')
                    ->join('sites', 'sites.id', '=', 'site_global_categories.site_id')
                    ->select('sites.name', DB::raw('COUNT(*) as categories_count'))
                    ->groupBy('sites.id', 'sites.name')
                    ->orderBy('categories_count', 'desc')
                    ->limit(10)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            Log::error('Get analytics failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des analytics'
            ], 500);
        }
    }

    /**
     * Obtenir l'activité récente
     */
    private function getRecentActivity(): array
    {
        $recentSuggestions = CategorySuggestion::with(['suggestedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($suggestion) {
                return [
                    'type' => 'suggestion',
                    'action' => 'created',
                    'message' => "Nouvelle suggestion: {$suggestion->suggested_name}",
                    'user' => $suggestion->suggestedBy->name ?? 'Anonyme',
                    'created_at' => $suggestion->created_at,
                ];
            });

        $recentCategories = GlobalCategory::with(['creator'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($category) {
                return [
                    'type' => 'category',
                    'action' => 'created',
                    'message' => "Nouvelle catégorie: {$category->name}",
                    'user' => $category->creator->name ?? 'Système',
                    'created_at' => $category->created_at,
                ];
            });

        return $recentSuggestions->merge($recentCategories)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->toArray();
    }
}
