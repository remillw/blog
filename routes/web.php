<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\ArticleController;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::resource('sites', SiteController::class);
    Route::resource('articles', ArticleController::class);
    Route::post('/articles/upload-image', [ArticleController::class, 'uploadImage'])->name('articles.upload-image');
    Route::post('/articles/fetch-url-metadata', [ArticleController::class, 'fetchUrlMetadata'])->name('articles.fetch-url-metadata');
    Route::get('/sites/{id}/colors', [SiteController::class, 'colors'])->name('sites.colors');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
