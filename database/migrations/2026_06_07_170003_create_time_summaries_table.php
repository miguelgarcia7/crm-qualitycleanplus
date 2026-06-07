<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Materialized per-(work_order, week) rollup (ADR-0008). Recomputed by the
     * RecomputeTimeSummary job after any time-entry change. Buckets: regular,
     * overtime, holiday, training. (Holiday stays 0 until the Phase 08 calendar.)
     */
    public function up(): void
    {
        Schema::create('time_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');

            foreach (['regular', 'overtime', 'holiday', 'training'] as $bucket) {
                $table->integer("{$bucket}_minutes")->default(0);
                $table->bigInteger("{$bucket}_amount_pay")->default(0);
                $table->bigInteger("{$bucket}_amount_bill")->default(0);
            }

            $table->bigInteger('total_pay')->default(0);
            $table->bigInteger('total_bill')->default(0);
            $table->timestamp('last_recomputed_at')->nullable();
            $table->timestamps();

            $table->unique(['work_order_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_summaries');
    }
};
