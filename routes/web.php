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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
