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
        Schema::table('site_topics', function (Blueprint $table) {
            $table->date('scheduled_date')->nullable()->after('last_used_at')->comment('Date prévue de publication du topic');
            $table->time('scheduled_time')->nullable()->after('scheduled_date')->default('09:00')->comment('Heure prévue de publication');
            $table->enum('status', ['draft', 'scheduled', 'published', 'cancelled'])->default('draft')->after('scheduled_time')->comment('Statut du topic dans le calendrier éditorial');
            $table->text('editorial_notes')->nullable()->after('status')->comment('Notes éditoriales pour le calendrier');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->after('editorial_notes')->comment('Utilisateur assigné pour la rédaction');
            $table->index('scheduled_date');
            $table->index(['status', 'scheduled_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_topics', function (Blueprint $table) {
            $table->dropColumn([
                'scheduled_date',
                'scheduled_time', 
                'status',
                'editorial_notes',
                'assigned_to_user_id'
            ]);
        });
    }
};
