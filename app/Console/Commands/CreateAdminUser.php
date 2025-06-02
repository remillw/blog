<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

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
                            {--role=admin : Rôle à assigner (user|moderator|admin|super_admin)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Créer un utilisateur admin avec les permissions appropriées';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->generatePassword();
        $role = $this->option('role');

        // Valider le rôle
        $validRoles = ['user', 'moderator', 'admin', 'super_admin'];
        if (!in_array($role, $validRoles)) {
            $this->error("Rôle invalide. Rôles disponibles : " . implode(', ', $validRoles));
            return 1;
        }

        // Vérifier si l'email existe déjà
        if (User::where('email', $email)->exists()) {
            $this->error("Un utilisateur avec cet email existe déjà !");
            return 1;
        }

        try {
            // Créer l'utilisateur
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

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
                    ['Rôle', $user->role],
                    ['Mot de passe', $this->option('password') ? '[Fourni]' : $password],
                    ['Permissions', implode(', ', $user->getAllPermissions())],
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
            $this->info('🎯 Accès disponibles selon le rôle :');
            
            switch ($role) {
                case 'super_admin':
                    $this->line("• Toutes les permissions");
                    $this->line("• Gestion complète des utilisateurs");
                    $this->line("• Gestion des catégories globales");
                    $this->line("• Validation des suggestions");
                    $this->line("• Analytics complètes");
                    break;
                    
                case 'admin':
                    $this->line("• Gestion des catégories globales");
                    $this->line("• Validation des suggestions");
                    $this->line("• Analytics");
                    break;
                    
                case 'moderator':
                    $this->line("• Validation des suggestions");
                    break;
                    
                case 'user':
                    $this->line("• Accès standard utilisateur");
                    break;
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
