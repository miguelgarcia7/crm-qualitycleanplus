<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One payroll-period slice of a contractor charge schedule (ADR-0014). When
     * its period opens, ApplyScheduledContractorCharges turns it into a
     * non-billable time_entry_adjustment (the deduction).
     */
    public function up(): void
    {
        Schema::create('contractor_charge_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('contractor_charge_schedules')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->bigInteger('amount'); // cents
            $table->unsignedTinyInteger('payment_index');
            $table->enum('status', ['scheduled', 'applied', 'skipped'])->default('scheduled')->index('ccse_status_index');
            $table->foreignId('applied_adjustment_id')->nullable()->constrained('time_entry_adjustments')->nullOnDelete();
            $table->timestamps();

            $table->index(['payroll_period_id', 'status'], 'ccse_period_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_charge_schedule_entries');
    }
};
