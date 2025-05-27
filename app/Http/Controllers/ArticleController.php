<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\Site;
use App\Services\ArticleRecoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
            'content_html' => 'nullable|string', // HTML converti côté frontend
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

        // Générer un slug unique
        $slug = Str::slug($validated['title']);
        $originalSlug = $slug;
        $counter = 1;
        while (Article::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        $article = Article::create([
            ...$validated,
            'user_id' => Auth::id(),
            'slug' => $slug,
            'source' => 'created',
            'external_id' => Str::uuid(), // ID unique pour identifier l'article côté externe
            'is_synced' => false, // Pas encore envoyé via webhook
        ]);

        if (!empty($validated['categories'])) {
            $article->categories()->sync($validated['categories']);
        }

        if (!empty($validated['tags'])) {
            $article->tags()->sync($validated['tags']);
        }

        // Envoyer le webhook si l'article est publié
        if ($validated['status'] === 'published') {
            $this->sendWebhook($article);
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

        $sites = Site::where('user_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Articles/Edit', [
            'article' => $article,
            'categories' => $categories,
            'tags' => $tags,
            'sites' => $sites,
        ]);
    }

    public function update(Request $request, Article $article)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'content_html' => 'nullable|string', // HTML converti côté frontend
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

        // Mettre à jour le slug si le titre a changé
        if ($article->title !== $validated['title']) {
            $slug = Str::slug($validated['title']);
            $originalSlug = $slug;
            $counter = 1;
            while (Article::where('slug', $slug)->where('id', '!=', $article->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $validated['slug'] = $slug;
        }

        // Marquer comme non synchronisé si le contenu a changé
        if ($article->content !== $validated['content'] || $article->content_html !== $validated['content_html']) {
            $validated['is_synced'] = false;
        }

        $article->update($validated);

        if (isset($validated['categories'])) {
            $article->categories()->sync($validated['categories']);
        }

        if (isset($validated['tags'])) {
            $article->tags()->sync($validated['tags']);
        }

        // Envoyer le webhook si l'article est publié et a été modifié
        if ($validated['status'] === 'published' && !$article->is_synced) {
            $this->sendWebhook($article);
        }

        return redirect()->route('articles.index')
            ->with('success', 'Article updated successfully.');
    }

    public function destroy(Article $article)
    {
        // Envoyer un webhook de suppression si l'article était synchronisé
        if ($article->is_synced && $article->external_id) {
            $this->sendDeleteWebhook($article);
        }

        $article->delete();

        return redirect()->route('articles.index')
            ->with('success', 'Article deleted successfully.');
    }

    /**
     * Recevoir un webhook d'un article modifié côté client
     */
    public function receiveWebhook(Request $request)
    {
        $validated = $request->validate([
            'external_id' => 'required|string',
            'title' => 'required|string',
            'content' => 'required|string', // HTML reçu
            'excerpt' => 'nullable|string',
            'status' => 'required|in:draft,published,scheduled',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'featured_image_url' => 'nullable|string',
            'author_name' => 'nullable|string',
            'author_bio' => 'nullable|string',
        ]);

        try {
            // Chercher l'article par external_id ou le créer s'il n'existe pas
            $article = Article::where('external_id', $validated['external_id'])->first();

            if ($article) {
                // Article existant : mise à jour
                $article->update([
                    'title' => $validated['title'],
                    'content' => null, // On efface l'EditorJS, il sera régénéré à l'édition
                    'content_html' => $validated['content'], // HTML reçu
                    'excerpt' => $validated['excerpt'],
                    'status' => $validated['status'],
                    'meta_title' => $validated['meta_title'],
                    'meta_description' => $validated['meta_description'],
                    'featured_image_url' => $validated['featured_image_url'],
                    'author_name' => $validated['author_name'],
                    'author_bio' => $validated['author_bio'],
                    'source' => 'webhook',
                    'webhook_received_at' => now(),
                    'webhook_data' => $validated,
                    'is_synced' => true,
                ]);

                Log::info('Article updated via webhook', ['article_id' => $article->id, 'external_id' => $validated['external_id']]);
            } else {
                // Nouvel article reçu via webhook
                // Il faut déterminer le site et l'utilisateur (logique à adapter selon vos besoins)
                $site = Site::first(); // À adapter selon votre logique
                
                $slug = Str::slug($validated['title']);
                $originalSlug = $slug;
                $counter = 1;
                while (Article::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }

                $article = Article::create([
                    'title' => $validated['title'],
                    'slug' => $slug,
                    'content' => null, // Sera généré à l'édition
                    'content_html' => $validated['content'],
                    'excerpt' => $validated['excerpt'],
                    'status' => $validated['status'],
                    'meta_title' => $validated['meta_title'],
                    'meta_description' => $validated['meta_description'],
                    'featured_image_url' => $validated['featured_image_url'],
                    'author_name' => $validated['author_name'],
                    'author_bio' => $validated['author_bio'],
                    'site_id' => $site->id,
                    'user_id' => $site->user_id,
                    'external_id' => $validated['external_id'],
                    'source' => 'webhook',
                    'webhook_received_at' => now(),
                    'webhook_data' => $validated,
                    'is_synced' => true,
                ]);

                Log::info('New article created via webhook', ['article_id' => $article->id, 'external_id' => $validated['external_id']]);
            }

            return response()->json(['success' => true, 'article_id' => $article->id]);

        } catch (\Exception $e) {
            Log::error('Webhook reception failed', ['error' => $e->getMessage(), 'data' => $validated]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Envoyer un webhook pour créer/mettre à jour un article
     */
    private function sendWebhook(Article $article)
    {
        try {
            $site = $article->site;
            
            if (!$site->webhook_url) {
                Log::warning('No webhook URL configured for site', ['site_id' => $site->id]);
                return;
            }

            $webhookData = [
                'action' => 'upsert',
                'external_id' => $article->external_id,
                'title' => $article->title,
                'content' => $article->content_html, // Envoyer le HTML
                'excerpt' => $article->excerpt,
                'status' => $article->status,
                'meta_title' => $article->meta_title,
                'meta_description' => $article->meta_description,
                'featured_image_url' => $article->featured_image_url,
                'author_name' => $article->author_name,
                'author_bio' => $article->author_bio,
                'published_at' => $article->published_at?->toISOString(),
                'categories' => $article->categories->pluck('name')->toArray(),
                'tags' => $article->tags->pluck('name')->toArray(),
            ];

            $response = Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $site->api_key,
                    'Content-Type' => 'application/json',
                ])
                ->post($site->webhook_url, $webhookData);

            if ($response->successful()) {
                $article->update([
                    'webhook_sent_at' => now(),
                    'is_synced' => true,
                ]);
                Log::info('Webhook sent successfully', ['article_id' => $article->id]);
            } else {
                Log::error('Webhook failed', [
                    'article_id' => $article->id,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Webhook sending failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Envoyer un webhook de suppression
     */
    private function sendDeleteWebhook(Article $article)
    {
        try {
            $site = $article->site;
            
            if (!$site->webhook_url) {
                return;
            }

            $webhookData = [
                'action' => 'delete',
                'external_id' => $article->external_id,
            ];

            Http::timeout(30)
                ->withHeaders([
                    'X-API-Key' => $site->api_key,
                    'Content-Type' => 'application/json',
                ])
                ->post($site->webhook_url, $webhookData);

            Log::info('Delete webhook sent', ['article_id' => $article->id]);

        } catch (\Exception $e) {
            Log::error('Delete webhook failed', ['article_id' => $article->id, 'error' => $e->getMessage()]);
        }
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

    /**
     * Récupère un article supprimé depuis le SaaS
     */
    public function recover(Request $request)
    {
        $validated = $request->validate([
            'article_id' => 'nullable|integer',
            'external_id' => 'nullable|string',
            'title' => 'nullable|string',
        ]);

        $recoveryService = new ArticleRecoveryService();

        if (!$recoveryService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Service de récupération non configuré'
            ], 500);
        }

        $article = null;

        // Essayer différentes méthodes de récupération
        if (!empty($validated['external_id'])) {
            $article = $recoveryService->recoverArticleByExternalId($validated['external_id']);
        } elseif (!empty($validated['article_id'])) {
            $article = $recoveryService->recoverArticleById($validated['article_id']);
        } elseif (!empty($validated['title'])) {
            $article = $recoveryService->recoverArticleByTitle($validated['title']);
        }

        if ($article) {
            return response()->json([
                'success' => true,
                'message' => 'Article récupéré avec succès',
                'article' => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Article non trouvé sur le SaaS'
        ], 404);
    }
} 