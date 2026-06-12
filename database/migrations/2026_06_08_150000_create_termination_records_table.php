<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The durable record of a termination (ADR-0018). One row per `termination`
     * workflow: it captures the form payload (type/reason), the physical-tasks
     * outcome (file move), the final-paycheck consolidation result, and the
     * QuickBooks-removal marker for reporting.
     */
    public function up(): void
    {
        Schema::create('termination_records', function (Blueprint $table) {
            $table->id();
            // restrict: the termination record is the durable audit trail — deleting
            // the workflow must never take the record (and its reason/notes) with it.
            $table->foreignId('workflow_id')->constrained('workflows')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('people')->nullOnDelete();

            $table->date('effective_date');
            $table->string('termination_type');
            $table->string('reason_category');
            $table->text('notes')->nullable();
            $table->boolean('rehireable')->default(true);

            // Physical tasks (front desk)
            $table->timestamp('file_moved_at')->nullable();
            $table->foreignId('file_moved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('terminated_at')->nullable();

            // Final paycheck (payroll)
            $table->foreignId('final_paycheck_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->timestamp('final_paycheck_processed_at')->nullable();
            $table->foreignId('final_paycheck_processed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->bigInteger('final_paycheck_consolidated_cents')->nullable();
            $table->bigInteger('final_paycheck_remainder_cents')->nullable();

            // QuickBooks removal marker (no integration in v1)
            $table->timestamp('quickbooks_removed_at')->nullable();

            // Cancellation (super-admin, before file move)
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termination_records');
    }
};
