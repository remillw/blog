<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('saas_url'); // URL du SaaS
            $table->string('api_key_hash'); // Hash de la clé API (pour sécurité)
            $table->timestamp('last_sync_at'); // Dernière synchronisation
            $table->integer('articles_fetched')->default(0); // Nombre d'articles récupérés
            $table->integer('articles_created')->default(0); // Nombre d'articles créés
            $table->integer('articles_updated')->default(0); // Nombre d'articles mis à jour
            $table->json('sync_data')->nullable(); // Données de synchronisation (filtres, etc.)
            $table->text('sync_notes')->nullable(); // Notes de synchronisation
            $table->boolean('sync_success')->default(true); // Succès de la sync
            $table->timestamps();
            
            // Index pour les performances
            $table->index(['saas_url', 'api_key_hash']);
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
