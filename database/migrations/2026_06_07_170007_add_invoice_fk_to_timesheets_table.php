<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * timesheets.invoice_id was left as a bare column until the invoices table
     * existed; promote it to a real FK now.
     */
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
        });
    }
};
