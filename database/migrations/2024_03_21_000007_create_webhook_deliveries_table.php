<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->onDelete('cascade');
            $table->string('event');
            $table->json('payload');
            $table->json('response')->nullable();
            $table->integer('status_code')->nullable();
            $table->text('error_message')->nullable();
            $table->morphs('deliverable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
}; 