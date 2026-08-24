<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Job codes: the client property's own accounting/GL code for a position
     * (hotel A bills "Housekeeper" as 1001-10, hotel B as 5500-01). One code
     * per (property, position) — deliberately NOT on property_position_rates,
     * whose rows are effective-dated. Snapshotted onto invoice_items at freeze
     * (ADR-0006) so historical invoices keep the code they were issued with.
     */
    public function up(): void
    {
        Schema::create('property_position_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->restrictOnDelete();
            $table->string('job_code', 64);
            $table->timestamps();

            $table->unique(['property_id', 'position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_position_codes');
    }
};
