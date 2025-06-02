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
        Schema::create('ai_batch_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id')->unique()->nullable(); // ID du batch OpenAI
            $table->string('status')->default('pending'); // pending, submitted, completed, failed
            $table->json('requests'); // Les requêtes à envoyer
            $table->json('responses')->nullable(); // Les réponses reçues
            $table->integer('total_requests')->default(0);
            $table->integer('completed_requests')->default(0);
            $table->decimal('estimated_cost', 8, 4)->default(0);
            $table->decimal('actual_cost', 8, 4)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->index(['status', 'created_at']);
            $table->index('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_batch_jobs');
    }
};
