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
        Schema::create('micro_apps', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('icon', 64);
            $table->unsignedSmallInteger('hue');
            $table->string('tags')->nullable();
            $table->boolean('visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->enum('target_type', ['internal', 'external'])->default('external');
            $table->string('target')->nullable();
            $table->timestamps();

            $table->index(['visible', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('micro_apps');
    }
};
