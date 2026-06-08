<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An applied adjustment on a (person, payroll_period) — an incentive (adds to
     * pay; optionally billable) or a deduction (reduces pay; never billable).
     * `source_type`/`source_id` records the origin: a manual entry, a
     * supply-request charge, an import, etc. `value` is always positive cents;
     * `type` carries the sign semantically (ADR-0005, ADR-0014).
     */
    public function up(): void
    {
        Schema::create('time_entry_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('adjustment_item_id')->nullable()->constrained('adjustment_items')->nullOnDelete();

            $table->enum('source_type', [
                'manual',
                'supply_request',
                'supply_request_termination_consolidation',
                'import',
                'other',
            ])->default('manual');
            $table->unsignedBigInteger('source_id')->nullable(); // generic ref to the source entity

            $table->bigInteger('value'); // cents, always positive
            $table->enum('type', ['incentive', 'deduction']);
            $table->boolean('is_billable')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['payroll_period_id', 'type']);
            $table->index(['person_id', 'payroll_period_id']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entry_adjustments');
    }
};
