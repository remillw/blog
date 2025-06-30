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
        Schema::table('sites', function (Blueprint $table) {
            // Suppression automatique après synchronisation
            $table->boolean('auto_delete_after_sync')->default(false)->comment('Supprime automatiquement les articles après synchronisation');
            
            // Configuration pour la génération automatique d'articles
            $table->boolean('auto_article_generation')->default(false)->comment('Active la génération automatique d\'articles');
            $table->json('auto_schedule')->nullable()->comment('Configuration du planning de génération (jours, heures, paramètres)');
            $table->text('auto_content_guidelines')->nullable()->comment('Directives de contenu pour la génération automatique');
            $table->string('auto_content_language', 10)->nullable()->comment('Langue par défaut pour la génération automatique');
            $table->integer('auto_word_count')->default(800)->comment('Nombre de mots par défaut pour les articles générés');
            $table->timestamp('last_auto_generation')->nullable()->comment('Dernière génération automatique effectuée');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'auto_delete_after_sync',
                'auto_article_generation',
                'auto_schedule',
                'auto_content_guidelines',
                'auto_content_language',
                'auto_word_count',
                'last_auto_generation'
            ]);
        });
    }
};
