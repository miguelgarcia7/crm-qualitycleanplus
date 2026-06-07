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
     *
     * property_id is a bare indexed column for now; the real FK to `properties`
     * is added in Phase 02 (Property Bible) when that table exists.
     */
    public function up(): void
    {
        Schema::create('people_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->unsignedBigInteger('property_id')->index();
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
