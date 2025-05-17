<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
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
        $articles = Article::with(['author', 'categories', 'tags'])
            ->where('site_id', Auth::user()->current_site_id)
            ->latest()
            ->paginate(10);

        $categories = Category::where('site_id', Auth::user()->current_site_id)
            ->orderBy('name')
            ->get();

        $tags = Tag::where('site_id', Auth::user()->current_site_id)
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
        $categories = Category::where('site_id', Auth::user()->current_site_id)
            ->orderBy('name')
            ->get();

        $tags = Tag::where('site_id', Auth::user()->current_site_id)
            ->orderBy('name')
            ->get();

        $site = Auth::user()->currentSite;

        return Inertia::render('Articles/Create', [
            'categories' => $categories,
            'tags' => $tags,
            'site' => [
                'primary_color' => $site->primary_color,
                'secondary_color' => $site->secondary_color,
                'accent_color' => $site->accent_color,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|array',
            'canonical_url' => 'nullable|url',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'is_featured' => 'boolean',
            'author_name' => 'nullable|string|max:255',
            'author_bio' => 'nullable|string',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
            'og_image' => 'nullable|string',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string',
            'twitter_image' => 'nullable|string',
            'schema_markup' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

        $article = Article::create([
            ...$validated,
            'site_id' => Auth::user()->current_site_id,
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

        $categories = Category::where('site_id', Auth::user()->current_site_id)
            ->orderBy('name')
            ->get();

        $tags = Tag::where('site_id', Auth::user()->current_site_id)
            ->orderBy('name')
            ->get();

        $site = Auth::user()->currentSite;

        return Inertia::render('Articles/Edit', [
            'article' => $article,
            'categories' => $categories,
            'tags' => $tags,
            'site' => [
                'primary_color' => $site->primary_color,
                'secondary_color' => $site->secondary_color,
                'accent_color' => $site->accent_color,
            ],
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'featured_image' => 'nullable|string',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|array',
            'canonical_url' => 'nullable|url',
            'status' => 'required|in:draft,published,scheduled',
            'published_at' => 'nullable|date',
            'scheduled_at' => 'nullable|date',
            'is_featured' => 'boolean',
            'author_name' => 'nullable|string|max:255',
            'author_bio' => 'nullable|string',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
            'og_image' => 'nullable|string',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string',
            'twitter_image' => 'nullable|string',
            'schema_markup' => 'nullable|array',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:tags,id',
        ]);

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