<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Language;

class SiteController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sites = Site::where('user_id', Auth::id())
            ->with('languages')
            ->get()
            ->map(function ($site) {
                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'domain' => $site->domain,
                    'platform_type' => $site->platform_type,
                    'is_active' => $site->is_active,
                    'description' => $site->description,
                    'primary_color' => $site->primary_color,
                    'secondary_color' => $site->secondary_color,
                    'accent_color' => $site->accent_color,
                    'languages' => $site->languages->map(fn($lang) => [
                        'id' => $lang->id,
                        'name' => $lang->name,
                        'flag_url' => $lang->flag_url,
                    ]),
                    'api_key' => $site->api_key,
                    'webhook_url' => config('app.url') . '/webhooks/receive/' . $site->webhook_token,
                ];
            });
        
        $languages = Language::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($language) => [
                'id' => $language->id,
                'name' => $language->name,
                'flag_url' => $language->flag_url,
            ]);
        
        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'availableLanguages' => $languages
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $languages = Language::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($language) => [
                'id' => $language->id,
                'name' => $language->name,
                'flag_url' => $language->flag_url,
            ]);

        return Inertia::render('Sites/Create', [
            'availableLanguages' => $languages,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'platform_type' => 'required|in:laravel,wordpress,prestashop',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
            'primary_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'secondary_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'accent_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'languages' => 'required|array|min:1',
            'languages.*' => 'required|exists:languages,id',
        ]);

        $site = Site::create([
            'name' => $validated['name'],
            'domain' => $validated['url'],
            'platform_type' => $validated['platform_type'],
            'api_key' => Str::random(32),
            'webhook_token' => Str::uuid(),
            'is_active' => $validated['status'] === 'active',
            'description' => $validated['description'],
            'primary_color' => $validated['primary_color'],
            'secondary_color' => $validated['secondary_color'],
            'accent_color' => $validated['accent_color'],
            'user_id' => Auth::id(),
        ]);

        $site->languages()->sync($validated['languages']);

        return redirect()->route('sites.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Site $site)
    {
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $languages = Language::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn($language) => [
                'id' => $language->id,
                'name' => $language->name,
                'flag_url' => $language->flag_url,
            ]);

        $webhookUrl = config('app.url') . '/webhooks/receive/' . $site->webhook_token;

        return Inertia::render('Sites/Edit', [
            'site' => $site->load('languages'),
            'availableLanguages' => $languages,
            'webhookUrl' => $webhookUrl,
            'apiKey' => $site->api_key,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Site $site)
    {
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url',
            'platform_type' => 'required|in:laravel,wordpress,prestashop',
            'status' => 'required|in:active,inactive',
            'description' => 'nullable|string',
            'primary_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'secondary_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'accent_color' => 'required|string|regex:/^#[a-fA-F0-9]{6}$/',
            'languages' => 'required|array|min:1',
            'languages.*' => 'required|exists:languages,id',
        ]);

        $site->update([
            'name' => $validated['name'],
            'domain' => $validated['url'],
            'platform_type' => $validated['platform_type'],
            'is_active' => $validated['status'] === 'active',
            'description' => $validated['description'],
            'primary_color' => $validated['primary_color'],
            'secondary_color' => $validated['secondary_color'],
            'accent_color' => $validated['accent_color'],
        ]);

        $site->languages()->sync($validated['languages']);

        return redirect()->route('sites.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Site $site)
    {
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $site->delete();

        return redirect()->back()->with('success', 'Site deleted successfully.');
    }

    public function colors($id)
    {
        $site = Site::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();
            
        return response()->json([
            'primary_color' => $site->primary_color,
            'secondary_color' => $site->secondary_color,
            'accent_color' => $site->accent_color,
        ]);
    }

    public function getColors(Site $site)
    {
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }
            
        return response()->json([
            'primary_color' => $site->primary_color,
            'secondary_color' => $site->secondary_color,
            'accent_color' => $site->accent_color,
        ]);
    }

    public function getCategories(Site $site, Request $request)
    {
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        // Récupérer le paramètre de langue depuis la requête
        $languageCode = $request->get('language');

        // Utiliser la relation many-to-many via la table pivot category_site
        $query = $site->categories();

        // Si un code de langue est fourni, filtrer les catégories par langue
        if ($languageCode) {
            // Supposons qu'on ajoute une colonne language_code dans la table categories
            // ou qu'on utilise une relation avec les langues
            $query->where('language_code', $languageCode);
        }

        $categories = $query->get(['categories.id', 'categories.name', 'categories.language_code']);
        
        return response()->json($categories);
    }

    public function getLanguages(Site $site)
    {
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $languages = $site->languages()->get()->map(function ($language) {
            $aiLanguageCode = $this->mapToAILanguageCode($language->slug);
            
            return [
                'id' => $language->id,
                'code' => $aiLanguageCode,
                'name' => $language->name,
                'flag' => $language->flag_url ?: $this->getLanguageFlag($aiLanguageCode),
            ];
        });
        
        return response()->json($languages);
    }

    private function mapToAILanguageCode(string $slug): string
    {
        $mapping = [
            'francais' => 'fr',
            'french' => 'fr',
            'fr' => 'fr',
            'english' => 'en',
            'anglais' => 'en',
            'en' => 'en',
            'spanish' => 'es',
            'espagnol' => 'es',
            'es' => 'es',
            'german' => 'de',
            'allemand' => 'de',
            'de' => 'de',
            'italian' => 'it',
            'italien' => 'it',
            'it' => 'it',
            'portuguese' => 'pt',
            'portugais' => 'pt',
            'pt' => 'pt',
            'dutch' => 'nl',
            'neerlandais' => 'nl',
            'nl' => 'nl',
            'russian' => 'ru',
            'russe' => 'ru',
            'ru' => 'ru',
            'japanese' => 'ja',
            'japonais' => 'ja',
            'ja' => 'ja',
            'chinese' => 'zh',
            'chinois' => 'zh',
            'zh' => 'zh',
        ];

        return $mapping[strtolower($slug)] ?? 'fr';
    }

    private function getLanguageFlag(string $code): string
    {
        $flags = [
            'fr' => '🇫🇷',
            'en' => '🇬🇧',
            'es' => '🇪🇸',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            'nl' => '🇳🇱',
            'ru' => '🇷🇺',
            'ja' => '🇯🇵',
            'zh' => '🇨🇳',
        ];

        return $flags[$code] ?? '🌐';
    }
}
