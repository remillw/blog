<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Site;
use App\Models\SiteTopic;
use Illuminate\Support\Facades\Hash;

class CreateTestUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:create-user-with-topics 
                           {--email=test@test.com : Email de l\'utilisateur}
                           {--password=password : Mot de passe}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crée un utilisateur de test avec site et topics pour tester la génération automatique';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->option('email');
        $password = $this->option('password');

        $this->info('🔧 Création d\'un utilisateur de test avec topics...');

        // Créer ou récupérer l'utilisateur
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Test User',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        $this->info("✅ Utilisateur créé/trouvé : {$user->email}");

        // Créer ou récupérer le site
        $site = Site::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Site de Test IA'],
            [
                'description' => 'Site de test pour la génération automatique d\'articles avec l\'IA',
                'domain' => 'test-ia.example.com',
                'platform_type' => 'laravel',
                'api_key' => 'test-api-key-' . time(),
                'is_active' => true,
                'primary_color' => '#4E8D44',
                'secondary_color' => '#6b7280',
                'accent_color' => '#10b981',
                'auto_article_generation' => true,
                'auto_content_guidelines' => 'Créer du contenu informatif et engageant sur la technologie, le jardinage et les voyages.',
                'auto_content_language' => 'fr',
                'auto_word_count' => 800,
            ]
        );

        $this->info("✅ Site créé/trouvé : {$site->name}");

        // Créer des topics de test
        $testTopics = [
            [
                'title' => 'Guide complet du jardinage urbain pour débutants',
                'description' => 'Un guide exhaustif pour commencer le jardinage en ville, même avec peu d\'espace',
                'keywords' => ['jardinage urbain', 'potager balcon', 'plantes débutant', 'jardinage appartement'],
                'categories' => ['Jardinage', 'Écologie', 'DIY'],
                'priority' => 5,
                'ai_context' => 'Article destiné aux citadins débutants qui veulent commencer à jardiner'
            ],
            [
                'title' => 'Les meilleures destinations de voyage écologique en 2024',
                'description' => 'Découverte de destinations touristiques respectueuses de l\'environnement',
                'keywords' => ['voyage écologique', 'tourisme durable', 'écotravel', 'destinations vertes'],
                'categories' => ['Voyage', 'Écologie', 'Tourisme'],
                'priority' => 4,
                'ai_context' => 'Focus sur l\'impact environnemental et les pratiques durables'
            ],
            [
                'title' => 'Intelligence Artificielle : révolution ou simple évolution ?',
                'description' => 'Analyse de l\'impact de l\'IA sur notre société et notre avenir',
                'keywords' => ['intelligence artificielle', 'IA', 'technologie', 'avenir', 'société'],
                'categories' => ['Technologie', 'Innovation', 'Société'],
                'priority' => 5,
                'ai_context' => 'Article équilibré entre opportunités et défis de l\'IA'
            ],
            [
                'title' => 'Comment créer une routine matinale productive',
                'description' => 'Conseils pratiques pour optimiser sa routine du matin et augmenter sa productivité',
                'keywords' => ['routine matinale', 'productivité', 'habitudes', 'morning routine', 'développement personnel'],
                'categories' => ['Productivité', 'Bien-être', 'Développement personnel'],
                'priority' => 3,
                'ai_context' => 'Conseils pratiques et actionables, avec des exemples concrets'
            ],
        ];

        foreach ($testTopics as $topicData) {
            $topic = SiteTopic::firstOrCreate(
                [
                    'site_id' => $site->id,
                    'title' => $topicData['title'],
                ],
                [
                    'description' => $topicData['description'],
                    'keywords' => $topicData['keywords'],
                    'categories' => $topicData['categories'],
                    'language_code' => 'fr',
                    'priority' => $topicData['priority'],
                    'is_active' => true,
                    'source' => 'manual',
                    'ai_context' => $topicData['ai_context'],
                    'status' => 'scheduled',
                    'scheduled_date' => today(),
                    'scheduled_time' => now()->addMinutes(rand(5, 60))->format('H:i'),
                ]
            );

            $this->line("📝 Topic créé : {$topic->title}");
        }

        $this->newLine();
        $this->info('🎉 Configuration de test terminée !');
        $this->newLine();
        
        $this->table(['Information', 'Valeur'], [
            ['Email utilisateur', $user->email],
            ['Mot de passe', $password],
            ['Site ID', $site->id],
            ['Site nom', $site->name],
            ['Topics créés', count($testTopics)],
            ['Topics programmés', SiteTopic::where('site_id', $site->id)->where('status', 'scheduled')->count()],
        ]);

        $this->newLine();
        $this->info('🤖 Pour tester la génération automatique :');
        $this->line('php artisan articles:generate-auto --site-id=' . $site->id . ' --force');
        $this->newLine();
        $this->info('🎯 Pour voir les articles générés :');
        $this->line('Connectez-vous sur /login avec les identifiants ci-dessus');

        return 0;
    }
}
