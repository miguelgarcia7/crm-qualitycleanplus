<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A temporary assignment opens a fixed-window child work order at another
     * property while the home WO stays open (ADR-0019). This flag distinguishes
     * those WOs so roster counts can exclude them and the daily auto-close job
     * can find them.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->boolean('is_temporary_assignment')->default(false)->after('source')->index();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropColumn('is_temporary_assignment');
        });
    }
};
