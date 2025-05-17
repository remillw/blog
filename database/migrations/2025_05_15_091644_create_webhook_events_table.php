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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_endpoint_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // article.created, article.updated, article.deleted, etc.
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->string('status')->default('pending'); // pending, success, failed
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
