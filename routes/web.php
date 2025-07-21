<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Auth;
use App\Models\Site;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Route pour rafraîchir le token CSRF
Route::get('/csrf-token', function () {
    return response()->json([
        'csrf_token' => csrf_token()
    ]);
})->middleware('web');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('sites', SiteController::class);
    Route::resource('articles', ArticleController::class);
    Route::resource('categories', CategoryController::class);
    Route::post('/articles/upload-image', [ArticleController::class, 'uploadImage'])->name('articles.upload-image');
    Route::post('/articles/upload-file', [ArticleController::class, 'uploadFile'])->name('articles.upload-file');
    Route::post('/articles/upload-cover-image', [ArticleController::class, 'uploadCoverImage'])->name('articles.upload-cover-image');
    
    // Route dédiée pour l'upload d'images avec gestion CSRF simplifiée
    Route::post('/upload/cover-image', [App\Http\Controllers\ImageUploadController::class, 'uploadCoverImage'])
        ->name('upload.cover-image');
    Route::post('/articles/fetch-url-metadata', [ArticleController::class, 'fetchUrlMetadata'])->name('articles.fetch-url-metadata');
    Route::post('/articles/recover', [ArticleController::class, 'recover'])->name('articles.recover');
    Route::get('/sites/{id}/colors', [SiteController::class, 'colors'])->name('sites.colors');
    Route::get('/sites/{id}/categories', [CategoryController::class, 'getBySite'])->name('sites.categories');
    Route::get('/debug/categories/{siteId}', function($siteId) {
        $user = Auth::user();
        $site = Site::where('id', $siteId)->where('user_id', $user->id)->first();
        return response()->json([
            'user_id' => $user->id,
            'site_found' => !!$site,
            'site' => $site,
            'categories' => $site ? $site->categories()->get(['categories.id', 'categories.name']) : []
        ]);
    })->name('debug.categories');
    Route::get('/sites/{site}/colors', [SiteController::class, 'getColors'])->name('sites.colors');
    Route::get('/sites/{site}/categories', [SiteController::class, 'getCategories'])->name('sites.categories');
    Route::get('/sites/{site}/languages', [SiteController::class, 'getLanguages'])->name('sites.languages');

    // **NOUVEAU: Routes pour la gestion des sujets via Inertia**
    Route::prefix('sites/{site}/topics')->group(function () {
        Route::post('/generate-ai', [App\Http\Controllers\SiteTopicController::class, 'generateWithAIWeb'])->name('site.topics.generate-ai');
        Route::post('/import', [App\Http\Controllers\SiteTopicController::class, 'importWeb'])->name('site.topics.import');
        Route::get('/', [App\Http\Controllers\SiteTopicController::class, 'indexWeb'])->name('site.topics.index');
        Route::post('/', [App\Http\Controllers\SiteTopicController::class, 'storeWeb'])->name('site.topics.store');
        Route::delete('/{topic}', [App\Http\Controllers\SiteTopicController::class, 'destroyWeb'])->name('site.topics.destroy');
    });

    // **NOUVEAU: Routes pour le calendrier éditorial des topics**
    Route::prefix('topics')->group(function () {
        Route::get('/', [App\Http\Controllers\TopicController::class, 'index'])->name('topics.index');
        Route::post('/', [App\Http\Controllers\TopicController::class, 'store'])->name('topics.store');
        Route::post('/generate-ai', [App\Http\Controllers\TopicController::class, 'generateWithAI'])->name('topics.generate-ai');
        Route::put('/{topic}', [App\Http\Controllers\TopicController::class, 'update'])->name('topics.update');
        Route::delete('/{topic}', [App\Http\Controllers\TopicController::class, 'destroy'])->name('topics.destroy');
        Route::post('/{topic}/schedule', [App\Http\Controllers\TopicController::class, 'schedule'])->name('topics.schedule');
        Route::post('/{topic}/move', [App\Http\Controllers\TopicController::class, 'move'])->name('topics.move');
        Route::post('/{topic}/duplicate', [App\Http\Controllers\TopicController::class, 'duplicate'])->name('topics.duplicate');
        Route::get('/date/{date}', [App\Http\Controllers\TopicController::class, 'getTopicsForDate'])->name('topics.date');
        
        // **NOUVEAU: Route pour générer un article depuis un topic**
        Route::post('/{topic}/generate-article', [App\Http\Controllers\TopicToArticleController::class, 'generateFromTopic'])->name('topics.generate-article');
    });
    
    // Routes pour l'IA et la traduction
    Route::post('/articles/generate-with-ai', [App\Http\Controllers\Api\AIController::class, 'generateArticle'])
        ->name('articles.generate-ai');
    Route::post('/articles/translate', [App\Http\Controllers\Api\AIController::class, 'translateArticle'])
        ->name('articles.translate');
    
    // Routes pour les batches IA (50% moins cher!)
    Route::post('/ai/batch', [App\Http\Controllers\Api\AIController::class, 'createBatch'])
        ->name('ai.batch.create');
    Route::get('/ai/batch/{batchId}/status', [App\Http\Controllers\Api\AIController::class, 'getBatchStatus'])
        ->name('ai.batch.status');
    Route::get('/ai/batch/{batchId}/results', [App\Http\Controllers\Api\AIController::class, 'getBatchResults'])
        ->name('ai.batch.results');
    Route::get('/ai/batches', [App\Http\Controllers\Api\AIController::class, 'getUserBatches'])
        ->name('ai.batches');
        
    // **Routes Admin**
    Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class)->prefix('admin')->group(function () {
        // Page principale d'administration des catégories
        Route::get('/categories', function () {
            return Inertia::render('Admin/CategoryDashboard');
        })->name('admin.categories');
        
        // Gestion des utilisateurs (administrator permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':administrator')->group(function () {
            Route::get('/users', [\App\Http\Controllers\Admin\UserAdminController::class, 'index'])->name('admin.users');
            Route::get('/users/create', [\App\Http\Controllers\Admin\UserAdminController::class, 'create'])->name('admin.users.create');
            Route::post('/users', [\App\Http\Controllers\Admin\UserAdminController::class, 'store'])->name('admin.users.store');
            Route::get('/users/{user}/edit', [\App\Http\Controllers\Admin\UserAdminController::class, 'edit'])->name('admin.users.edit');
            Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserAdminController::class, 'update'])->name('admin.users.update');
            Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserAdminController::class, 'destroy'])->name('admin.users.destroy');
            Route::post('/users/{user}/add-points', [\App\Http\Controllers\Admin\UserAdminController::class, 'addPoints'])->name('admin.users.add-points');
            Route::post('/users/{user}/remove-points', [\App\Http\Controllers\Admin\UserAdminController::class, 'removePoints'])->name('admin.users.remove-points');
            
            // Configuration système
            Route::get('/settings', [\App\Http\Controllers\Admin\SettingsAdminController::class, 'index'])->name('admin.settings');
            Route::put('/settings', [\App\Http\Controllers\Admin\SettingsAdminController::class, 'update'])->name('admin.settings.update');
        });
        
        // Analytics (view analytics permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':view analytics')->group(function () {
            Route::get('/analytics', [\App\Http\Controllers\Admin\AnalyticsAdminController::class, 'index'])->name('admin.analytics');
        });
    });
    
    // **Routes API Admin** (authentification web)
    Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class)->prefix('api/admin')->group(function () {
        // Dashboard principal (admin)
        Route::get('/dashboard/categories', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'dashboard']);
        
        // Gestion des suggestions (review suggestions permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':review suggestions')->group(function () {
            Route::get('/suggestions', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'getSuggestions']);
            Route::post('/suggestions/{suggestion}/approve', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'approveSuggestion']);
            Route::post('/suggestions/{suggestion}/reject', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'rejectSuggestion']);
            Route::post('/suggestions/{suggestion}/merge', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'mergeSuggestion']);
        });
        
        // Gestion des catégories (manage categories permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':manage categories')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'getCategories']);
            Route::put('/categories/{category}', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'updateCategory']);
            Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'deleteCategory']);
        });
        
        // Analytics (view analytics permission)
        Route::middleware(\App\Http\Middleware\AdminPermissionMiddleware::class . ':view analytics')->group(function () {
            Route::get('/analytics/categories', [\App\Http\Controllers\Admin\CategoryAdminController::class, 'getAnalytics']);
        });
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
