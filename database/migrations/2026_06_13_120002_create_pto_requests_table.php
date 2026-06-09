<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A PTO request against a bucket within a year allotment (ADR-0016). Pending +
     * approved both reserve hours (deduct-on-submission); rejected/cancelled return them.
     */
    public function up(): void
    {
        Schema::create('pto_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            $table->foreignId('year_allotment_id')->constrained('pto_year_allotments')->restrictOnDelete();
            $table->string('bucket');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours', 6, 2);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('reject_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->boolean('is_self_approved')->default(false);
            $table->boolean('notice_period_warning_acknowledged')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pto_requests');
    }
};
