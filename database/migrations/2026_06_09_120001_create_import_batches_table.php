<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One weekly Excel hour-import for an import-only property (40-flows/import-hours.md).
     * The batch is the permanent audit record of an import: who uploaded what file,
     * for which property + payroll period, and what it produced. Not soft-deleted —
     * a rolled-back batch keeps its row with status = rolled_back.
     */
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->string('file_name');
            $table->string('file_hash', 64)->index();          // sha256 — duplicate-file guard
            $table->string('status')->default('parsing')->index();
            $table->json('pending_adjustments')->nullable();    // staged during the wizard, applied on commit
            $table->json('stats')->nullable();                  // committed totals for the audit/result screen
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
