<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArticleApiController extends Controller
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
                Log::warning('Invalid API key attempt', [
                    'provided_key' => substr($apiKey, 0, 8) . '...',
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid API key'
                ], 401);
            }

            // Récupérer les paramètres de pagination et filtres
            $perPage = min($request->get('per_page', 50), 100); // Max 100 articles par page
            $status = $request->get('status'); // draft, published, scheduled
            $search = $request->get('search');
            $since = $request->get('since'); // Date ISO pour récupérer seulement les articles modifiés depuis

            // Construire la requête pour les articles de ce site
            $query = Article::where('site_id', $site->id)
                ->with(['categories:id,name,slug'])
                ->orderBy('updated_at', 'desc'); // Trier par date de modification

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

            // Appliquer les autres filtres
            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%")
                      ->orWhere('excerpt', 'like', "%{$search}%");
                });
            }

            // Paginer les résultats
            $articles = $query->paginate($perPage);

            // Transformer les données pour l'API
            $transformedArticles = $articles->getCollection()->map(function ($article) {
                return [
                    'id' => $article->id,
                    'external_id' => $article->external_id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'content' => $article->content_html, // HTML pour webhook
                    'excerpt' => $article->excerpt,
                    'status' => $article->status,
                    'meta_keywords' => $article->meta_keywords,
                    'canonical_url' => $article->canonical_url,
                    'og_title' => $article->og_title,
                    'og_description' => $article->og_description,
                    'og_image' => $article->og_image,
                    'twitter_title' => $article->twitter_title,
                    'twitter_description' => $article->twitter_description,
                    'twitter_image' => $article->twitter_image,
                    'schema_markup' => $article->schema_markup,
                    'source' => $article->source,
                    'is_synced' => $article->is_synced,
                    'is_featured' => $article->is_featured,
                    'reading_time' => $article->reading_time,
                    'word_count' => $article->word_count,
                    'author_name' => $article->author_name,
                    'author_bio' => $article->author_bio,
                    'published_at' => $article->published_at?->toISOString(),
                    'created_at' => $article->created_at->toISOString(),
                    'cover_image' => $article->cover_image,
                    'meta_title' => $article->meta_title,
                    'meta_description' => $article->meta_description,
                    'author_name' => $article->author_name,
                    'author_bio' => $article->author_bio,
                    'published_at' => $article->published_at?->toISOString(),
                    'created_at' => $article->created_at->toISOString(),
                    'updated_at' => $article->updated_at->toISOString(),
                    'categories' => $article->categories->pluck('name')->toArray(),
                    'reading_time' => $article->reading_time,
                    'is_featured' => $article->is_featured,
                ];
            });

            Log::info('Articles fetched via API', [
                'site_id' => $site->id,
                'site_name' => $site->name,
                'articles_count' => $transformedArticles->count(),
                'total_articles' => $articles->total(),
                'since_filter' => $since,
                'filters' => [
                    'status' => $status,
                    'search' => $search,
                ]
            ]);

            return response()->json([
                'success' => true,
                'site' => [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                ],
                'articles' => $transformedArticles,
                'pagination' => [
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'per_page' => $articles->perPage(),
                    'total' => $articles->total(),
                    'from' => $articles->firstItem(),
                    'to' => $articles->lastItem(),
                ],
                'sync_info' => [
                    'since' => $since,
                    'server_time' => now()->toISOString(),
                    'filtered_by_date' => !empty($since),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching articles via API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Récupère les articles d'un site spécifique pour le formulaire d'article
     */
    public function getSiteArticles(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if ($request->user()->id !== $site->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Article::where('site_id', $site->id)
            ->orderBy('updated_at', 'desc');

        // Filtre par langue si spécifié
        if ($request->has('language') && $request->language) {
            $query->where('language_code', $request->language);
        }

        // Filtre par statut si spécifié
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Recherche textuelle
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', "%{$request->search}%")
                  ->orWhere('excerpt', 'like', "%{$request->search}%");
            });
        }

        $perPage = min($request->get('per_page', 20), 50); // Max 50 pour l'interface
        $articles = $query->paginate($perPage);

        return response()->json([
            'articles' => $articles->items(),
            'pagination' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'per_page' => $articles->perPage(),
                'total' => $articles->total(),
                'has_more_pages' => $articles->hasMorePages(),
            ]
        ]);
    }

    /**
     * Récupère les détails complets d'un article pour édition
     */
    public function getFullArticle(Request $request, Article $article)
    {
        // Vérifier que l'utilisateur a accès à l'article
        $site = $article->site;
        if ($request->user()->id !== $site->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Charger l'article avec toutes ses relations
        $article->load(['categories:id,name,slug', 'tags:id,name', 'site:id,name']);

        return response()->json([
            'article' => [
                'id' => $article->id,
                'site_id' => $article->site_id,
                'language_code' => $article->language_code,
                'title' => $article->title,
                'slug' => $article->slug,
                'content' => $article->content, // Version markdown/brute
                'content_html' => $article->content_html,
                'excerpt' => $article->excerpt,
                'cover_image' => $article->cover_image,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'meta_keywords' => $article->meta_keywords,
                'canonical_url' => $article->canonical_url,
                'status' => $article->status,
                'published_at' => $article->published_at,
                'scheduled_at' => $article->scheduled_at,
                'is_featured' => $article->is_featured,
                'reading_time' => $article->reading_time,
                'word_count' => $article->word_count,
                'author_name' => $article->author_name,
                'author_bio' => $article->author_bio,
                'og_title' => $article->og_title,
                'og_description' => $article->og_description,
                'og_image' => $article->og_image,
                'twitter_title' => $article->twitter_title,
                'twitter_description' => $article->twitter_description,
                'twitter_image' => $article->twitter_image,
                'schema_markup' => $article->schema_markup,
                'source' => $article->source,
                'external_id' => $article->external_id,
                'created_at' => $article->created_at,
                'updated_at' => $article->updated_at,
                'categories' => $article->categories->map(fn($cat) => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                ]),
                'tags' => $article->tags->map(fn($tag) => [
                    'id' => $tag->id,
                    'name' => $tag->name,
                ]),
                'site' => [
                    'id' => $site->id,
                    'name' => $site->name,
                ]
            ]
        ]);
    }
} 