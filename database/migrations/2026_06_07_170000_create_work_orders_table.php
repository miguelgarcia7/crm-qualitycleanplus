<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A work order links a contractor to a property + position with specific
     * pay/bill rates. Every billable hour belongs to a WO. The WO is the
     * authoritative rate source for the time entries it owns (ADR-0005); the
     * Bible's rates only auto-fill the form. See 20-domain/work-orders.md.
     */
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();

            // Authoritative rates for this WO's time entries (cents).
            $table->bigInteger('pay_rate');
            $table->bigInteger('bill_rate');
            $table->bigInteger('ot_pay_rate');
            $table->bigInteger('ot_bill_rate');

            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = open-ended
            $table->enum('status', ['active', 'closed', 'suspended'])->default('active')->index();
            // Hours the contractor must work at this property before the hotel may
            // hire them directly (a commercial term protecting QCP against losing a
            // placement). Copied from the property default at creation and
            // overridable per work order. Measured against worked time, not
            // elapsed calendar time.
            $table->unsignedInteger('direct_hire_threshold_minutes');
            // Set once, when the contractor crosses the threshold, so the
            // recruiter is told exactly once rather than on every recompute.
            $table->timestamp('direct_hire_notified_at')->nullable();
            $table->enum('source', [
                'recruiter_created',
                'imported',
                'pay_increase',
                'transfer',
                'temporary_assignment',
            ])->default('recruiter_created');
            // Fixed-window child WO at another property while the home WO stays open
            // (ADR-0019) — excluded from roster counts, auto-closed by the daily job.
            $table->boolean('is_temporary_assignment')->default(false)->index();
            $table->foreignId('parent_wo_id')->nullable()->constrained('work_orders')->nullOnDelete();
            // more_staff_request_id (ADR-0021) is added in create_more_staff_requests —
            // that table is created later.
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
