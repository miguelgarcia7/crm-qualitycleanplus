<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One frozen line per work order on an invoice (ADR-0006). work_order_id is
     * stored as a plain id (the WO may later be closed) — not an FK.
     * See 20-domain/invoicing.md.
     */
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('work_order_id');

            $table->string('contractor_name');
            $table->string('position_name');
            $table->string('job_coding')->nullable();

            $table->bigInteger('pay_rate');
            $table->bigInteger('ot_pay_rate');
            $table->bigInteger('bill_rate');
            $table->bigInteger('ot_bill_rate');

            $table->integer('regular_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->integer('holiday_minutes')->default(0);
            $table->integer('training_minutes')->default(0);

            $table->bigInteger('regular_amount_bill')->default(0);
            $table->bigInteger('overtime_amount_bill')->default(0);
            $table->bigInteger('holiday_amount_bill')->default(0);
            $table->bigInteger('training_amount_bill')->default(0);

            $table->bigInteger('total_bill')->default(0);
            $table->bigInteger('total_payout')->default(0);

            $table->timestamps();

            $table->unique(['invoice_id', 'work_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
