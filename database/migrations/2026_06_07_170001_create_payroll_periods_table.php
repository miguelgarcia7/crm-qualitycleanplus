<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A property's pay week as a first-class row (ADR-0009). Created in advance
     * by EnsurePayrollPeriods. Gates edits: open → editable; locked → timesheet
     * submitted; invoiced → invoice generated. See 20-domain/time-tracking.md.
     */
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('week_start')->index(); // history lists filter and sort on this
            $table->date('week_end');
            $table->enum('status', ['open', 'locked', 'invoiced', 'closed'])->default('open')->index();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
