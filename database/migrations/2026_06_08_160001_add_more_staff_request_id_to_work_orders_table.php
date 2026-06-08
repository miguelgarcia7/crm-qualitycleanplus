<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a work order to the more-staff request it fulfills (ADR-0021).
     * Nullable — most WOs are not tied to a request.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('more_staff_request_id')->nullable()->after('parent_wo_id')
                ->constrained('more_staff_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('more_staff_request_id');
        });
    }
};
