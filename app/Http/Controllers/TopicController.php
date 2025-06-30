<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteTopic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class TopicController extends Controller
{
    /**
     * Page principale du calendrier éditorial
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Récupérer les sites de l'utilisateur (propriétaire)
        $sites = $user->ownedSites()->with('languages')->get();
        
        // Date par défaut : mois actuel
        $date = $request->get('date', now()->format('Y-m'));
        $selectedDate = Carbon::parse($date . '-01');
        
        // Récupérer les topics pour le mois
        $startDate = $selectedDate->copy()->startOfMonth();
        $endDate = $selectedDate->copy()->endOfMonth();
        
        $topicsQuery = SiteTopic::with(['site'])
            ->whereIn('site_id', $sites->pluck('id'))
            ->where(function($query) use ($startDate, $endDate) {
                // Topics avec scheduled_date dans la période
                $query->whereBetween('scheduled_date', [$startDate, $endDate])
                      // OU topics sans scheduled_date créés dans la période
                      ->orWhere(function($subQuery) use ($startDate, $endDate) {
                          $subQuery->whereNull('scheduled_date')
                                   ->whereBetween('created_at', [$startDate, $endDate]);
                      });
            });
            
        // Filtres
        if ($request->has('site_id') && $request->site_id) {
            $topicsQuery->where('site_id', $request->site_id);
        }
        
        if ($request->has('status') && $request->status) {
            $topicsQuery->byStatus($request->status);
        }
        
        if ($request->has('language') && $request->language) {
            $topicsQuery->byLanguage($request->language);
        }
        
        $topics = $topicsQuery->orderBy('scheduled_date', 'desc')
                             ->orderBy('scheduled_time')
                             ->orderBy('created_at', 'desc')
                             ->get();
        
        // Statistiques
        $stats = [
            'total_topics' => $topics->count(),
            'scheduled' => $topics->where('status', 'scheduled')->count(),
            'published' => $topics->where('status', 'published')->count(),
            'draft' => $topics->where('status', 'draft')->count(),
        ];

        return Inertia::render('Topics/Calendar', [
            'sites' => $sites,
            'topics' => $topics,
            'currentDate' => $selectedDate->format('Y-m'),
            'stats' => $stats,
            'filters' => [
                'site_id' => $request->get('site_id'),
                'status' => $request->get('status'),
                'language' => $request->get('language'),
            ]
        ]);
    }

    /**
     * Créer un nouveau topic
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'keywords' => 'required|array|min:1',
            'keywords.*' => 'string|max:100',
            'categories' => 'nullable|array',
            'categories.*' => 'string|max:100',
            'language_code' => 'required|string|max:10',
            'priority' => 'integer|min:1|max:5',
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'status' => 'required|in:draft,scheduled,published,cancelled',
            'editorial_notes' => 'nullable|string|max:1000',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        // Vérifier que l'utilisateur a accès au site
        $site = Site::where('id', $validated['site_id'])
                   ->where('user_id', Auth::id())
                   ->firstOrFail();

        $topic = SiteTopic::create([
            'site_id' => $site->id,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'keywords' => $validated['keywords'],
            'categories' => $validated['categories'] ?? [],
            'language_code' => $validated['language_code'],
            'priority' => $validated['priority'] ?? 3,
            'scheduled_date' => $validated['scheduled_date'],
            'scheduled_time' => $validated['scheduled_time'],
            'status' => $validated['status'],
            'editorial_notes' => $validated['editorial_notes'],
            'assigned_to_user_id' => $validated['assigned_to_user_id'],
            'source' => 'manual',
            'is_active' => true,
        ]);

        return back()->with('success', 'Topic créé avec succès');
    }

    /**
     * Générer des topics automatiquement avec l'IA
     */
    public function generateWithAI(Request $request)
    {
        $validated = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'language_code' => 'required|string|max:10',
            'count' => 'integer|min:1|max:20',
            'focus_area' => 'nullable|string|max:200',
        ]);

        // Vérifier que l'utilisateur a accès au site
        $site = Site::where('id', $validated['site_id'])
                   ->where('user_id', Auth::id())
                   ->firstOrFail();

        try {
            // Vérifier la configuration OpenAI
            if (!config('services.openai.key')) {
                return back()->with('error', 'Configuration OpenAI manquante (OPENAI_API_KEY non définie)');
            }

            \Log::info('Starting AI topic generation', [
                'site_id' => $site->id,
                'language' => $validated['language_code'],
                'count' => $validated['count'] ?? 10,
                'focus_area' => $validated['focus_area'] ?? 'none'
            ]);

            // Utiliser la méthode existante du SiteTopicController
            $siteTopicController = app(\App\Http\Controllers\SiteTopicController::class);
            $generatedTopics = $siteTopicController->generateTopicsWithAI(
                $site, 
                $validated['language_code'], 
                $validated['count'] ?? 10, 
                $validated['focus_area'] ?? ''
            );
            
            \Log::info('AI generation completed', [
                'site_id' => $site->id,
                'topics_generated' => count($generatedTopics)
            ]);

            if (empty($generatedTopics)) {
                return back()->with('error', 'L\'IA n\'a pas pu générer de sujets. Vérifiez votre configuration OpenAI.');
            }

            // Sauvegarder les sujets générés
            $savedTopics = [];
            foreach ($generatedTopics as $topicData) {
                $topic = $site->topics()->create([
                    'title' => $topicData['title'],
                    'description' => $topicData['description'],
                    'keywords' => $topicData['keywords'],
                    'categories' => $topicData['categories'] ?? [],
                    'language_code' => $validated['language_code'],
                    'priority' => $topicData['priority'] ?? 3,
                    'is_active' => true,
                    'status' => 'draft',
                    'source' => 'ai_generated',
                    'ai_context' => $topicData['ai_context'] ?? '',
                ]);
                $savedTopics[] = $topic;
            }

            \Log::info('Topics saved successfully', [
                'site_id' => $site->id,
                'topics_saved' => count($savedTopics)
            ]);

            return back()->with('success', count($savedTopics) . ' topics générés avec succès par l\'IA ! 🤖');

        } catch (\Exception $e) {
            \Log::error('Error generating topics with AI', [
                'site_id' => $site->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = 'Erreur lors de la génération : ' . $e->getMessage();
            
            // Messages d'erreur plus spécifiques
            if (str_contains($e->getMessage(), 'cURL error')) {
                $errorMessage = 'Erreur de connexion à l\'API OpenAI. Vérifiez votre connexion internet.';
            } elseif (str_contains($e->getMessage(), '401')) {
                $errorMessage = 'Clé API OpenAI invalide. Vérifiez votre configuration.';
            } elseif (str_contains($e->getMessage(), '429')) {
                $errorMessage = 'Limite de requêtes OpenAI atteinte. Essayez plus tard.';
            }

            return back()->with('error', $errorMessage);
        }
    }

    /**
     * Mettre à jour un topic
     */
    public function update(Request $request, SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au topic
        if (Auth::id() !== $topic->site->user_id) {
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
            'scheduled_date' => 'nullable|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'status' => 'required|in:draft,scheduled,published,cancelled',
            'editorial_notes' => 'nullable|string|max:1000',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $topic->update($validated);

        return back()->with('success', 'Topic mis à jour avec succès');
    }

    /**
     * Supprimer un topic
     */
    public function destroy(SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au topic
        if (Auth::id() !== $topic->site->user_id) {
            abort(403);
        }

        $topic->delete();

        return back()->with('success', 'Topic supprimé avec succès');
    }

    /**
     * Planifier un topic à une date spécifique
     */
    public function schedule(Request $request, SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au topic
        if (Auth::id() !== $topic->site->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|date_format:H:i',
            'editorial_notes' => 'nullable|string|max:1000',
        ]);

        $topic->update([
            'scheduled_date' => $validated['scheduled_date'],
            'scheduled_time' => $validated['scheduled_time'] ?? '09:00',
            'status' => 'scheduled',
            'editorial_notes' => $validated['editorial_notes'],
        ]);

        return back()->with('success', 'Topic planifié avec succès');
    }

    /**
     * Déplacer un topic vers une autre date (drag & drop)
     */
    public function move(Request $request, SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au topic
        if (Auth::id() !== $topic->site->user_id) {
            abort(403);
        }

        $validated = $request->validate([
            'scheduled_date' => 'required|date',
            'scheduled_time' => 'nullable|date_format:H:i',
        ]);

        $topic->update([
            'scheduled_date' => $validated['scheduled_date'],
            'scheduled_time' => $validated['scheduled_time'] ?? $topic->scheduled_time ?? '09:00',
            'status' => 'scheduled',
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Récupérer les topics pour une date spécifique (API pour le calendrier)
     */
    public function getTopicsForDate(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'site_id' => 'nullable|exists:sites,id',
        ]);

        $user = Auth::user();
        $sites = $user->sites()->pluck('id');

        $query = SiteTopic::with(['site'])
            ->whereIn('site_id', $sites)
            ->where('scheduled_date', $validated['date']);

        if ($validated['site_id']) {
            $query->where('site_id', $validated['site_id']);
        }

        $topics = $query->orderBy('scheduled_time')->get();

        return response()->json($topics);
    }

    /**
     * Dupliquer un topic
     */
    public function duplicate(SiteTopic $topic)
    {
        // Vérifier que l'utilisateur a accès au topic
        if (Auth::id() !== $topic->site->user_id) {
            abort(403);
        }

        $newTopic = $topic->replicate();
        $newTopic->title = $topic->title . ' (copie)';
        $newTopic->status = 'draft';
        $newTopic->scheduled_date = null;
        $newTopic->scheduled_time = null;
        $newTopic->usage_count = 0;
        $newTopic->last_used_at = null;
        $newTopic->save();

        return back()->with('success', 'Topic dupliqué avec succès');
    }
}
