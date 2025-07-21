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
use Illuminate\Support\Facades\Storage;

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
        // Debug de l'image si présente
        \Log::info('ArticleController::store - Request data:', [
            'has_file' => $request->hasFile('cover_image'),
            'all_files' => $request->allFiles(),
            'input_cover_image' => $request->input('cover_image'),
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type')
        ]);

        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            \Log::info('Cover image upload attempt:', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'client_mime_type' => $file->getClientMimeType(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError(),
                'error_message' => $file->getErrorMessage(),
                'temp_path' => $file->getPathname(),
                'path' => $file->path(),
                'extension' => $file->extension(),
                'client_extension' => $file->getClientOriginalExtension()
            ]);
        } else {
            \Log::info('No file received in cover_image field');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'content_html' => 'nullable|string', // HTML converti côté frontend
            'excerpt' => 'nullable|string',
            'cover_image' => 'nullable|string', // Maintenant c'est un chemin vers le fichier déjà uploadé
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string', // Seulement string maintenant
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

        // Gérer l'image de couverture (déjà uploadée via /upload/cover-image)
        $coverImageUrl = null;
        if (!empty($validated['cover_image'])) {
            // L'image est déjà uploadée, on a juste le chemin
            $coverImageUrl = asset('storage/' . $validated['cover_image']);
        }

        $article = Article::create([
            ...$validated,
            'user_id' => Auth::id(),
            'slug' => $slug,
            'cover_image' => $coverImageUrl,
            'source' => 'created',
            'external_id' => Str::uuid(),
            'is_synced' => false, 
        ]);

        if (!empty($validated['categories'])) {
            $article->categories()->sync($validated['categories']);
        }

        if (!empty($validated['tags'])) {
            $article->tags()->sync($validated['tags']);
        }

        if ($validated['status'] === 'published') {
            $this->sendWebhook($article);
        }

        return redirect()->route('articles.index')
            ->with('success', 'Article created successfully.');
    }

    public function edit(Article $article)
    {
        $article->load(['categories', 'tags', 'site.languages']);

        // Récupérer toutes les catégories disponibles pour ce site avec leurs langues
        $categories = Category::whereHas('sites', function ($query) use ($article) {
            $query->where('sites.id', $article->site_id);
        })->orderBy('name')->get();

        // Récupérer les tags du site de l'article
        $tags = Tag::where('site_id', $article->site_id)
            ->orderBy('name')
            ->get();

        // Récupérer tous les sites de l'utilisateur
        $sites = Site::where('user_id', Auth::id())
            ->with(['languages'])
            ->orderBy('name')
            ->get(['id', 'name']);

        // Préparer les données de l'article pour EditorJS
        $articleData = $article->toArray();
        
        // Si le content est du JSON EditorJS, le parser pour l'édition
        if (!empty($article->content)) {
            $decodedContent = json_decode($article->content, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($decodedContent['blocks'])) {
                // C'est du contenu EditorJS
                $articleData['editorjs_content'] = $decodedContent;
                $articleData['content_type'] = 'editorjs';
            } else {
                // C'est du contenu texte/HTML classique
                $articleData['content_type'] = 'html';
            }
        }

        // Ajouter les IDs des catégories et tags pour le formulaire
        $articleData['category_ids'] = $article->categories->pluck('id')->toArray();
        $articleData['tag_ids'] = $article->tags->pluck('id')->toArray();

        return Inertia::render('Articles/Edit', [
            'article' => $articleData,
            'categories' => $categories,
            'tags' => $tags,
            'sites' => $sites,
        ]);
    }

    public function update(Request $request, Article $article)
    {
        // Debug de l'image si présente
        if ($request->hasFile('cover_image')) {
            $file = $request->file('cover_image');
            \Log::info('Cover image update attempt:', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'client_mime_type' => $file->getClientMimeType(),
                'is_valid' => $file->isValid(),
                'error' => $file->getError(),
                'temp_path' => $file->getPathname()
            ]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'content_html' => 'nullable|string', 
            'content_type' => 'nullable|string|in:html,editorjs',
            'editorjs_content' => 'nullable|array',
            'excerpt' => 'nullable|string',
            'cover_image' => 'nullable|string', // Maintenant c'est un chemin vers le fichier déjà uploadé
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string', // Seulement string maintenant
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
            'language_code' => 'nullable|string|max:10',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string',
            'og_image' => 'nullable|string',
            'twitter_title' => 'nullable|string|max:255',
            'twitter_description' => 'nullable|string',
            'twitter_image' => 'nullable|string',
        ]);

        // Vérifier que le site appartient à l'utilisateur connecté
        $site = Site::where('id', $validated['site_id'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        // Gérer le contenu selon le type
        $contentData = [];
        if ($validated['content_type'] === 'editorjs' && !empty($validated['editorjs_content'])) {
            // Stocker le contenu EditorJS en JSON
            $contentData['content'] = json_encode($validated['editorjs_content']);
            // Générer le HTML à partir du contenu EditorJS si besoin
            if (!empty($validated['content_html'])) {
                $contentData['content_html'] = $validated['content_html'];
            }
        } else {
            // Contenu HTML classique
            $contentData['content'] = $validated['content'];
            $contentData['content_html'] = $validated['content_html'] ?? $validated['content'];
        }

        // Générer un nouveau slug si le titre a changé
        $slug = $article->slug;
        if ($article->title !== $validated['title']) {
            $newSlug = Str::slug($validated['title']);
            $originalSlug = $newSlug;
            $counter = 1;
            while (Article::where('slug', $newSlug)->where('id', '!=', $article->id)->exists()) {
                $newSlug = $originalSlug . '-' . $counter;
                $counter++;
            }
            $slug = $newSlug;
        }

        // Gérer l'image de couverture (déjà uploadée via /upload/cover-image)
        if (!empty($validated['cover_image'])) {
            // Nouvelle image uploadée
            $validated['cover_image'] = asset('storage/' . $validated['cover_image']);
        } else {
            // Garder l'image existante
            unset($validated['cover_image']);
        }

        // Calculer le word count
        $wordCount = 0;
        if (!empty($contentData['content_html'])) {
            $wordCount = str_word_count(strip_tags($contentData['content_html']));
        } elseif (!empty($contentData['content'])) {
            $wordCount = str_word_count(strip_tags($contentData['content']));
        }

        // Mettre à jour l'article
        $updateData = array_merge($validated, $contentData, [
            'slug' => $slug,
            'word_count' => $wordCount,
            'reading_time' => ceil($wordCount / 200),
            'is_synced' => false, // Marquer comme non synchronisé pour mise à jour
        ]);

        // Supprimer les champs qui ne sont pas dans la table
        unset($updateData['categories'], $updateData['tags'], $updateData['content_type'], $updateData['editorjs_content']);

        $article->update($updateData);

        // Synchroniser les catégories
        if (isset($validated['categories'])) {
            $article->categories()->sync($validated['categories']);
        }

        // Synchroniser les tags
        if (isset($validated['tags'])) {
            $article->tags()->sync($validated['tags']);
        }

        // Envoyer le webhook si l'article est publié
        if ($validated['status'] === 'published') {
            $this->sendWebhook($article);
        }

        return redirect()->route('articles.index')
            ->with('success', 'Article mis à jour avec succès.');
    }

    public function destroy(Article $article)
    {
        // Supprimer l'image de couverture si elle existe (extraire le chemin de l'URL)
        if ($article->cover_image) {
            $imagePath = str_replace(asset('storage/'), '', $article->cover_image);
            $this->deleteCoverImage($imagePath);
        }

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

    /**
     * Handle cover image upload
     */
    public function uploadCoverImage(Request $request)
    {
        $request->validate([
            'cover_image' => 'required|image|max:2048', // Max 2MB
            'article_slug' => 'nullable|string', // Slug de l'article pour nommer le fichier
        ]);

        // Générer un nom temporaire si pas de slug fourni
        $slug = $request->input('article_slug', 'temp-' . time());
        $path = $this->storeCoverImage($request->file('cover_image'), $slug);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => asset('storage/' . $path)
        ]);
    }

    /**
     * Store cover image with proper naming
     */
    private function storeCoverImage($file, $slug)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = $slug . '-cover.' . $extension;
        
        // Créer le chemin avec l'année/mois pour organiser les fichiers
        $directory = 'cover-images/' . date('Y/m');
        $path = $directory . '/' . $filename;
        
        // Vérifier si un fichier avec ce nom existe déjà et ajouter un suffixe si nécessaire
        $counter = 1;
        $originalPath = $path;
        while (Storage::disk('public')->exists($path)) {
            $pathInfo = pathinfo($originalPath);
            $path = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '-' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }
        
        // Stocker le fichier
        Storage::disk('public')->putFileAs($directory, $file, basename($path));
        
        return $path;
    }

    /**
     * Delete cover image
     */
    private function deleteCoverImage($path)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5120', // 5MB max pour les images
        ]);

        try {
            $image = $request->file('image');
            $originalName = $image->getClientOriginalName();
            $extension = $image->getClientOriginalExtension();
            
            // Générer un nom de fichier unique
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
            
            // Stocker l'image dans le dossier posts/content
            $path = $image->storeAs('posts/content', $filename, 'public');

            return response()->json([
                'success' => 1,
                'file' => [
                    'url' => asset('storage/' . $path),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0,
                'message' => 'Erreur lors de l\'upload de l\'image: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload file for EditorJS Attaches tool
     */
    public function uploadFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $size = $file->getSize();
            
            // Générer un nom de fichier unique
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
            
            // Stocker le fichier
            $path = $file->storeAs('editor-files', $filename, 'public');
            
            return response()->json([
                'success' => 1,
                'file' => [
                    'url' => asset('storage/' . $path),
                    'name' => $originalName,
                    'size' => $size,
                    'extension' => $extension,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0,
                'message' => 'Erreur lors de l\'upload du fichier: ' . $e->getMessage()
            ], 500);
        }
    }


}