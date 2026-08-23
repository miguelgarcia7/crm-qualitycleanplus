<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holiday calendar (20-domain/property-bible.md) + the per-property opt-in
     * pivot. `legal` holidays are seeded with a recurrence rule from a fixed
     * vocabulary (e.g. fourth_thursday_november) and are read-only; `custom`
     * holidays are month/day dates repeating yearly. Work on an attached
     * holiday's date buckets as holiday time (config qcp.time.holiday_multiplier)
     * and never overtime, but still advances the weekly 40h counter.
     */
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 10); // HolidayType: legal | custom
            $table->string('rule')->nullable(); // legal only — recurrence vocabulary key
            $table->unsignedTinyInteger('month')->nullable(); // custom only
            $table->unsignedTinyInteger('day')->nullable(); // custom only
            $table->timestamps();
        });

        Schema::create('property_holiday', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('holiday_id')->constrained('holidays')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'holiday_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_holiday');
        Schema::dropIfExists('holidays');
    }
};
