<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class FetchFromSaas extends Command
{
    protected $signature = 'saas:fetch-articles 
                           {--saas-url=http://localhost:8000} 
                           {--endpoint=/api/articles}
                           {--api-key= : Clé API pour authentification}
                           {--status= : Filtrer par status (draft, published, scheduled)}
                           {--search= : Rechercher dans le titre/contenu}
                           {--per-page=10 : Nombre d\'articles par page}
                           {--categories : Tester l\'endpoint des catégories au lieu des articles}';
    
    protected $description = 'Fetch articles from SaaS platform using API key authentication';

    public function handle()
    {
        $saasUrl = $this->option('saas-url');
        $endpoint = $this->option('endpoint');
        $apiKey = $this->option('api-key');
        $status = $this->option('status');
        $search = $this->option('search');
        $perPage = $this->option('per-page');
        $testCategories = $this->option('categories');
        
        // Modifier l'endpoint si on teste les catégories
        if ($testCategories) {
            $endpoint = '/api/categories';
        }
        
        $fullUrl = $saasUrl . $endpoint;

        $this->info("🔍 Test de communication avec votre SaaS");
        $this->info("🌐 URL SaaS: {$saasUrl}");
        $this->info("📡 Endpoint: {$endpoint}");
        $this->info("🎯 URL complète: {$fullUrl}");
        
        if ($testCategories) {
            $this->info("📂 Mode: Test des catégories");
        } else {
            $this->info("📄 Mode: Test des articles");
        }
        
        if ($apiKey) {
            $this->info("🔑 Clé API: " . substr($apiKey, 0, 8) . '...');
        } else {
            $this->warn("⚠️  Aucune clé API fournie (utilisez --api-key=votre-cle)");
        }
        
        $this->newLine();

        try {
            $this->info("📤 Tentative de connexion au SaaS...");
            
            // Préparer les headers
            $headers = [
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-Client/1.0'
            ];
            
            if ($apiKey) {
                $headers['X-API-Key'] = $apiKey;
            }
            
            // Préparer les paramètres de requête
            $queryParams = [];
            if ($status && !$testCategories) $queryParams['status'] = $status; // Status seulement pour articles
            if ($search) $queryParams['search'] = $search;
            if ($perPage) $queryParams['per_page'] = $perPage;

            // Faire la requête
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->get($fullUrl, $queryParams);

            $this->info("📊 Réponse reçue:");
            $this->table(['Champ', 'Valeur'], [
                ['Status HTTP', $response->status()],
                ['Content-Type', $response->header('Content-Type') ?? 'N/A'],
                ['Taille réponse', strlen($response->body()) . ' bytes'],
                ['Temps réponse', $response->transferStats?->getTransferTime() ?? 'N/A']
            ]);

            if ($response->successful()) {
                $this->info("✅ Connexion réussie !");
                $this->newLine();

                // Décoder la réponse JSON
                try {
                    $data = $response->json();
                    
                    if (isset($data['success']) && $data['success']) {
                        // Format de réponse de votre API
                        $this->info("🎉 Authentification réussie !");
                        
                        if (isset($data['site'])) {
                            $site = $data['site'];
                            $this->info("🏢 Site: {$site['name']} ({$site['domain']})");
                        }
                        
                        if ($testCategories) {
                            // Traitement des catégories
                            if (isset($data['categories']) && is_array($data['categories'])) {
                                $categories = $data['categories'];
                                $pagination = $data['pagination'] ?? [];
                                
                                $this->info("📂 Catégories trouvées: " . count($categories));
                                
                                if (isset($pagination['total'])) {
                                    $this->info("📊 Total dans la base: {$pagination['total']} catégories");
                                    $this->info("📖 Page {$pagination['current_page']}/{$pagination['last_page']}");
                                }

                                // Afficher les catégories
                                if (!empty($categories)) {
                                    $this->newLine();
                                    $this->info("📋 Liste des catégories:");
                                    
                                    $tableData = [];
                                    foreach ($categories as $category) {
                                        $tableData[] = [
                                            'ID' => $category['id'] ?? 'N/A',
                                            'Nom' => $category['name'] ?? 'N/A',
                                            'Slug' => $category['slug'] ?? 'N/A',
                                            'Articles' => $category['articles_count'] ?? 0,
                                            'Créée le' => isset($category['created_at']) ? date('d/m/Y', strtotime($category['created_at'])) : 'N/A',
                                            'Modifiée le' => isset($category['updated_at']) ? date('d/m/Y', strtotime($category['updated_at'])) : 'N/A'
                                        ];
                                    }

                                    $this->table(['ID', 'Nom', 'Slug', 'Articles', 'Créée le', 'Modifiée le'], $tableData);

                                    // Afficher le détail de la première catégorie
                                    if (isset($categories[0])) {
                                        $firstCategory = $categories[0];
                                        $this->newLine();
                                        $this->info("📂 Détail de la première catégorie:");
                                        
                                        $detailData = [
                                            ['ID', $firstCategory['id'] ?? 'N/A'],
                                            ['Nom', $firstCategory['name'] ?? 'N/A'],
                                            ['Slug', $firstCategory['slug'] ?? 'N/A'],
                                            ['Description', $firstCategory['description'] ?? 'Aucune'],
                                            ['Nombre d\'articles', $firstCategory['articles_count'] ?? 0],
                                            ['Créée le', isset($firstCategory['created_at']) ? date('d/m/Y H:i', strtotime($firstCategory['created_at'])) : 'N/A'],
                                            ['Modifiée le', isset($firstCategory['updated_at']) ? date('d/m/Y H:i', strtotime($firstCategory['updated_at'])) : 'N/A'],
                                        ];

                                        $this->table(['Champ', 'Valeur'], $detailData);
                                    }

                                } else {
                                    $this->warn("⚠️  Aucune catégorie trouvée");
                                    if ($search) {
                                        $this->info("💡 Essayez sans filtres pour voir toutes les catégories");
                                    }
                                }
                            } else {
                                $this->warn("⚠️  Aucune catégorie dans la réponse");
                            }
                        } else {
                            // Traitement des articles (code existant)
                            if (isset($data['articles']) && is_array($data['articles'])) {
                                $articles = $data['articles'];
                                $pagination = $data['pagination'] ?? [];
                                
                                $this->info("📄 Articles trouvés: " . count($articles));
                                
                                if (isset($pagination['total'])) {
                                    $this->info("📊 Total dans la base: {$pagination['total']} articles");
                                    $this->info("📖 Page {$pagination['current_page']}/{$pagination['last_page']}");
                                }

                                // Afficher les articles
                                if (!empty($articles)) {
                                    $this->newLine();
                                    $this->info("📋 Liste des articles:");
                                    
                                    $tableData = [];
                                    foreach ($articles as $index => $article) {
                                        $tableData[] = [
                                            'ID' => $article['external_id'] ?? $article['id'] ?? 'N/A',
                                            'Titre' => isset($article['title']) ? substr($article['title'], 0, 40) . '...' : 'N/A',
                                            'Status' => $article['status'] ?? 'N/A',
                                            'Auteur' => $article['author_name'] ?? 'N/A',
                                            'Catégories' => isset($article['categories']) ? implode(', ', array_slice($article['categories'], 0, 2)) : 'N/A',
                                            'Publié' => isset($article['published_at']) ? date('d/m/Y', strtotime($article['published_at'])) : 'N/A'
                                        ];
                                    }

                                    $this->table(['ID', 'Titre', 'Status', 'Auteur', 'Catégories', 'Publié'], $tableData);

                                    // Afficher le détail du premier article
                                    if (isset($articles[0])) {
                                        $firstArticle = $articles[0];
                                        $this->newLine();
                                        $this->info("📖 Détail du premier article:");
                                        
                                        $detailData = [
                                            ['ID', $firstArticle['id'] ?? 'N/A'],
                                            ['External ID', $firstArticle['external_id'] ?? 'N/A'],
                                            ['Titre', $firstArticle['title'] ?? 'N/A'],
                                            ['Slug', $firstArticle['slug'] ?? 'N/A'],
                                            ['Status', $firstArticle['status'] ?? 'N/A'],
                                            ['Auteur', $firstArticle['author_name'] ?? 'N/A'],
                                            ['Extrait', isset($firstArticle['excerpt']) ? substr($firstArticle['excerpt'], 0, 100) . '...' : 'N/A'],
                                            ['Contenu', isset($firstArticle['content']) ? substr(strip_tags($firstArticle['content']), 0, 100) . '...' : 'N/A'],
                                            ['Catégories', isset($firstArticle['categories']) ? implode(', ', $firstArticle['categories']) : 'N/A'],
                                            ['Temps lecture', ($firstArticle['reading_time'] ?? 0) . ' min'],
                                            ['Featured', $firstArticle['is_featured'] ? 'Oui' : 'Non'],
                                            ['Créé le', isset($firstArticle['created_at']) ? date('d/m/Y H:i', strtotime($firstArticle['created_at'])) : 'N/A'],
                                            ['Modifié le', isset($firstArticle['updated_at']) ? date('d/m/Y H:i', strtotime($firstArticle['updated_at'])) : 'N/A'],
                                        ];

                                        $this->table(['Champ', 'Valeur'], $detailData);
                                    }

                                } else {
                                    $this->warn("⚠️  Aucun article trouvé");
                                    if ($status || $search) {
                                        $this->info("💡 Essayez sans filtres pour voir tous les articles");
                                    }
                                }
                            } else {
                                $this->warn("⚠️  Aucun article dans la réponse");
                            }
                        }
                        
                    } else {
                        // Erreur d'authentification ou autre
                        $errorMessage = $data['message'] ?? 'Erreur inconnue';
                        $this->error("❌ Erreur API: {$errorMessage}");
                        
                        if (str_contains($errorMessage, 'API key')) {
                            $this->newLine();
                            $this->warn("💡 Problème d'authentification:");
                            $this->warn("- Vérifiez votre clé API avec --api-key=votre-cle");
                            $this->warn("- Contactez l'administrateur du SaaS pour obtenir une clé valide");
                        }
                        
                        return 1;
                    }

                } catch (\Exception $e) {
                    $this->warn("⚠️  Impossible de décoder la réponse JSON");
                    $this->info("Erreur: " . $e->getMessage());
                    $this->newLine();
                    $this->info("📄 Contenu brut de la réponse (200 premiers caractères):");
                    $this->line(substr($response->body(), 0, 200) . '...');
                }

            } else {
                $this->error("❌ Erreur de connexion");
                $this->error("Status: {$response->status()}");
                
                // Essayer de décoder l'erreur JSON
                try {
                    $errorData = $response->json();
                    if (isset($errorData['message'])) {
                        $this->error("Message: {$errorData['message']}");
                    }
                } catch (\Exception $e) {
                    $this->error("Réponse: " . substr($response->body(), 0, 200));
                }
                
                $this->newLine();
                $this->warn("💡 Suggestions:");
                
                if ($response->status() === 401) {
                    $this->warn("- Problème d'authentification: vérifiez votre clé API");
                    $this->warn("- Utilisez: --api-key=votre-cle-api");
                } else {
                    $this->warn("- Vérifiez que votre SaaS est démarré sur {$saasUrl}");
                    $this->warn("- Vérifiez que l'endpoint {$endpoint} existe");
                    $this->warn("- Vérifiez que l'endpoint retourne du JSON");
                }
                
                return 1;
            }

        } catch (\Exception $e) {
            $this->error("❌ Exception lors de la connexion:");
            $this->error($e->getMessage());
            
            if (str_contains($e->getMessage(), 'Connection refused')) {
                $this->newLine();
                $this->warn("💡 Le SaaS ne semble pas accessible:");
                $this->warn("- Vérifiez que votre SaaS est démarré");
                $this->warn("- Vérifiez l'URL: {$saasUrl}");
                $this->warn("- Essayez: php artisan saas:fetch-articles --saas-url=http://localhost:8001");
            }
            
            return 1;
        }

        $this->newLine();
        $this->info("🎉 Test de communication terminé avec succès !");
        
        // Afficher des exemples d'utilisation
        $this->newLine();
        $this->info("💡 Exemples d'utilisation:");
        $this->info("php artisan saas:fetch-articles --api-key=votre-cle");
        $this->info("php artisan saas:fetch-articles --api-key=votre-cle --status=published");
        $this->info("php artisan saas:fetch-articles --api-key=votre-cle --search=laravel");
        $this->info("php artisan saas:fetch-articles --api-key=votre-cle --per-page=20");
        $this->info("php artisan saas:fetch-articles --api-key=votre-cle --categories");
        
        return 0;
    }
} 