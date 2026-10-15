<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When Admin was told the contract turned three years old, so the nightly
     * check sends it once (contracts are never deleted; this is a review nudge).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('three_year_notice_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('three_year_notice_at');
        });
    }
};
