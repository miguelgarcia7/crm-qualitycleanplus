<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provisional v1 contract shape (90-open/contracts-data-model.md — the richer
     * structured model is deferred). Metadata + one uploaded file (via the
     * polymorphic `files` table). Restricted to super_admin + payroll at the
     * policy layer. See 20-domain/property-bible.md §4.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['msa', 'addendum', 'sow', 'other'])->default('other');
            $table->date('effective_date')->nullable();
            $table->date('expiration_date')->nullable()->index(); // drives renewal alerts
            $table->foreignId('uploaded_by')->nullable()
                ->constrained('people')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('replaces_contract_id')->nullable()
                ->constrained('contracts')->nullOnDelete(); // renewal lineage
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
