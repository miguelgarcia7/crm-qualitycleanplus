<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 01 left `people_external_ids.property_id` as a bare indexed column
     * because `properties` didn't exist yet. Now it does — promote it to a real
     * FK (RESTRICT on delete: an external-id mapping shouldn't silently vanish
     * with a property). Import matching logic still lands in Phase 05.
     */
    public function up(): void
    {
        Schema::table('people_external_ids', function (Blueprint $table) {
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('people_external_ids', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
        });
    }
};
