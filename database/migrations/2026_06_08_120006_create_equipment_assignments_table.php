<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks equipment issued to a person so it can be recovered at termination
     * (ADR-0012, ADR-0018). Created when an equipment supply request is fulfilled.
     * No serial numbers in v1.
     */
    public function up(): void
    {
        Schema::create('equipment_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_variant_id')->constrained('item_variants')->restrictOnDelete();
            $table->foreignId('assigned_to_person_id')->constrained('people')->restrictOnDelete();
            $table->unsignedBigInteger('source_request_id')->nullable(); // soft ref to supply_requests (Inc 4)
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('people')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('people')->nullOnDelete();
            $table->text('return_notes')->nullable();
            $table->enum('status', ['assigned', 'returned', 'lost', 'retired'])->default('assigned')->index();
            $table->timestamps();

            $table->index(['assigned_to_person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_assignments');
    }
};
