<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIController extends Controller
{
    private function getOpenAIKey(): string
    {
        $key = env('OPENAI_API_KEY');
        if (!$key) {
            throw new \Exception('Clé API OpenAI non configurée');
        }
        return $key;
    }

    public function generateArticle(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'site_id' => 'nullable|exists:sites,id',
            'language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
        ]);

        try {
            $prompt = $request->input('prompt');
            $siteId = $request->input('site_id');
            $language = $request->input('language', 'fr');

            // Récupérer les informations du site pour le contexte
            $siteContext = '';
            $availableCategories = [];
            
            if ($siteId) {
                $site = Site::find($siteId);
                if ($site) {
                    $siteContext = "\nContexte du site: {$site->name} - {$site->description}";
                    $availableCategories = Category::where('site_id', $siteId)->pluck('name')->toArray();
                }
            }

            // Construire le prompt pour ChatGPT
            $systemPrompt = $this->buildSystemPrompt($language, $siteContext, $availableCategories);
            $userPrompt = $this->buildUserPrompt($prompt, $language);

            // Appel à l'API OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getOpenAIKey(),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'temperature' => 0.7,
                'max_tokens' => 4000,
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Erreur lors de l\'appel à l\'API OpenAI');
            }

            $aiResponse = $response->json();
            $content = $aiResponse['choices'][0]['message']['content'] ?? '';

            // Parser la réponse JSON de l'IA
            $parsedContent = $this->parseAIResponse($content);

            return response()->json($parsedContent);

        } catch (\Exception $e) {
            Log::error('AI Generation Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function translateArticle(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'excerpt' => 'nullable|string',
            'content' => 'nullable|string',
            'meta_title' => 'nullable|string',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string',
            'author_bio' => 'nullable|string',
            'target_language' => 'required|string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
            'source_language' => 'string|in:fr,en,es,de,it,pt,nl,ru,ja,zh',
        ]);

        try {
            $targetLanguage = $request->input('target_language');
            $sourceLanguage = $request->input('source_language', 'fr');

            if ($targetLanguage === $sourceLanguage) {
                return response()->json([
                    'error' => true,
                    'message' => 'La langue source et la langue cible sont identiques'
                ], 400);
            }

            // Construire le prompt de traduction
            $systemPrompt = $this->buildTranslationSystemPrompt($sourceLanguage, $targetLanguage);
            $userPrompt = $this->buildTranslationUserPrompt($request->all());

            // Appel à l'API OpenAI
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getOpenAIKey(),
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt
                    ],
                    [
                        'role' => 'user',
                        'content' => $userPrompt
                    ]
                ],
                'temperature' => 0.3, // Plus bas pour des traductions plus fidèles
                'max_tokens' => 4000,
            ]);

            if (!$response->successful()) {
                Log::error('OpenAI Translation API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new \Exception('Erreur lors de l\'appel à l\'API OpenAI pour la traduction');
            }

            $aiResponse = $response->json();
            $content = $aiResponse['choices'][0]['message']['content'] ?? '';

            // Parser la réponse JSON de l'IA
            $translatedContent = $this->parseTranslationResponse($content);

            return response()->json($translatedContent);

        } catch (\Exception $e) {
            Log::error('Translation Error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => true,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function buildSystemPrompt(string $language, string $siteContext, array $categories): string
    {
        $languageNames = [
            'fr' => 'français',
            'en' => 'anglais',
            'es' => 'espagnol',
            'de' => 'allemand',
            'it' => 'italien',
            'pt' => 'portugais',
            'nl' => 'néerlandais',
            'ru' => 'russe',
            'ja' => 'japonais',
            'zh' => 'chinois',
        ];

        $targetLanguage = $languageNames[$language] ?? 'français';
        $categoriesText = !empty($categories) ? "\nCatégories disponibles: " . implode(', ', $categories) : '';

        return "Tu es un expert en rédaction d'articles de blog optimisés pour le SEO. 
Tu dois générer un article complet en {$targetLanguage} avec un contenu riche et engageant.
{$siteContext}
{$categoriesText}

IMPORTANT: Tu dois répondre UNIQUEMENT avec un JSON valide dans ce format exact:
{
    \"title\": \"Titre principal de l'article\",
    \"excerpt\": \"Résumé court et accrocheur (150-200 caractères)\",
    \"content\": \"Contenu EditorJS au format JSON stringifié\",
    \"meta_title\": \"Titre SEO optimisé (50-60 caractères)\",
    \"meta_description\": \"Description SEO (150-160 caractères)\",
    \"meta_keywords\": \"mot-clé1, mot-clé2, mot-clé3\",
    \"author_name\": \"Nom d'auteur approprié\",
    \"author_bio\": \"Biographie courte de l'auteur\",
    \"suggested_categories\": [\"catégorie1\", \"catégorie2\"]
}

Pour le content EditorJS, structure-le avec des blocs variés:
- Paragraphes avec du texte riche
- Headers (h2, h3) pour structurer
- Listes à puces ou numérotées
- Éventuellement des blocs de citation ou code selon le sujet

Assure-toi que le contenu soit informatif, bien structuré et optimisé SEO.";
    }

    private function buildUserPrompt(string $prompt, string $language): string
    {
        return "Génère un article complet sur: {$prompt}

L'article doit être professionnel, informatif et engageant. 
Adapte le ton et le style selon le sujet.
Minimum 800 mots dans le contenu principal.";
    }

    private function buildTranslationSystemPrompt(string $sourceLanguage, string $targetLanguage): string
    {
        $languageNames = [
            'fr' => 'français',
            'en' => 'anglais',
            'es' => 'espagnol',
            'de' => 'allemand',
            'it' => 'italien',
            'pt' => 'portugais',
            'nl' => 'néerlandais',
            'ru' => 'russe',
            'ja' => 'japonais',
            'zh' => 'chinois',
        ];

        $sourceLang = $languageNames[$sourceLanguage] ?? 'français';
        $targetLang = $languageNames[$targetLanguage] ?? 'anglais';

        return "Tu es un traducteur professionnel spécialisé dans la traduction de contenu web et marketing.
Tu dois traduire fidèlement du {$sourceLang} vers le {$targetLang} en conservant:
- Le sens et le ton original
- La structure EditorJS des contenus
- L'optimisation SEO
- Le style et la voix de la marque

IMPORTANT: Tu dois répondre UNIQUEMENT avec un JSON valide dans ce format:
{
    \"title\": \"Titre traduit\",
    \"excerpt\": \"Résumé traduit\",
    \"content\": \"Contenu EditorJS traduit au format JSON stringifié\",
    \"meta_title\": \"Titre SEO traduit\",
    \"meta_description\": \"Description SEO traduite\",
    \"meta_keywords\": \"mots-clés traduits\",
    \"author_bio\": \"Biographie traduite\"
}

Pour le contenu EditorJS, conserve exactement la même structure de blocs mais traduis uniquement les textes.";
    }

    private function buildTranslationUserPrompt(array $data): string
    {
        return "Traduis ce contenu:\n\n" . json_encode([
            'title' => $data['title'] ?? '',
            'excerpt' => $data['excerpt'] ?? '',
            'content' => $data['content'] ?? '',
            'meta_title' => $data['meta_title'] ?? '',
            'meta_description' => $data['meta_description'] ?? '',
            'meta_keywords' => $data['meta_keywords'] ?? '',
            'author_bio' => $data['author_bio'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
    }

    private function parseAIResponse(string $content): array
    {
        // Nettoyer le contenu (enlever markdown, etc.)
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        try {
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Réponse JSON invalide de l\'IA');
            }

            // Générer un contenu EditorJS par défaut si manquant
            if (empty($decoded['content'])) {
                $decoded['content'] = $this->generateDefaultEditorJSContent($decoded['title'] ?? 'Article', $decoded['excerpt'] ?? '');
            }

            return $decoded;
        } catch (\Exception $e) {
            // Fallback: créer une structure par défaut
            return $this->createFallbackContent($content);
        }
    }

    private function parseTranslationResponse(string $content): array
    {
        // Même logique que parseAIResponse mais pour la traduction
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        try {
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Réponse de traduction JSON invalide');
            }

            return $decoded;
        } catch (\Exception $e) {
            throw new \Exception('Erreur lors du parsing de la traduction: ' . $e->getMessage());
        }
    }

    private function generateDefaultEditorJSContent(string $title, string $excerpt): string
    {
        $editorJSData = [
            'time' => time() * 1000,
            'blocks' => [
                [
                    'id' => Str::random(10),
                    'type' => 'paragraph',
                    'data' => [
                        'text' => $excerpt ?: 'Contenu de l\'article généré par l\'IA.'
                    ]
                ]
            ],
            'version' => '2.28.2'
        ];

        return json_encode($editorJSData);
    }

    private function createFallbackContent(string $rawContent): array
    {
        return [
            'title' => 'Article généré par IA',
            'excerpt' => 'Contenu généré automatiquement',
            'content' => $this->generateDefaultEditorJSContent('Article généré par IA', 'Contenu généré automatiquement'),
            'meta_title' => 'Article généré par IA',
            'meta_description' => 'Contenu généré automatiquement',
            'meta_keywords' => 'article, ia, automatique',
            'author_name' => 'Assistant IA',
            'author_bio' => 'Contenu généré par intelligence artificielle',
            'suggested_categories' => []
        ];
    }
} 