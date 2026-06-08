<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The unified stock ledger (ADR-0012, ADR-0015). Every change to a variant's
     * stock is a row here; `quantity` is signed (positive in, negative out).
     * `current_stock` on item_variants is the running balance these maintain.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained('item_variants')->restrictOnDelete();
            $table->enum('movement_type', [
                'purchase_order_receipt',
                'direct_receipt',
                'count_correction',
                'issuance',
                'manual_issuance',
                'return',
            ]);
            $table->integer('quantity'); // signed
            $table->text('reason')->nullable();
            $table->foreignId('related_purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete();
            $table->unsignedBigInteger('related_request_id')->nullable(); // soft ref to supply_requests (Inc 4)
            $table->foreignId('related_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->foreignId('recipient_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('recipient_type', ['contractor', 'staff', 'external', 'unspecified'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_variant_id', 'created_at']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
