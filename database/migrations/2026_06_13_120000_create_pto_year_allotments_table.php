<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A W-2 employee's PTO year, hire-anniversary to anniversary (ADR-0016). Holds
     * the per-bucket allotment (base tier + mid-year top-ups); unused hours are
     * forfeited at close (no rollover).
     */
    public function up(): void
    {
        Schema::create('pto_year_allotments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->date('year_start');
            $table->date('year_end');
            $table->string('tier_at_year_start');
            $table->decimal('vacation_allotment', 6, 2)->default(0);
            $table->decimal('scheduled_allotment', 6, 2)->default(0);
            $table->decimal('unscheduled_allotment', 6, 2)->default(0);
            $table->string('status')->default('open')->index();
            $table->timestamp('closed_at')->nullable();
            $table->json('forfeited_hours_at_close')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'year_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_year_allotments');
    }
};
