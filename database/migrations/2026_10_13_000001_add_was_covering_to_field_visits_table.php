<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A recruiter may check in at a hotel they aren't assigned to (covering for
     * someone). It's allowed and flagged for the office, frozen at check-in so a
     * later assignment change doesn't rewrite history (field-visits.md).
     */
    public function up(): void
    {
        Schema::table('field_visits', function (Blueprint $table) {
            $table->boolean('was_covering')->default(false)->after('was_inside_geofence');
        });
    }

    public function down(): void
    {
        Schema::table('field_visits', function (Blueprint $table) {
            $table->dropColumn('was_covering');
        });
    }
};
