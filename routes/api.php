<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route pour récupérer les articles d'un site via clé API
Route::get('/articles', [App\Http\Controllers\Api\ArticleApiController::class, 'index'])
    ->name('api.articles.index');

// Route pour récupérer les catégories d'un site via clé API
Route::get('/categories', [App\Http\Controllers\Api\CategoryApiController::class, 'index'])
    ->name('api.categories.index'); 