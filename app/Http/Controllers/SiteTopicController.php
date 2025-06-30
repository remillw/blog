<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SiteTopicController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
    }

    /**
     * Récupère les sujets d'un site
     */
    public function index(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = $site->topics()->orderBy('priority', 'desc')->orderBy('created_at', 'desc');

        // Filtre par langue si spécifié
        if ($request->has('language') && $request->language) {
            $query->byLanguage($request->language);
        }

        // Filtre par statut actif
        if ($request->boolean('active_only')) {
            $query->active();
        }

        $topics = $query->get();

        return response()->json([
            'topics' => $topics->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'keywords' => $topic->keywords,
                    'categories' => $topic->categories,
                    'language_code' => $topic->language_code,
                    'priority' => $topic->priority,
                    'is_active' => $topic->is_active,
                    'usage_count' => $topic->usage_count,
                    'last_used_at' => $topic->last_used_at,
                    'source' => $topic->source,
                    'created_at' => $topic->created_at,
                ];
            })
        ]);
    }

    /**
     * Crée un nouveau sujet
     */
    public function store(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:100',
            'language_code' => 'required|string|max:10',
            'priority' => 'integer|min:1|max:5',
            'is_active' => 'boolean',
            'ai_context' => 'nullable|string|max:500',
        ]);

        $topic = $site->topics()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'keywords' => $validated['keywords'],
            'categories' => $validated['categories'] ?? [],
            'language_code' => $validated['language_code'],
            'priority' => $validated['priority'] ?? 3,
            'is_active' => $validated['is_active'] ?? true,
            'ai_context' => $validated['ai_context'],
            'source' => 'manual',
        ]);

        return response()->json([
            'success' => true,
            'topic' => $topic,
            'message' => 'Sujet créé avec succès'
        ]);
    }

    /**
     * Met à jour un sujet
     */
    public function update(Request $request, Site $site, SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au site et que le sujet appartient au site
        if (Auth::id() !== $site->user_id || $topic->site_id !== $site->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:100',
            'language_code' => 'required|string|max:10',
            'priority' => 'integer|min:1|max:5',
            'is_active' => 'boolean',
            'ai_context' => 'nullable|string|max:500',
        ]);

        $topic->update($validated);

        return response()->json([
            'success' => true,
            'topic' => $topic,
            'message' => 'Sujet mis à jour avec succès'
        ]);
    }

    /**
     * Supprime un sujet
     */
    public function destroy(Site $site, SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au site et que le sujet appartient au site
        if (Auth::id() !== $site->user_id || $topic->site_id !== $site->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $topic->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sujet supprimé avec succès'
        ]);
    }

    /**
     * Génère des sujets automatiquement avec l'IA
     */
    public function generateWithAI(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'language_code' => 'required|string|max:10',
            'count' => 'integer|min:1|max:20',
            'focus_area' => 'nullable|string|max:200',
        ]);

        $languageCode = $validated['language_code'];
        $count = $validated['count'] ?? 10;
        $focusArea = $validated['focus_area'] ?? '';

        try {
            $generatedTopics = $this->generateTopicsWithAI($site, $languageCode, $count, $focusArea);
            
            if (empty($generatedTopics)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de générer des sujets avec l\'IA'
                ], 500);
            }

            // Sauvegarder les sujets générés
            $savedTopics = [];
            foreach ($generatedTopics as $topicData) {
                $topic = $site->topics()->create([
                    'title' => $topicData['title'],
                    'description' => $topicData['description'],
                    'keywords' => $topicData['keywords'],
                    'categories' => $topicData['categories'] ?? [],
                    'language_code' => $languageCode,
                    'priority' => $topicData['priority'] ?? 3,
                    'is_active' => true,
                    'source' => 'ai_generated',
                    'ai_context' => $topicData['ai_context'] ?? '',
                ]);
                $savedTopics[] = $topic;
            }

            return response()->json([
                'success' => true,
                'topics' => $savedTopics,
                'message' => count($savedTopics) . ' sujets générés avec succès'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating topics with AI', [
                'site_id' => $site->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Génère des sujets avec l'IA
     */
    public function generateTopicsWithAI(Site $site, string $languageCode, int $count, string $focusArea = ''): array
    {
        $prompt = $this->buildTopicGenerationPrompt($site, $languageCode, $count, $focusArea);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.openai.key'),
            'Content-Type' => 'application/json',
        ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un expert en marketing de contenu et SEO. Tu dois générer des sujets d\'articles pertinents et optimisés pour un site web spécifique.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.8,
        ]);

        if (!$response->successful()) {
            throw new \Exception("Erreur API OpenAI: " . $response->body());
        }

        $aiResponse = $response->json();
        $content = $aiResponse['choices'][0]['message']['content'] ?? null;

        if (!$content) {
            throw new \Exception("Réponse vide de l'IA");
        }

        return $this->parseTopicsFromAIResponse($content);
    }

    /**
     * Construit le prompt pour la génération de sujets
     */
    public function buildTopicGenerationPrompt(Site $site, string $languageCode, int $count, string $focusArea): string
    {
        $prompt = "Génère {$count} sujets d'articles pertinents pour le site '{$site->name}'.\n\n";
        
        if ($site->description) {
            $prompt .= "Description du site: {$site->description}\n\n";
        }
        
        if ($site->auto_content_guidelines) {
            $prompt .= "Directives de contenu: {$site->auto_content_guidelines}\n\n";
        }
        
        if ($focusArea) {
            $prompt .= "Zone de focus spécifique: {$focusArea}\n\n";
        }

        $prompt .= "Critères pour chaque sujet:\n";
        $prompt .= "- Titre accrocheur et SEO-friendly\n";
        $prompt .= "- Description claire du contenu à couvrir\n";
        $prompt .= "- 5-8 mots-clés pertinents\n";
        $prompt .= "- 2-3 catégories suggérées\n";
        $prompt .= "- Priorité de 1 à 5 (5 = haute priorité)\n\n";

        $prompt .= "Format de réponse JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "topics": ['."\n";
        $prompt .= "    {\n";
        $prompt .= '      "title": "Titre du sujet",'."\n";
        $prompt .= '      "description": "Description détaillée",'."\n";
        $prompt .= '      "keywords": ["mot-clé1", "mot-clé2", ...],'."\n";
        $prompt .= '      "categories": ["catégorie1", "catégorie2"],'."\n";
        $prompt .= '      "priority": 3,'."\n";
        $prompt .= '      "ai_context": "Contexte spécifique pour ce sujet"'."\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n";

        return $prompt;
    }

    /**
     * Parse la réponse de l'IA pour extraire les sujets
     */
    public function parseTopicsFromAIResponse(string $content): array
    {
        // Extraire le JSON de la réponse
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $jsonString = $matches[0];
            $data = json_decode($jsonString, true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($data['topics'])) {
                return $data['topics'];
            }
        }

        throw new \Exception("Format de réponse IA invalide");
    }

    /**
     * Importe des sujets depuis un fichier CSV/JSON
     */
    public function import(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'topics_data' => 'required|string',
            'format' => 'required|in:json,csv',
            'language_code' => 'required|string|max:10',
        ]);

        try {
            $topicsData = $validated['format'] === 'json' 
                ? json_decode($validated['topics_data'], true)
                : $this->parseCsvTopics($validated['topics_data']);

            if (!$topicsData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format de données invalide'
                ], 400);
            }

            $savedTopics = [];
            foreach ($topicsData as $topicData) {
                if (!isset($topicData['title']) || !isset($topicData['keywords'])) {
                    continue; // Ignorer les entrées invalides
                }

                $topic = $site->topics()->create([
                    'title' => $topicData['title'],
                    'description' => $topicData['description'] ?? '',
                    'keywords' => is_array($topicData['keywords']) 
                        ? $topicData['keywords'] 
                        : explode(',', $topicData['keywords']),
                    'categories' => $topicData['categories'] ?? [],
                    'language_code' => $validated['language_code'],
                    'priority' => $topicData['priority'] ?? 3,
                    'is_active' => $topicData['is_active'] ?? true,
                    'source' => 'manual',
                    'ai_context' => $topicData['ai_context'] ?? '',
                ]);
                $savedTopics[] = $topic;
            }

            return response()->json([
                'success' => true,
                'topics' => $savedTopics,
                'message' => count($savedTopics) . ' sujets importés avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'import : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse un CSV en format topics
     */
    private function parseCsvTopics(string $csvData): array
    {
        $lines = str_getcsv($csvData, "\n");
        $topics = [];
        
        foreach ($lines as $index => $line) {
            if ($index === 0) continue; // Ignorer l'en-tête
            
            $data = str_getcsv($line);
            if (count($data) >= 2) {
                $topics[] = [
                    'title' => $data[0],
                    'keywords' => isset($data[1]) ? explode(';', $data[1]) : [],
                    'description' => $data[2] ?? '',
                    'categories' => isset($data[3]) ? explode(';', $data[3]) : [],
                    'priority' => isset($data[4]) ? (int)$data[4] : 3,
                ];
            }
        }
        
        return $topics;
    }

    // **MÉTHODES WEB POUR INERTIA**

    /**
     * Génère des sujets automatiquement avec l'IA (version web)
     */
    public function generateWithAIWeb(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'language_code' => 'required|string|max:10',
            'count' => 'integer|min:1|max:50',
            'focus_area' => 'nullable|string|max:200',
        ]);

        $languageCode = $validated['language_code'];
        $count = $validated['count'] ?? 20;
        $focusArea = $validated['focus_area'] ?? '';

        try {
            $generatedTopics = $this->generateTopicsWithAI($site, $languageCode, $count, $focusArea);
            
            if (empty($generatedTopics)) {
                return back()->with('error', 'Impossible de générer des sujets avec l\'IA');
            }

            // Sauvegarder les sujets générés
            $savedTopics = [];
            foreach ($generatedTopics as $topicData) {
                $topic = $site->topics()->create([
                    'title' => $topicData['title'],
                    'description' => $topicData['description'],
                    'keywords' => $topicData['keywords'],
                    'categories' => $topicData['categories'] ?? [],
                    'language_code' => $languageCode,
                    'priority' => $topicData['priority'] ?? 3,
                    'is_active' => true,
                    'source' => 'ai_generated',
                    'ai_context' => $topicData['ai_context'] ?? '',
                ]);
                $savedTopics[] = $topic;
            }

            return back()->with('success', count($savedTopics) . ' sujets générés avec succès');

        } catch (\Exception $e) {
            Log::error('Error generating topics with AI', [
                'site_id' => $site->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Erreur lors de la génération : ' . $e->getMessage());
        }
    }

    /**
     * Récupère les sujets d'un site (version web)
     */
    public function indexWeb(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $query = $site->topics()->orderBy('priority', 'desc')->orderBy('created_at', 'desc');

        // Filtre par langue si spécifié
        if ($request->has('language') && $request->language) {
            $query->byLanguage($request->language);
        }

        $topics = $query->get();

        return response()->json([
            'topics' => $topics->map(function ($topic) {
                return [
                    'id' => $topic->id,
                    'title' => $topic->title,
                    'description' => $topic->description,
                    'keywords' => $topic->keywords,
                    'categories' => $topic->categories,
                    'language_code' => $topic->language_code,
                    'priority' => $topic->priority,
                    'is_active' => $topic->is_active,
                    'usage_count' => $topic->usage_count,
                    'last_used_at' => $topic->last_used_at,
                    'source' => $topic->source,
                    'created_at' => $topic->created_at,
                ];
            })
        ]);
    }

    /**
     * Crée un nouveau sujet (version web)
     */
    public function storeWeb(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:100',
            'language_code' => 'required|string|max:10',
            'priority' => 'integer|min:1|max:5',
            'is_active' => 'boolean',
            'ai_context' => 'nullable|string|max:500',
        ]);

        $topic = $site->topics()->create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'keywords' => $validated['keywords'],
            'categories' => $validated['categories'] ?? [],
            'language_code' => $validated['language_code'],
            'priority' => $validated['priority'] ?? 3,
            'is_active' => $validated['is_active'] ?? true,
            'ai_context' => $validated['ai_context'],
            'source' => 'manual',
        ]);

        return back()->with('success', 'Sujet créé avec succès');
    }

    /**
     * Supprime un sujet (version web)
     */
    public function destroyWeb(Site $site, SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au site et que le sujet appartient au site
        if (Auth::id() !== $site->user_id || $topic->site_id !== $site->id) {
            abort(403);
        }

        $topic->delete();

        return back()->with('success', 'Sujet supprimé avec succès');
    }

    /**
     * Importe des sujets depuis un fichier CSV/JSON (version web)
     */
    public function importWeb(Request $request, Site $site)
    {
        // Vérifier que l'utilisateur a accès au site
        if (Auth::id() !== $site->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'topics_data' => 'required|string',
            'format' => 'required|in:json,csv',
            'language_code' => 'required|string|max:10',
        ]);

        try {
            $topicsData = $validated['format'] === 'json' 
                ? json_decode($validated['topics_data'], true)
                : $this->parseCsvTopics($validated['topics_data']);

            if (!$topicsData) {
                return back()->with('error', 'Format de données invalide');
            }

            $savedTopics = [];
            foreach ($topicsData as $topicData) {
                if (!isset($topicData['title']) || !isset($topicData['keywords'])) {
                    continue; // Ignorer les entrées invalides
                }

                $topic = $site->topics()->create([
                    'title' => $topicData['title'],
                    'description' => $topicData['description'] ?? '',
                    'keywords' => is_array($topicData['keywords']) 
                        ? $topicData['keywords'] 
                        : explode(',', $topicData['keywords']),
                    'categories' => $topicData['categories'] ?? [],
                    'language_code' => $validated['language_code'],
                    'priority' => $topicData['priority'] ?? 3,
                    'is_active' => $topicData['is_active'] ?? true,
                    'source' => 'manual',
                    'ai_context' => $topicData['ai_context'] ?? '',
                ]);
                $savedTopics[] = $topic;
            }

            return back()->with('success', count($savedTopics) . ' sujets importés avec succès');

        } catch (\Exception $e) {
            return back()->with('error', 'Erreur lors de l\'import : ' . $e->getMessage());
        }
    }
}
