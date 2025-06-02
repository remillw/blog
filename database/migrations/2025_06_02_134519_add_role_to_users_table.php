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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'moderator', 'admin', 'super_admin'])->default('user')->after('email');
            $table->json('permissions')->nullable()->after('role'); // Permissions spécifiques
            $table->boolean('is_active')->default(true)->after('permissions');
            $table->timestamp('last_activity_at')->nullable()->after('is_active');
            
            // Index pour les requêtes fréquentes
            $table->index(['role', 'is_active']);
            $table->index(['last_activity_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['last_activity_at']);
            $table->dropColumn(['role', 'permissions', 'is_active', 'last_activity_at']);
        });
    }
};
