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
        Schema::create('user_backlink_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('available_points')->default(20); // Points disponibles
            $table->integer('used_points')->default(0); // Points utilisés
            $table->integer('total_earned')->default(20); // Total des points gagnés
            $table->timestamp('last_recharge_at')->nullable(); // Dernière recharge automatique
            $table->timestamps();

            // Index et contraintes
            $table->unique('user_id');
            $table->index(['available_points']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_backlink_points');
    }
};
