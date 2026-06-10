<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polymorphic feedback (Phase 08c) — "Was this helpful?" votes and
     * free-form suggestions/issues/questions. KB articles first; designed to
     * point at other models later. Votes (helpful / not_helpful) are
     * deduplicated per person per record in the action layer.
     */
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->id();
            $table->morphs('feedbackable');
            $table->foreignId('person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('type')->index();
            $table->text('message')->nullable();
            $table->string('url')->nullable();
            $table->string('user_agent')->nullable();

            $table->boolean('is_resolved')->default(false)->index();
            $table->foreignId('resolved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('admin_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
