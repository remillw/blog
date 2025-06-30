<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// **GÉNÉRATION AUTOMATIQUE D'ARTICLES À PARTIR DES TOPICS PROGRAMMÉS**
Schedule::command('articles:generate-auto')
    ->everyThirtyMinutes() // Vérifier toutes les 30 minutes
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/auto-generation.log'));

// Planification automatique du nettoyage des articles synchronisés
Schedule::command('articles:clean-synced --force')
    ->dailyAt('02:00') // Tous les jours à 2h du matin
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cleanup.log'));

// Optionnel : Nettoyage plus agressif le dimanche
Schedule::command('articles:clean-synced --days=3 --force')
    ->weeklyOn(0, '03:00') // Dimanche à 3h du matin
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cleanup-weekly.log'));
