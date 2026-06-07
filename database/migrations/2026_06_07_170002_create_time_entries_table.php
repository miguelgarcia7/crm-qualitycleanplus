<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Raw time events — one row per atomic event (ADR-0008). Rate snapshots are
     * copied from the work order at creation (ADR-0005). Reports run off
     * `time_summaries`, never off this table directly.
     *
     * Phase 03: source is manual_entry (recruiter) for now; clock_event/imported
     * arrive with Phase 07 / Phase 05. GPS + selfie columns deferred to Phase 07.
     */
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('work_order_id')->constrained('work_orders')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();

            $table->enum('source', ['clock_event', 'manual_entry', 'imported', 'adjustment_credit']);
            $table->enum('clock_method', ['tablet', 'qr', 'manual'])->nullable();
            $table->enum('entry_type', ['work', 'training'])->default('work');

            $table->timestamp('start_at_utc')->nullable();
            $table->timestamp('end_at_utc')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('timezone')->nullable();

            // Rate snapshots from the work order (cents).
            $table->bigInteger('pay_rate_snapshot');
            $table->bigInteger('bill_rate_snapshot');
            $table->bigInteger('ot_pay_rate_snapshot');
            $table->bigInteger('ot_bill_rate_snapshot');

            $table->json('source_metadata')->nullable();
            $table->boolean('was_updated')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['work_order_id', 'start_at_utc']);
            $table->index(['payroll_period_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
