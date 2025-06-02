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
        Schema::table('articles', function (Blueprint $table) {
            $table->string('language_code', 5)->default('fr')->after('site_id');
            
            // Ajouter un index pour optimiser les requêtes par langue
            $table->index(['site_id', 'language_code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'language_code', 'status']);
            $table->dropColumn('language_code');
        });
    }
};
