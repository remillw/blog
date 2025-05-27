<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ArticleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(Article::class, 'article');
    }

    public function index()
    {
        // Pour l'instant, récupérer tous les articles de l'utilisateur
        // TODO: Implémenter la logique de site actuel plus tard
        $userSiteIds = Site::where('user_id', Auth::id())->pluck('id');
        
        $articles = Article::with(['author', 'categories', 'tags'])
            ->whereIn('site_id', $userSiteIds)
            ->latest()
            ->paginate(10);

        // Récupérer les catégories via la relation many-to-many avec les sites de l'utilisateur
        $categories = Category::whereHas('sites', function ($query) use ($userSiteIds) {
            $query->whereIn('sites.id', $userSiteIds);
        })->orderBy('name')->get();

        // Récupérer les tags des sites de l'utilisateur
        $tags = Tag::whereIn('site_id', $userSiteIds)
            ->orderBy('name')
            ->get();

        return Inertia::render('Articles/Index', [
            'articles' => $articles,
            'categories' => $categories,
            'tags' => $tags,
        ]);
    }

    public function create()
    {
        // Récupérer les catégories via la relation many-to-many avec les sites de l'utilisateur
        $userSiteIds = Site::where('user_id', Auth::id())->pluck('id');
        $categories = Category::whereHas('sites', function ($query) use ($userSiteIds) {
            $query->whereIn('sites.id', $userSiteIds);
        })->orderBy('name')->get();

        // Récupérer les tags des sites de l'utilisateur
        $tags = Tag::whereIn('site_id', $userSiteIds)
            ->orderBy('name')
            ->get();

        $sites = Site::where('user_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Articles/Create', [
            'categories' => $categories,
            'tags' => $tags,
            'sites' => $sites,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image_url' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'canonical_url' => 'nullable|url',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'author_name' => 'nullable|string|max:255',
            'author_bio' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'site_id' => 'required|exists:sites,id',
        ]);

        // Vérifier que le site appartient à l'utilisateur connecté
        $site = Site::where('id', $validated['site_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $article = Article::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        if (!empty($validated['categories'])) {
            $article->categories()->sync($validated['categories']);
        }

        if (!empty($validated['tags'])) {
            $article->tags()->sync($validated['tags']);
        }

        return redirect()->route('articles.index')
            ->with('success', 'Article created successfully.');
    }

    public function edit(Article $article)
    {
        $article->load(['categories', 'tags']);

        // Récupérer les catégories via la relation many-to-many avec les sites de l'utilisateur
        $userSiteIds = Site::where('user_id', Auth::id())->pluck('id');
        $categories = Category::whereHas('sites', function ($query) use ($userSiteIds) {
            $query->whereIn('sites.id', $userSiteIds);
        })->orderBy('name')->get();

        // Récupérer les tags des sites de l'utilisateur
        $tags = Tag::whereIn('site_id', $userSiteIds)
            ->orderBy('name')
            ->get();

        // Récupérer le site de l'article pour les couleurs
        $site = Site::find($article->site_id);

        return Inertia::render('Articles/Edit', [
            'article' => $article,
            'categories' => $categories,
            'tags' => $tags,
            'site' => $site ? [
                'primary_color' => $site->primary_color,
                'secondary_color' => $site->secondary_color,
                'accent_color' => $site->accent_color,
            ] : null,
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image_url' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'canonical_url' => 'nullable|url',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'author_name' => 'nullable|string|max:255',
            'author_bio' => 'nullable|string',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
            'site_id' => 'required|exists:sites,id',
        ]);

        // Vérifier que le site appartient à l'utilisateur connecté
        $site = Site::where('id', $validated['site_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $article->update($validated);

        if (isset($validated['categories'])) {
            $article->categories()->sync($validated['categories']);
        }

        if (isset($validated['tags'])) {
            $article->tags()->sync($validated['tags']);
        }

        return redirect()->route('articles.index')
            ->with('success', 'Article updated successfully.');
    }

    public function destroy(Article $article)
    {
        $article->delete();

        return redirect()->route('articles.index')
            ->with('success', 'Article deleted successfully.');
    }

    /**
     * Handle image upload from EditorJS
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:2048', // Max 2MB
        ]);

        $path = $request->file('image')->store('editor-images', 'public');

        return response()->json([
            'success' => 1,
            'file' => [
                'url' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * Fetch metadata from a URL for EditorJS LinkTool
     */
    public function fetchUrlMetadata(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        try {
            $html = file_get_contents($request->url);
            $doc = new \DOMDocument();
            @$doc->loadHTML($html);
            $xpath = new \DOMXPath($doc);

            // Get title
            $title = $xpath->query('//title')->item(0)?->textContent;
            
            // Get description
            $description = $xpath->query('//meta[@name="description"]')->item(0)?->getAttribute('content');
            
            // Get image
            $image = $xpath->query('//meta[@property="og:image"]')->item(0)?->getAttribute('content');
            if (!$image) {
                $image = $xpath->query('//meta[@name="twitter:image"]')->item(0)?->getAttribute('content');
            }

            return response()->json([
                'success' => 1,
                'meta' => [
                    'title' => $title,
                    'description' => $description,
                    'image' => [
                        'url' => $image
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0,
                'message' => 'Could not fetch URL metadata'
            ], 400);
        }
    }
} 