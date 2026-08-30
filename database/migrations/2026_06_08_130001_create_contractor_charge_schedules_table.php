<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contractor charge spread over 1–4 payroll periods (ADR-0014). Created when
     * a uniform supply request with a charge is fulfilled. Each period's slice is
     * a contractor_charge_schedule_entry that becomes a payroll deduction.
     */
    public function up(): void
    {
        Schema::create('contractor_charge_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_request_id')->nullable(); // supply_requests
            $table->foreignId('person_id')->constrained('people')->restrictOnDelete();
            // What the deduction is for. Uniforms come from a supply request;
            // the hiring fee is set by 'CP when a contractor is taken on and has
            // no source request.
            $table->enum('reason', ['uniform', 'hiring_fee'])->default('uniform')->index();
            $table->bigInteger('total_amount'); // cents
            $table->unsignedTinyInteger('num_payments');
            $table->bigInteger('amount_per_payment'); // cents (base slice)
            $table->enum('status', ['active', 'completed', 'cancelled', 'accelerated_to_final_paycheck'])
                ->default('active')->index();
            $table->timestamps();

            $table->index('person_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_charge_schedules');
    }
};
