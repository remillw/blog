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
        Schema::create('backlink_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_article_id')->constrained('articles')->onDelete('cascade');
            $table->foreignId('target_article_id')->constrained('articles')->onDelete('cascade');
            $table->decimal('relevance_score', 3, 2); // 0.00 à 1.00
            $table->string('anchor_suggestion')->nullable();
            $table->text('reasoning')->nullable();
            $table->boolean('is_same_site')->default(false);
            $table->boolean('is_used')->default(false); // Si le lien a été utilisé
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['source_article_id', 'relevance_score']);
            $table->index(['target_article_id']);
            $table->index(['is_same_site', 'relevance_score']);
            
            // Éviter les doublons
            $table->unique(['source_article_id', 'target_article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backlink_suggestions');
    }
};
