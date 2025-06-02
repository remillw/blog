<?php

namespace App\Console\Commands;

use App\Models\GlobalCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateBaseGlobalCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'categories:create-base-global {--force : Forcer la création même si des catégories existent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer les catégories globales de base avec hiérarchie et traductions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');

        // Vérifier si des catégories existent déjà
        if (GlobalCategory::count() > 0 && !$force) {
            $this->warn('Des catégories globales existent déjà. Utilisez --force pour les recréer.');
            return;
        }

        if ($force && GlobalCategory::count() > 0) {
            $this->warn('Suppression des catégories existantes...');
            GlobalCategory::truncate();
        }

        $this->info('🌱 Création des catégories globales de base');
        $this->newLine();

        // Définir l'arborescence des catégories avec traductions
        $categories = $this->getCategoryTree();

        $createdCount = 0;
        foreach ($categories as $categoryData) {
            $category = $this->createCategory($categoryData);
            if ($category) {
                $createdCount++;
                $this->line("   ✅ {$category->name} (ID: {$category->id})");
                
                // Créer les sous-catégories
                if (isset($categoryData['children'])) {
                    $childrenCount = $this->createChildren($categoryData['children'], $category->id, 1);
                    $createdCount += $childrenCount;
                }
            }
        }

        $this->newLine();
        $this->info("✨ {$createdCount} catégories globales créées avec succès !");
        
        // Afficher quelques statistiques
        $this->displayStatistics();
    }

    /**
     * Définir l'arborescence des catégories de base
     */
    private function getCategoryTree(): array
    {
        return [
            [
                'name' => 'Technologie',
                'translations' => [
                    'fr' => 'Technologie',
                    'en' => 'Technology',
                    'es' => 'Tecnología',
                    'de' => 'Technologie',
                    'it' => 'Tecnologia',
                ],
                'icon' => '💻',
                'color' => '#3B82F6',
                'description' => 'Tout ce qui concerne la technologie et l\'innovation',
                'children' => [
                    [
                        'name' => 'Intelligence Artificielle',
                        'translations' => [
                            'fr' => 'Intelligence Artificielle',
                            'en' => 'Artificial Intelligence',
                            'es' => 'Inteligencia Artificial',
                            'de' => 'Künstliche Intelligenz',
                            'it' => 'Intelligenza Artificiale',
                        ],
                        'icon' => '🤖',
                        'color' => '#8B5CF6',
                    ],
                    [
                        'name' => 'Développement Web',
                        'translations' => [
                            'fr' => 'Développement Web',
                            'en' => 'Web Development',
                            'es' => 'Desarrollo Web',
                            'de' => 'Webentwicklung',
                            'it' => 'Sviluppo Web',
                        ],
                        'icon' => '🌐',
                        'color' => '#10B981',
                    ],
                    [
                        'name' => 'Cybersécurité',
                        'translations' => [
                            'fr' => 'Cybersécurité',
                            'en' => 'Cybersecurity',
                            'es' => 'Ciberseguridad',
                            'de' => 'Cybersicherheit',
                            'it' => 'Sicurezza Informatica',
                        ],
                        'icon' => '🔒',
                        'color' => '#EF4444',
                    ],
                ]
            ],
            [
                'name' => 'Business',
                'translations' => [
                    'fr' => 'Business',
                    'en' => 'Business',
                    'es' => 'Negocios',
                    'de' => 'Geschäft',
                    'it' => 'Affari',
                ],
                'icon' => '💼',
                'color' => '#F59E0B',
                'description' => 'Entrepreneuriat, marketing et stratégie d\'entreprise',
                'children' => [
                    [
                        'name' => 'Marketing Digital',
                        'translations' => [
                            'fr' => 'Marketing Digital',
                            'en' => 'Digital Marketing',
                            'es' => 'Marketing Digital',
                            'de' => 'Digitales Marketing',
                            'it' => 'Marketing Digitale',
                        ],
                        'icon' => '📈',
                        'color' => '#F97316',
                    ],
                    [
                        'name' => 'E-commerce',
                        'translations' => [
                            'fr' => 'E-commerce',
                            'en' => 'E-commerce',
                            'es' => 'Comercio Electrónico',
                            'de' => 'E-Commerce',
                            'it' => 'E-commerce',
                        ],
                        'icon' => '🛒',
                        'color' => '#06B6D4',
                    ],
                    [
                        'name' => 'Startup',
                        'translations' => [
                            'fr' => 'Startup',
                            'en' => 'Startup',
                            'es' => 'Startup',
                            'de' => 'Startup',
                            'it' => 'Startup',
                        ],
                        'icon' => '🚀',
                        'color' => '#8B5CF6',
                    ],
                ]
            ],
            [
                'name' => 'Lifestyle',
                'translations' => [
                    'fr' => 'Style de Vie',
                    'en' => 'Lifestyle',
                    'es' => 'Estilo de Vida',
                    'de' => 'Lebensstil',
                    'it' => 'Stile di Vita',
                ],
                'icon' => '🌟',
                'color' => '#EC4899',
                'description' => 'Mode de vie, bien-être et développement personnel',
                'children' => [
                    [
                        'name' => 'Santé et Bien-être',
                        'translations' => [
                            'fr' => 'Santé et Bien-être',
                            'en' => 'Health & Wellness',
                            'es' => 'Salud y Bienestar',
                            'de' => 'Gesundheit & Wohlbefinden',
                            'it' => 'Salute e Benessere',
                        ],
                        'icon' => '🏥',
                        'color' => '#10B981',
                    ],
                    [
                        'name' => 'Voyage',
                        'translations' => [
                            'fr' => 'Voyage',
                            'en' => 'Travel',
                            'es' => 'Viajes',
                            'de' => 'Reisen',
                            'it' => 'Viaggi',
                        ],
                        'icon' => '✈️',
                        'color' => '#3B82F6',
                    ],
                    [
                        'name' => 'Cuisine',
                        'translations' => [
                            'fr' => 'Cuisine',
                            'en' => 'Cooking',
                            'es' => 'Cocina',
                            'de' => 'Kochen',
                            'it' => 'Cucina',
                        ],
                        'icon' => '👨‍🍳',
                        'color' => '#F59E0B',
                    ],
                ]
            ],
            [
                'name' => 'Education',
                'translations' => [
                    'fr' => 'Éducation',
                    'en' => 'Education',
                    'es' => 'Educación',
                    'de' => 'Bildung',
                    'it' => 'Istruzione',
                ],
                'icon' => '📚',
                'color' => '#6366F1',
                'description' => 'Apprentissage, formation et développement des compétences',
                'children' => [
                    [
                        'name' => 'Formation en ligne',
                        'translations' => [
                            'fr' => 'Formation en ligne',
                            'en' => 'Online Learning',
                            'es' => 'Aprendizaje en Línea',
                            'de' => 'Online-Lernen',
                            'it' => 'Apprendimento Online',
                        ],
                        'icon' => '💻',
                        'color' => '#8B5CF6',
                    ],
                    [
                        'name' => 'Langues',
                        'translations' => [
                            'fr' => 'Langues',
                            'en' => 'Languages',
                            'es' => 'Idiomas',
                            'de' => 'Sprachen',
                            'it' => 'Lingue',
                        ],
                        'icon' => '🗣️',
                        'color' => '#10B981',
                    ],
                ]
            ],
            [
                'name' => 'Divertissement',
                'translations' => [
                    'fr' => 'Divertissement',
                    'en' => 'Entertainment',
                    'es' => 'Entretenimiento',
                    'de' => 'Unterhaltung',
                    'it' => 'Intrattenimento',
                ],
                'icon' => '🎬',
                'color' => '#EF4444',
                'description' => 'Films, musique, jeux et culture populaire',
                'children' => [
                    [
                        'name' => 'Gaming',
                        'translations' => [
                            'fr' => 'Jeux Vidéo',
                            'en' => 'Gaming',
                            'es' => 'Juegos',
                            'de' => 'Gaming',
                            'it' => 'Gaming',
                        ],
                        'icon' => '🎮',
                        'color' => '#8B5CF6',
                    ],
                    [
                        'name' => 'Musique',
                        'translations' => [
                            'fr' => 'Musique',
                            'en' => 'Music',
                            'es' => 'Música',
                            'de' => 'Musik',
                            'it' => 'Musica',
                        ],
                        'icon' => '🎵',
                        'color' => '#F59E0B',
                    ],
                ]
            ],
        ];
    }

    /**
     * Créer une catégorie avec ses traductions
     */
    private function createCategory(array $data, ?int $parentId = null, int $depth = 0): ?GlobalCategory
    {
        try {
            $category = GlobalCategory::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'translations' => $data['translations'] ?? [],
                'icon' => $data['icon'] ?? null,
                'color' => $data['color'] ?? '#6B7280',
                'parent_id' => $parentId,
                'depth' => $depth,
                'usage_count' => 0,
                'is_approved' => true,
                'created_by' => 1, // Admin par défaut
            ]);

            return $category;
        } catch (\Exception $e) {
            $this->error("   ❌ Erreur lors de la création de {$data['name']}: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Créer les catégories enfants
     */
    private function createChildren(array $children, int $parentId, int $depth): int
    {
        $count = 0;
        
        foreach ($children as $childData) {
            $child = $this->createCategory($childData, $parentId, $depth);
            if ($child) {
                $count++;
                $indent = str_repeat('   ', $depth + 1);
                $this->line("{$indent}└─ {$child->name} (ID: {$child->id})");
                
                // Récursion pour les petits-enfants
                if (isset($childData['children'])) {
                    $count += $this->createChildren($childData['children'], $child->id, $depth + 1);
                }
            }
        }
        
        return $count;
    }

    /**
     * Afficher les statistiques
     */
    private function displayStatistics(): void
    {
        $this->newLine();
        $this->info('📊 Statistiques:');
        
        $totalCategories = GlobalCategory::count();
        $rootCategories = GlobalCategory::roots()->count();
        $withTranslations = GlobalCategory::whereJsonLength('translations', '>', 1)->count();
        $languages = collect(GlobalCategory::all()->pluck('translations')->flatten(1))->keys()->unique()->count();
        
        $this->line("   Total des catégories: {$totalCategories}");
        $this->line("   Catégories racines: {$rootCategories}");
        $this->line("   Avec traductions multiples: {$withTranslations}");
        $this->line("   Langues couvertes: {$languages}");
        
        $this->newLine();
        $this->line('🌍 Langues disponibles: fr, en, es, de, it');
        $this->line('🔗 Testez l\'API: GET /api/global-categories/tree?language=fr');
    }
}
