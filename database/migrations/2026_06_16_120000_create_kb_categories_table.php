<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KB categories (Phase 08c) — hierarchical via `parent_id` (e.g. Operations
     * → Time Clock). Articles attach through the `kb_article_category` pivot
     * and may live in multiple categories. `slug` is the public route key.
     */
    public function up(): void
    {
        Schema::create('kb_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->foreignId('parent_id')->nullable()->constrained('kb_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_categories');
    }
};
