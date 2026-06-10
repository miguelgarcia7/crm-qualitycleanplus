<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Report rollups (ADR-0028) — derived data, rebuilt at will by
     * `reports:refresh-rollups`. Weekly = operational truth from time_summaries;
     * monthly = financial truth from frozen invoices.
     */
    public function up(): void
    {
        Schema::create('report_weekly_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');

            foreach (['regular', 'overtime', 'holiday', 'training'] as $bucket) {
                $table->integer("{$bucket}_minutes")->default(0);
            }
            $table->integer('total_minutes')->default(0);
            $table->bigInteger('total_pay')->default(0);
            $table->bigInteger('total_bill')->default(0);
            $table->unsignedInteger('contractor_count')->default(0);

            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'position_id', 'week_start']);
            $table->index('week_start');
        });

        Schema::create('report_monthly_revenue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->date('month_start');

            $table->unsignedInteger('invoice_count')->default(0);
            $table->bigInteger('work_subtotal')->default(0);
            $table->bigInteger('adjustment_total')->default(0);
            $table->bigInteger('tax_amount')->default(0);
            $table->bigInteger('invoiced_total')->default(0);
            $table->bigInteger('payout_total')->default(0);

            $table->timestamp('last_refreshed_at')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'month_start']);
            $table->index('month_start');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_monthly_revenue');
        Schema::dropIfExists('report_weekly_rollups');
    }
};
