<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The local day of an imported day's hours (the hotel's file, one entry per
     * day). Imported hours have no clock times, so start/end stay null; this is
     * what puts them on a day, for holidays and the weekly grid.
     */
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->date('work_date')->nullable()->after('end_at_utc');
        });
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropColumn('work_date');
        });
    }
};
