<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create-admin 
                            {name : Nom de l\'utilisateur}
                            {email : Email de l\'utilisateur}
                            {--password= : Mot de passe (généré automatiquement si non fourni)}
                            {--admin : Donner le statut d\'administrateur complet}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un utilisateur admin avec les permissions Spatie appropriées';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->generatePassword();
        $isFullAdmin = $this->option('admin');

        // Vérifier si l'email existe déjà
        if (User::where('email', $email)->exists()) {
            $this->error("Un utilisateur avec cet email existe déjà !");
            return 1;
        }

        try {
            // Créer les permissions si elles n'existent pas
            $this->createPermissions();

            // Créer l'utilisateur
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            // Assigner les permissions
            if ($isFullAdmin) {
                // Donner toutes les permissions admin
                $user->givePermissionTo([
                    'administrator',
                    'manage categories', 
                    'review suggestions', 
                    'view analytics'
                ]);
                $permissionsText = 'Toutes les permissions administrateur';
            } else {
                // Permissions de base pour reviewer
                $user->givePermissionTo(['review suggestions']);
                $permissionsText = 'Reviewer de suggestions seulement';
            }

            $this->newLine();
            $this->info('✅ Utilisateur créé avec succès !');
            $this->newLine();

            // Afficher les informations
            $this->table(
                ['Propriété', 'Valeur'],
                [
                    ['ID', $user->id],
                    ['Nom', $user->name],
                    ['Email', $user->email],
                    ['Mot de passe', $this->option('password') ? '[Fourni]' : $password],
                    ['Permissions', $permissionsText],
                    ['Admin complet', $isFullAdmin ? 'Oui' : 'Non'],
                ]
            );

            $this->newLine();
            $this->info('🔐 Informations de connexion :');
            $this->line("Email: {$email}");
            if (!$this->option('password')) {
                $this->line("Mot de passe: {$password}");
                $this->warn("⚠️  Notez bien ce mot de passe, il ne sera plus affiché !");
            }

            $this->newLine();
            $this->info('🎯 Accès disponibles :');
            
            if ($isFullAdmin) {
                $this->line("• Administration complète des catégories");
                $this->line("• Gestion des catégories globales");
                $this->line("• Validation des suggestions");
                $this->line("• Analytics complètes");
            } else {
                $this->line("• Validation des suggestions seulement");
                $this->warn("💡 Utilisez --admin pour donner toutes les permissions");
            }

            $this->newLine();
            $this->info("🌐 Dashboard admin disponible sur : /admin/categories");

            return 0;

        } catch (\Exception $e) {
            $this->error("Erreur lors de la création : {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Créer les permissions nécessaires
     */
    private function createPermissions(): void
    {
        $permissions = [
            'administrator' => 'Administrateur complet',
            'manage categories' => 'Gérer les catégories',
            'review suggestions' => 'Reviewer les suggestions',
            'view analytics' => 'Voir les analytics'
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web'
            ]);
        }

        $this->info("✅ Permissions créées/vérifiées");
    }

    /**
     * Générer un mot de passe sécurisé
     */
    private function generatePassword(): string
    {
        $length = 12;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return $password;
    }
}
