<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hotel-specific identifiers for a contractor (ADR-0004 / people-lifecycle).
     * One contractor may have different IDs at multiple import-only hotels, so
     * this is a separate table matched on (property_id, external_id) at import.
     * RESTRICT on property delete: a mapping shouldn't silently vanish.
     */
    public function up(): void
    {
        Schema::create('people_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('property_id')->index()->constrained('properties')->restrictOnDelete();
            $table->string('external_id');
            $table->string('source_system')->nullable();
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('people')->nullOnDelete();

            $table->unique(['property_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people_external_ids');
    }
};
