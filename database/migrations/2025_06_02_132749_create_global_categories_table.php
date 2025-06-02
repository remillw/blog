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
        Schema::create('global_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nom dans la langue principale
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('translations'); // Traductions dans toutes les langues
            $table->string('icon')->nullable(); // Icône pour l'interface
            $table->string('color', 7)->default('#6B7280'); // Couleur hex
            
            // Hiérarchie avec nested sets pour performance
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('lft')->default(0); // Left boundary pour nested sets
            $table->integer('rgt')->default(0); // Right boundary pour nested sets
            $table->integer('depth')->default(0); // Profondeur dans l'arbre
            
            // Métadonnées
            $table->integer('usage_count')->default(0); // Nombre d'utilisations
            $table->decimal('similarity_threshold', 3, 2)->default(0.70); // Seuil de similarité
            $table->boolean('is_approved')->default(true); // Validation manuelle
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();

            // Index pour les requêtes fréquentes
            $table->index(['parent_id']);
            $table->index(['lft', 'rgt', 'depth']); // Pour nested sets
            $table->index(['usage_count']);
            $table->index(['is_approved']);
            $table->index(['created_at']);
            
            // Contraintes
            $table->foreign('parent_id')->references('id')->on('global_categories')->onDelete('cascade');
        });

        // Table de liaison entre sites et catégories globales
        Schema::create('site_global_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('global_category_id')->constrained()->onDelete('cascade');
            $table->string('language_code', 5); // Langue spécifique au site
            $table->string('custom_name')->nullable(); // Nom personnalisé pour ce site
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Éviter les doublons
            $table->unique(['site_id', 'global_category_id', 'language_code'], 'site_category_language_unique');
            $table->index(['site_id', 'language_code', 'is_active']);
        });

        // Table pour l'historique des suggestions d'IA
        Schema::create('category_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('suggested_name');
            $table->string('language_code', 5);
            $table->foreignId('similar_to_id')->nullable()->constrained('global_categories')->onDelete('cascade');
            $table->decimal('similarity_score', 3, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'merged']);
            $table->text('ai_reasoning')->nullable();
            $table->foreignId('suggested_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'similarity_score']);
            $table->index(['suggested_by', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_suggestions');
        Schema::dropIfExists('site_global_categories');
        Schema::dropIfExists('global_categories');
    }
};
