<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supply request (ADR-0012). Drives a `supply_request` workflow. Two paths:
     * an existing item (item_variant_id set) or a new item (proposed_* fields, no
     * variant until procured). Uniform requests for a contractor may carry a
     * charge to deduct over N payroll periods (ADR-0014).
     */
    public function up(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained('item_variants')->nullOnDelete();
            $table->enum('beneficiary_type', ['self', 'contractor', 'general_office'])->default('self');
            $table->foreignId('beneficiary_person_id')->nullable()->constrained('people')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('charge_amount')->nullable(); // cents, uniforms-to-contractor only
            $table->unsignedTinyInteger('split_payments')->nullable();
            $table->date('needed_by')->nullable();
            $table->text('purpose')->nullable();
            $table->text('notes')->nullable();

            // New-item path
            $table->string('proposed_item_name')->nullable();
            $table->text('proposed_description')->nullable();
            $table->bigInteger('estimated_cost')->nullable(); // cents

            $table->foreignId('requested_by')->nullable()->constrained('people')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'fulfilled', 'denied'])->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};
