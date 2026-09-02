<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Legacy-primary-key → new-primary-key crosswalk for the QC Minute cutover
     * (docs/80-plan/phase-final-cutover.md). Every `legacy:import` step records
     * what it created here, which is what makes re-runs update instead of
     * duplicate, and what lets later steps repoint rows whose legacy parents
     * were merged (several legacy ids can map to one new id).
     *
     * Operational import bookkeeping, like import_batches — not soft-deleted.
     */
    public function up(): void
    {
        Schema::create('legacy_id_map', function (Blueprint $table) {
            $table->id();
            $table->string('entity', 40); // 'person', 'property', 'work_order', ...
            $table->unsignedBigInteger('legacy_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['entity', 'legacy_id']);
            $table->index(['entity', 'new_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_id_map');
    }
};
