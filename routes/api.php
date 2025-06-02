<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AIController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Routes IA
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/articles/generate-with-ai', [AIController::class, 'generateArticle']);
    Route::post('/articles/translate', [AIController::class, 'translateArticle']);
    
    // Routes batch
    Route::post('/ai/batch', [AIController::class, 'createBatch']);
    Route::get('/ai/batches', [AIController::class, 'getUserBatches']);
    Route::get('/ai/batch/{id}/status', [AIController::class, 'getBatchStatus']);
    Route::get('/ai/batch/{id}/results', [AIController::class, 'getBatchResults']);

    // **NOUVEAU: Routes backlinks et points**
    Route::get('/user/backlink-points', function (Request $request) {
        $userPoints = \App\Models\UserBacklinkPoints::getOrCreateForUser($request->user()->id);
        return response()->json([
            'available_points' => $userPoints->available_points,
            'used_points' => $userPoints->used_points,
            'total_earned' => $userPoints->total_earned,
            'last_recharge_at' => $userPoints->last_recharge_at,
        ]);
    });

    Route::get('/articles/{article}/backlink-suggestions', function (\App\Models\Article $article) {
        $suggestions = \App\Models\BacklinkSuggestion::where('source_article_id', $article->id)
            ->unused()
            ->highQuality()
            ->with(['targetArticle.site'])
            ->orderBy('relevance_score', 'desc')
            ->limit(10)
            ->get();

        return response()->json($suggestions->map(function ($suggestion) {
            return [
                'id' => $suggestion->id,
                'target_id' => $suggestion->target_article_id,
                'target_title' => $suggestion->targetArticle->title,
                'target_excerpt' => $suggestion->targetArticle->excerpt,
                'relevance_score' => $suggestion->relevance_score,
                'anchor_suggestion' => $suggestion->anchor_suggestion,
                'reasoning' => $suggestion->reasoning,
                'is_same_site' => $suggestion->is_same_site,
                'target_site_name' => $suggestion->targetArticle->site->name ?? null,
            ];
        }));
    });

    // **NOUVEAU: Routes catégories globales**
    Route::prefix('global-categories')->group(function () {
        Route::get('/tree', [\App\Http\Controllers\Api\GlobalCategoryController::class, 'getTree']);
        Route::get('/search', [\App\Http\Controllers\Api\GlobalCategoryController::class, 'search']);
        Route::post('/suggest', [\App\Http\Controllers\Api\GlobalCategoryController::class, 'suggest']);
        Route::post('/link-to-site', [\App\Http\Controllers\Api\GlobalCategoryController::class, 'linkToSite']);
        Route::get('/site-categories', [\App\Http\Controllers\Api\GlobalCategoryController::class, 'getSiteCategories']);
        Route::get('/pending-suggestions', [\App\Http\Controllers\Api\GlobalCategoryController::class, 'getPendingSuggestions']);
    });

    // **NOUVEAU: Routes admin pour la gestion des catégories** 
    Route::middleware(['auth:sanctum', \App\Http\Middleware\AdminPermissionMiddleware::class])->prefix('admin')->group(function () {
        // Dashboard principal (modérateur+)
        Route::get('/dashboard/categories', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'dashboard']);
        
        // Gestion des suggestions (review_suggestions permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':review_suggestions')->group(function () {
            Route::get('/suggestions', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'getSuggestions']);
            Route::post('/suggestions/{suggestion}/approve', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'approveSuggestion']);
            Route::post('/suggestions/{suggestion}/reject', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'rejectSuggestion']);
            Route::post('/suggestions/{suggestion}/merge', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'mergeSuggestion']);
        });
        
        // Gestion des catégories (manage_categories permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':manage_categories')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'getCategories']);
            Route::put('/categories/{category}', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'updateCategory']);
            Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'deleteCategory']);
        });
        
        // Analytics (view_analytics permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':view_analytics')->group(function () {
            Route::get('/analytics/categories', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'getAnalytics']);
        });
    });
});

// Route pour récupérer les articles d'un site via clé API
Route::get('/articles', [App\Http\Controllers\Api\ArticleApiController::class, 'index'])
    ->name('api.articles.index');

// Route pour récupérer les catégories d'un site via clé API
Route::get('/categories', [App\Http\Controllers\Api\CategoryApiController::class, 'index'])
    ->name('api.categories.index'); 