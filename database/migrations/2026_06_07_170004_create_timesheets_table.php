<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly per-(property, period) aggregation with an approval lifecycle that
     * gates invoice generation (ADR-0007). One timesheet per payroll period.
     * `invoice_id` is unconstrained here — timesheets ↔ invoices is circular;
     * the FK is promoted in create_invoices.
     */
    public function up(): void
    {
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('payroll_period_id')->unique()->constrained('payroll_periods')->cascadeOnDelete();
            $table->enum('source', ['clock_in', 'imported'])->default('clock_in');
            $table->enum('status', [
                'draft', 'pending_approval', 'declined', 'approved', 'invoiced', 'invoice_sent', 'voided',
            ])->default('draft')->index();

            $table->timestamp('sent_for_approval_at')->nullable();
            $table->foreignId('sent_for_approval_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('declined_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('decline_reason')->nullable();
            $table->string('decline_category')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('people')->nullOnDelete();

            $table->unsignedBigInteger('invoice_id')->nullable(); // FK promoted in create_invoices
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
