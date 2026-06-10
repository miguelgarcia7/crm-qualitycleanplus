<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * KB article version snapshots (Phase 08c) — immutable history rows,
     * written *before* each article update with the pre-edit state. Never
     * edited or deleted (only cascade with their article). `author_id` is the
     * person who wrote the snapshotted state. `created_at` only — no updates.
     */
    public function up(): void
    {
        Schema::create('kb_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('kb_articles')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->foreignId('author_id')->constrained('people')->cascadeOnDelete();
            $table->string('change_summary', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['article_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_article_versions');
    }
};
