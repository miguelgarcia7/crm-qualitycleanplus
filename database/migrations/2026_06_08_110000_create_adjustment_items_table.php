<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of reusable manual adjustment templates (e.g. "Pickup Fee",
     * "Referral Bonus"). Uniform/charge-schedule deductions do NOT use this
     * catalog — they enter via the supply-request chain (ADR-0014). See
     * 20-domain/adjustments.md.
     */
    public function up(): void
    {
        Schema::create('adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('default_value')->nullable(); // cents
            $table->enum('type', ['incentive', 'deduction']);
            $table->boolean('is_billable')->default(false); // deductions are never billable
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustment_items');
    }
};
