<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryApiController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Récupérer la clé API depuis le header
            $apiKey = $request->header('X-API-Key');
            
            if (!$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API key required in X-API-Key header'
                ], 401);
            }

            // Trouver le site correspondant à la clé API
            $site = Site::where('api_key', $apiKey)->first();
            
            if (!$site) {
                Log::warning('Invalid API key attempt for categories', [
                    'provided_key' => substr($apiKey, 0, 8) . '...',
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API key'
                ], 401);
            }

            // Récupérer les paramètres
            $perPage = min($request->get('per_page', 50), 100); // Max 100 catégories par page
            $search = $request->get('search');
            $since = $request->get('since'); // Date ISO pour récupérer seulement les catégories modifiées depuis

            // Construire la requête pour les catégories utilisées par les articles de ce site
            $query = Category::whereHas('articles', function ($q) use ($site) {
                $q->where('site_id', $site->id);
            })
            ->withCount(['articles' => function ($q) use ($site) {
                $q->where('site_id', $site->id);
            }])
            ->orderBy('updated_at', 'desc');

            // Appliquer le filtre "since" pour la synchronisation incrémentale
            if ($since) {
                try {
                    $sinceDate = \Carbon\Carbon::parse($since);
                    $query->where('updated_at', '>', $sinceDate);
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format for "since" parameter. Use ISO 8601 format.'
                    ], 400);
                }
            }

            // Appliquer le filtre de recherche
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('slug', 'like', "%{$search}%");
                });
            }

            // Paginer les résultats
            $categories = $query->paginate($perPage);

            // Transformer les données pour l'API
            $transformedCategories = $categories->getCollection()->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'articles_count' => $category->articles_count,
                    'created_at' => $category->created_at->toISOString(),
                    'updated_at' => $category->updated_at->toISOString(),
                ];
            });

            Log::info('Categories fetched via API', [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'categories_count' => $transformedCategories->count(),
                'total_categories' => $categories->total(),
                'since_filter' => $since,
                'search' => $search,
            ]);

            return response()->json([
                'success' => true,
                'site' => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                ],
                'categories' => $transformedCategories,
                'pagination' => [
                    'current_page' => $categories->currentPage(),
                    'last_page' => $categories->lastPage(),
                    'per_page' => $categories->perPage(),
                    'total' => $categories->total(),
                    'from' => $categories->firstItem(),
                    'to' => $categories->lastItem(),
                ],
                'sync_info' => [
                    'since' => $since,
                    'server_time' => now()->toISOString(),
                    'filtered_by_date' => !empty($since),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching categories via API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
