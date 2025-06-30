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
        Schema::create('site_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('title')->comment('Titre/nom du sujet');
            $table->text('description')->nullable()->comment('Description détaillée du sujet');
            $table->json('keywords')->comment('Mots-clés associés au sujet');
            $table->json('categories')->nullable()->comment('Catégories suggérées pour ce sujet');
            $table->string('language_code', 10)->comment('Langue du sujet');
            $table->integer('priority')->default(1)->comment('Priorité du sujet (1=faible, 5=élevée)');
            $table->boolean('is_active')->default(true)->comment('Sujet actif pour la génération');
            $table->integer('usage_count')->default(0)->comment('Nombre de fois que ce sujet a été utilisé');
            $table->timestamp('last_used_at')->nullable()->comment('Dernière utilisation de ce sujet');
            $table->enum('source', ['manual', 'ai_generated'])->default('manual')->comment('Source du sujet');
            $table->text('ai_context')->nullable()->comment('Contexte utilisé par l\'IA pour ce sujet');
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->index(['site_id', 'language_code', 'is_active']);
            $table->index(['site_id', 'priority', 'last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_topics');
    }
};
