<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a property's billable hours arrive (Phase 05). `clock_in` is the default
     * (device / QC Minute clock events); `import` properties have no clock-in and
     * receive a weekly Excel export through the import wizard. See
     * 40-flows/import-hours.md and ADR-0007.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('time_source', ['clock_in', 'import'])
                ->default('clock_in')
                ->after('status')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('time_source');
        });
    }
};
