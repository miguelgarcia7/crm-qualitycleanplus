<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job postings (Phase 08b-i) — advertised openings shown on the public job
     * board (legacy `positions`, renamed to avoid the Property-Bible `positions`
     * job-title catalog). A posting may point at a Property (`property_id`) or
     * just carry a free-text `location_label`. Only `published` postings are
     * public. `slug` is the public route key.
     */
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft')->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('pay_range')->nullable();
            $table->text('content')->nullable();
            $table->string('hour_start')->nullable();
            $table->string('hour_end')->nullable();

            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('location_label')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
