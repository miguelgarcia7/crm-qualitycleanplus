<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KB articles (Phase 08c) — versioned rich-text articles. `slug` is the
     * route key (generated once, stable across title edits). `version` is
     * monotonic; every edit snapshots the prior state into
     * `kb_article_versions` first. Role-based visibility lives in the
     * `kb_article_role` pivot. The FULLTEXT index only exists on MySQL —
     * tests run on SQLite, where search falls back to LIKE.
     */
    public function up(): void
    {
        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('content');

            $table->foreignId('author_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('last_edited_by')->nullable()->constrained('people')->nullOnDelete();

            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('view_count')->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('version')->default(1);

            $table->timestamps();
            $table->softDeletes();
        });

        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('kb_articles', function (Blueprint $table) {
                $table->fullText(['title', 'summary', 'content'], 'kb_articles_fulltext');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
    }
};
