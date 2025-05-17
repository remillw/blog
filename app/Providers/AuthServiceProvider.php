<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Site;
use App\Policies\ArticlePolicy;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Site::class => SitePolicy::class,
        Article::class => ArticlePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
} 