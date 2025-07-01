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
        // Vérifier si la colonne existe déjà avant de l'ajouter
        if (!Schema::hasColumn('site_topics', 'article_id')) {
            Schema::table('site_topics', function (Blueprint $table) {
                $table->foreignId('article_id')->nullable()->after('assigned_to_user_id')->constrained()->onDelete('set null')->comment('Article généré à partir de ce topic');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('site_topics', 'article_id')) {
            Schema::table('site_topics', function (Blueprint $table) {
                $table->dropForeign(['article_id']);
                $table->dropColumn('article_id');
            });
        }
    }
};
