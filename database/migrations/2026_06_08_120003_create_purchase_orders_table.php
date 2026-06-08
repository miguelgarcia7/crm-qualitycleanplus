<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A minimal purchase order (ADR-0012). No vendor tracking, delivery dates, or
     * partial receipts in v1 — receiving a PO records stock movements for all its
     * items. `source_request_id` links a PO created from a new-item request.
     */
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['draft', 'ordered', 'received', 'cancelled'])->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('source_request_id')->nullable(); // soft ref to supply_requests (Inc 4)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
