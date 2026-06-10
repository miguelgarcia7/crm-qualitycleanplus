<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KB article pivots (Phase 08c): categories and tags (plain M2M), plus
     * `kb_article_role` — the visibility table. Zero roles attached means the
     * article is visible to all authenticated users; one or more restricts it
     * to people holding a matching Spatie role (super_admin bypasses).
     */
    public function up(): void
    {
        Schema::create('kb_article_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('kb_categories')->cascadeOnDelete();

            $table->unique(['article_id', 'category_id']);
        });

        Schema::create('kb_article_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('kb_tags')->cascadeOnDelete();

            $table->unique(['article_id', 'tag_id']);
        });

        Schema::create('kb_article_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kb_article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();

            $table->unique(['kb_article_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_role');
        Schema::dropIfExists('kb_article_tag');
        Schema::dropIfExists('kb_article_category');
    }
};
