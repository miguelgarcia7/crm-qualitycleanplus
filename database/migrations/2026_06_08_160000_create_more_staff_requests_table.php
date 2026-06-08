<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A property manager's request for more contractors at their property
     * (ADR-0021). Drives a `more_staff` workflow; fulfillment is tracked by
     * linking work orders (see work_orders.more_staff_request_id). One position
     * per request.
     */
    public function up(): void
    {
        Schema::create('more_staff_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->foreignId('property_id')->constrained('properties')->restrictOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_fulfilled')->default(0);
            $table->date('by_date');
            $table->string('urgency')->default('normal');
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->foreignId('initiated_by')->nullable()->constrained('people')->nullOnDelete();
            $table->foreignId('assigned_recruiter_id')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('declined_at')->nullable();
            $table->foreignId('declined_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('decline_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('more_staff_requests');
    }
};
