<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hotel's own schedule for one work week, uploaded by its property
     * manager on QC Minute: any files (attached through `files`, polymorphic)
     * plus an optional note for the recruiter. One row per property and week.
     */
    public function up(): void
    {
        Schema::create('week_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('week_start');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('week_schedules');
    }
};
