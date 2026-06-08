<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stock-keeping variant of an item (ADR-0012). Non-variant items get one
     * variant with null size/color. `current_stock` is the running balance kept
     * in sync by stock_movements; `reorder_threshold` = 0 means "not tracked".
     */
    public function up(): void
    {
        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->string('sku')->nullable();
            $table->integer('current_stock')->default(0);
            $table->unsignedInteger('reorder_threshold')->default(0);
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['item_id', 'size', 'color'], 'item_variant_combo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variants');
    }
};
