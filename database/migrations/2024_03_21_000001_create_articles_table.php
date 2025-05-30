<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content'); // Format EditorJS (JSON)
            $table->longText('content_html')->nullable(); // HTML pour webhooks
            $table->text('excerpt')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->json('meta_keywords')->nullable();
            $table->string('canonical_url')->nullable();
            $table->enum('status', ['draft', 'published', 'scheduled'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->integer('reading_time')->nullable();
            $table->integer('word_count')->nullable();
            $table->string('author_name')->nullable();
            $table->text('author_bio')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image')->nullable();
            $table->json('schema_markup')->nullable();
            
            // Nouvelles colonnes pour la gestion des webhooks
            $table->enum('source', ['created', 'webhook'])->default('created'); // Source de l'article
            $table->string('external_id')->nullable(); // ID sur la plateforme externe
            $table->timestamp('webhook_sent_at')->nullable(); // Quand le webhook a été envoyé
            $table->timestamp('webhook_received_at')->nullable(); // Quand on a reçu un webhook
            $table->json('webhook_data')->nullable(); // Données du dernier webhook reçu
            $table->boolean('is_synced')->default(true); // Si l'article est synchronisé
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour les recherches fréquentes
            $table->index(['source', 'external_id']);
            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
}; 