<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invoices are billed to properties, generated on timesheet approval and
     * frozen at creation (ADR-0006). Void/reissue columns exist but the flow is
     * Phase 03b. See 20-domain/invoicing.md.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->foreignId('timesheet_id')->unique()->constrained('timesheets')->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->date('issue_date');
            $table->date('due_date');

            // Frozen snapshots
            $table->json('property_snapshot');
            $table->json('invoicer_snapshot');
            $table->decimal('tax_rate', 5, 4)->default(0);

            // Totals (cents)
            $table->bigInteger('work_subtotal')->default(0);
            $table->bigInteger('adjustment_total')->default(0);
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('total')->default(0);

            // Hour totals (denormalized)
            $table->integer('total_regular_minutes')->default(0);
            $table->integer('total_overtime_minutes')->default(0);
            $table->integer('total_holiday_minutes')->default(0);
            $table->integer('total_training_minutes')->default(0);

            $table->enum('status', ['draft', 'invoiced', 'invoice_sent', 'voided'])->default('draft')->index();
            $table->timestamp('frozen_at')->nullable();
            $table->foreignId('frozen_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('notification_sent_at')->nullable();
            $table->foreignId('notification_sent_by')->nullable()->constrained('people')->nullOnDelete();
            $table->string('notification_recipient')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('void_reason')->nullable();
            $table->foreignId('replaces_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('replaced_by_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
