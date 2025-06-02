<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Site;
use App\Models\Article;
use App\Models\GlobalCategory;
use App\Models\CategorySuggestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class AnalyticsAdminController extends Controller
{
    /**
     * Page d'analytics administrateur
     */
    public function index(Request $request)
    {
        $period = $request->input('period', '30'); // 7, 30, 90 jours

        $analytics = [
            'overview' => $this->getOverviewStats(),
            'growth' => $this->getGrowthStats($period),
            'categories' => $this->getCategoriesAnalytics(),
            'suggestions' => $this->getSuggestionsAnalytics(),
            'users' => $this->getUsersAnalytics($period),
            'performance' => $this->getPerformanceMetrics(),
        ];

        return Inertia::render('Admin/Analytics/Index', [
            'analytics' => $analytics,
            'period' => $period,
        ]);
    }

    /**
     * Statistiques générales
     */
    private function getOverviewStats(): array
    {
        return [
            'total_users' => User::count(),
            'total_sites' => Site::count(),
            'total_articles' => Article::count(),
            'total_categories' => GlobalCategory::count(),
            'pending_suggestions' => CategorySuggestion::pending()->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'active_sites' => Site::where('is_active', true)->count(),
            'published_articles' => Article::where('status', 'published')->count(),
        ];
    }

    /**
     * Statistiques de croissance
     */
    private function getGrowthStats(string $period): array
    {
        $days = (int) $period;
        $startDate = Carbon::now()->subDays($days);

        return [
            'users' => $this->getGrowthData(User::class, $days),
            'sites' => $this->getGrowthData(Site::class, $days),
            'articles' => $this->getGrowthData(Article::class, $days),
            'categories' => $this->getGrowthData(GlobalCategory::class, $days),
        ];
    }

    /**
     * Données de croissance pour un modèle
     */
    private function getGrowthData(string $model, int $days): array
    {
        $data = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $count = $model::whereDate('created_at', $date)->count();
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'count' => $count,
            ];
        }
        return $data;
    }

    /**
     * Analytics des catégories
     */
    private function getCategoriesAnalytics(): array
    {
        $topCategories = GlobalCategory::withCount('sites')
            ->orderBy('sites_count', 'desc')
            ->limit(10)
            ->get(['id', 'name', 'usage_count'])
            ->map(function ($category) {
                return [
                    'name' => $category->name,
                    'usage_count' => $category->usage_count,
                    'sites_count' => $category->sites_count,
                ];
            });

        $languageDistribution = DB::table('global_categories')
            ->join('global_category_translations', 'global_categories.id', '=', 'global_category_translations.global_category_id')
            ->select('locale', DB::raw('COUNT(*) as count'))
            ->groupBy('locale')
            ->orderBy('count', 'desc')
            ->get();

        return [
            'top_categories' => $topCategories,
            'language_distribution' => $languageDistribution,
            'depth_distribution' => $this->getCategoryDepthStats(),
        ];
    }

    /**
     * Répartition par profondeur des catégories
     */
    private function getCategoryDepthStats(): array
    {
        return DB::table('global_categories')
            ->select(DB::raw('depth, COUNT(*) as count'))
            ->groupBy('depth')
            ->orderBy('depth')
            ->get()
            ->toArray();
    }

    /**
     * Analytics des suggestions
     */
    private function getSuggestionsAnalytics(): array
    {
        $statusDistribution = CategorySuggestion::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $similarityStats = [
            'high' => CategorySuggestion::where('similarity_score', '>=', 0.70)->count(),
            'medium' => CategorySuggestion::whereBetween('similarity_score', [0.40, 0.69])->count(),
            'low' => CategorySuggestion::where('similarity_score', '<', 0.40)->count(),
        ];

        $recentSuggestions = CategorySuggestion::with(['suggestedBy', 'similarCategory'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'status_distribution' => $statusDistribution,
            'similarity_stats' => $similarityStats,
            'recent_suggestions' => $recentSuggestions,
        ];
    }

    /**
     * Analytics des utilisateurs
     */
    private function getUsersAnalytics(string $period): array
    {
        $days = (int) $period;
        
        $activeUsers = User::where('updated_at', '>=', Carbon::now()->subDays($days))->count();
        
        $usersByRole = DB::table('users')
            ->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->select('roles.name as role', DB::raw('COUNT(users.id) as count'))
            ->groupBy('roles.name')
            ->get();

        return [
            'active_users' => $activeUsers,
            'users_by_role' => $usersByRole,
            'registration_trend' => $this->getGrowthData(User::class, $days),
        ];
    }

    /**
     * Métriques de performance
     */
    private function getPerformanceMetrics(): array
    {
        return [
            'avg_suggestions_per_day' => $this->getAverageSuggestionsPerDay(),
            'approval_rate' => $this->getApprovalRate(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'popular_features' => $this->getPopularFeatures(),
        ];
    }

    /**
     * Moyenne des suggestions par jour
     */
    private function getAverageSuggestionsPerDay(): float
    {
        $totalSuggestions = CategorySuggestion::count();
        $daysSinceFirstSuggestion = CategorySuggestion::oldest('created_at')->first()?->created_at?->diffInDays(now()) ?? 1;
        
        return round($totalSuggestions / max($daysSinceFirstSuggestion, 1), 2);
    }

    /**
     * Taux d'approbation des suggestions
     */
    private function getApprovalRate(): float
    {
        $total = CategorySuggestion::whereIn('status', ['approved', 'rejected', 'merged'])->count();
        $approved = CategorySuggestion::whereIn('status', ['approved', 'merged'])->count();
        
        return $total > 0 ? round(($approved / $total) * 100, 1) : 0;
    }

    /**
     * Temps de réponse moyen
     */
    private function getAverageResponseTime(): string
    {
        $avgMinutes = CategorySuggestion::whereNotNull('reviewed_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, reviewed_at)) as avg_minutes')
            ->value('avg_minutes');

        if (!$avgMinutes) return 'N/A';

        $hours = floor($avgMinutes / 60);
        $minutes = $avgMinutes % 60;

        return $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
    }

    /**
     * Fonctionnalités populaires
     */
    private function getPopularFeatures(): array
    {
        return [
            ['name' => 'Génération IA', 'usage' => Article::whereNotNull('ai_generated')->count()],
            ['name' => 'Traduction auto', 'usage' => Article::whereJsonLength('translations', '>', 1)->count()],
            ['name' => 'Catégories globales', 'usage' => DB::table('site_global_categories')->count()],
            ['name' => 'Suggestions IA', 'usage' => CategorySuggestion::count()],
        ];
    }
} 