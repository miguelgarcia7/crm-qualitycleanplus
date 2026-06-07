<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Effective-dated pay/bill rates per (property, position), in CENTS (ADR /
     * 30-schema/conventions.md — money is BIGINT cents, never DECIMAL).
     *
     * A rate change ADDS a new row with a later effective_date; old rows are
     * never mutated (they're the rate for entries before the change). The
     * "current" rate is the latest row with effective_date <= today AND active.
     * These rates auto-fill work orders but don't force them (Phase 03).
     * See 20-domain/property-bible.md §3.
     */
    public function up(): void
    {
        Schema::create('property_position_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();

            $table->bigInteger('pay_rate');      // what QCP pays the contractor (cents)
            $table->bigInteger('bill_rate');     // what QCP charges the property (cents)
            $table->bigInteger('ot_pay_rate');   // overtime pay (cents)
            $table->bigInteger('ot_bill_rate');  // overtime bill (cents)

            $table->date('effective_date');
            $table->date('end_date')->nullable(); // set when superseded
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('people')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // One rate per property+position+effective_date; also serves as the
            // composite index for "latest effective_date" lookups.
            $table->unique(['property_id', 'position_id', 'effective_date'], 'prop_pos_rate_effective_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_position_rates');
    }
};
