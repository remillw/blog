<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'suggested_name',
        'language_code',
        'similar_to_id',
        'similarity_score',
        'status',
        'ai_reasoning',
        'suggested_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'similarity_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_MERGED = 'merged';

    /**
     * Relation vers la catégorie similaire détectée
     */
    public function similarCategory(): BelongsTo
    {
        return $this->belongsTo(GlobalCategory::class, 'similar_to_id');
    }

    /**
     * Utilisateur qui a fait la suggestion
     */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by');
    }

    /**
     * Utilisateur qui a reviewé la suggestion
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Marquer comme approuvé et créer la catégorie
     */
    public function approve(int $reviewerId): ?GlobalCategory
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        // Créer la nouvelle catégorie globale
        $category = GlobalCategory::create([
            'name' => $this->suggested_name,
            'translations' => [$this->language_code => $this->suggested_name],
            'created_by' => $this->suggested_by,
            'is_approved' => true,
        ]);

        return $category;
    }

    /**
     * Marquer comme rejeté
     */
    public function reject(int $reviewerId, string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'ai_reasoning' => $reason ? $this->ai_reasoning . "\nRejection reason: " . $reason : $this->ai_reasoning,
        ]);
    }

    /**
     * Marquer comme fusionné avec une catégorie existante
     */
    public function mergeWith(GlobalCategory $category, int $reviewerId): void
    {
        // Ajouter la traduction à la catégorie existante
        $category->setTranslation($this->language_code, $this->suggested_name);
        
        $this->update([
            'status' => self::STATUS_MERGED,
            'similar_to_id' => $category->id,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Vérifier si la suggestion est en attente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Vérifier si elle a été approuvée
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Scope pour les suggestions en attente
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope pour les suggestions avec forte similarité
     */
    public function scopeHighSimilarity($query, float $threshold = 0.70)
    {
        return $query->where('similarity_score', '>=', $threshold);
    }

    /**
     * Scope par langue
     */
    public function scopeForLanguage($query, string $languageCode)
    {
        return $query->where('language_code', $languageCode);
    }

    /**
     * Créer une suggestion intelligente avec IA
     */
    public static function createWithAI(string $suggestedName, string $languageCode, int $userId): self
    {
        // Chercher des catégories similaires
        $similarCategories = GlobalCategory::searchByName($suggestedName, $languageCode, 5);
        
        $suggestion = new self([
            'suggested_name' => $suggestedName,
            'language_code' => $languageCode,
            'suggested_by' => $userId,
            'status' => self::STATUS_PENDING,
        ]);

        // Si on trouve des catégories similaires, analyser avec IA
        if ($similarCategories->isNotEmpty()) {
            $aiAnalysis = self::analyzeWithAI($suggestedName, $similarCategories, $languageCode);
            
            if ($aiAnalysis) {
                $suggestion->similar_to_id = $aiAnalysis['similar_category_id'] ?? null;
                $suggestion->similarity_score = $aiAnalysis['similarity_score'] ?? 0;
                $suggestion->ai_reasoning = $aiAnalysis['reasoning'] ?? '';
                
                // Si similarité > 70%, marquer pour review
                if ($aiAnalysis['similarity_score'] >= 0.70) {
                    $suggestion->status = self::STATUS_PENDING;
                }
            }
        }

        $suggestion->save();
        return $suggestion;
    }

    /**
     * Analyser la similarité avec IA
     */
    private static function analyzeWithAI(string $suggestedName, $similarCategories, string $languageCode): ?array
    {
        try {
            $openaiKey = env('OPENAI_API_KEY');
            if (!$openaiKey) {
                return null;
            }

            $categoriesData = $similarCategories->map(function ($cat) use ($languageCode) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->getTranslatedName($languageCode),
                    'path' => $cat->getPath($languageCode),
                    'usage_count' => $cat->usage_count,
                ];
            })->toArray();

            $prompt = "Analyser la similarité entre la nouvelle catégorie proposée et les catégories existantes:

NOUVELLE CATÉGORIE: \"{$suggestedName}\" (langue: {$languageCode})

CATÉGORIES EXISTANTES:
" . json_encode($categoriesData, JSON_UNESCAPED_UNICODE) . "

Détermine si la nouvelle catégorie est similaire (>70%) à une existante.
Considère: synonymes, variations linguistiques, concepts similaires.

Réponds en JSON:
{
    \"is_similar\": true/false,
    \"similar_category_id\": 123 ou null,
    \"similarity_score\": 0.85,
    \"reasoning\": \"Explication claire\",
    \"recommendation\": \"merge\" ou \"create\" ou \"reject\"
}";

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $openaiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en taxonomie et classification. Réponds UNIQUEMENT en JSON valide.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            if (!$response->successful()) {
                return null;
            }

            $result = $response->json();
            $content = $result['choices'][0]['message']['content'] ?? '';
            
            // Parser la réponse JSON
            $content = trim($content);
            $content = preg_replace('/^```json\s*/', '', $content);
            $content = preg_replace('/\s*```$/', '', $content);
            
            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return [
                'similar_category_id' => $data['similar_category_id'] ?? null,
                'similarity_score' => $data['similarity_score'] ?? 0,
                'reasoning' => $data['reasoning'] ?? '',
                'recommendation' => $data['recommendation'] ?? 'create',
            ];

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AI category analysis failed', [
                'error' => $e->getMessage(),
                'suggested_name' => $suggestedName,
            ]);
            return null;
        }
    }
}
