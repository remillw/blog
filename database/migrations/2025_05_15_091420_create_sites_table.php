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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('platform_type')->comment('laravel, wordpress, prestashop');
            $table->string('api_key')->unique();
            $table->string('webhook_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('primary_color')->default('#4E8D44');
            $table->string('secondary_color')->default('#6b7280');
            $table->string('accent_color')->default('#10b981');
            $table->text('description')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
