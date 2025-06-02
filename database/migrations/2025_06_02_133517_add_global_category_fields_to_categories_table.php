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
        Schema::table('categories', function (Blueprint $table) {
            // Champs pour tracer la migration vers les catégories globales
            $table->foreignId('global_category_id')->nullable()->constrained('global_categories')->onDelete('set null');
            $table->timestamp('migrated_at')->nullable();
            $table->boolean('is_legacy')->default(false); // Marquer comme ancienne catégorie
            
            // Index pour les requêtes
            $table->index(['global_category_id']);
            $table->index(['is_legacy']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['global_category_id']);
            $table->dropColumn(['global_category_id', 'migrated_at', 'is_legacy']);
        });
    }
};
