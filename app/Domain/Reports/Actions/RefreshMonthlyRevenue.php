<?php

namespace App\Domain\Reports\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Reports\Models\ReportMonthlyRevenue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the (property, month) cell of report_monthly_revenue from non-voided
 * frozen invoices (ADR-0028). A month is the calendar month of the invoice's
 * payroll-period week_start. Idempotent: zero invoices deletes the cell.
 */
class RefreshMonthlyRevenue
{
    public function handle(int $propertyId, string $monthStart): void
    {
        $month = CarbonImmutable::parse($monthStart)->startOfMonth();

        $invoices = Invoice::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'invoices.payroll_period_id')
            ->where('invoices.property_id', $propertyId)
            ->where('invoices.status', '!=', InvoiceStatus::Voided->value)
            ->whereBetween('payroll_periods.week_start', [
                $month->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->get(['invoices.id', 'invoices.work_subtotal', 'invoices.adjustment_total', 'invoices.tax_amount', 'invoices.total']);

        DB::transaction(function () use ($propertyId, $month, $invoices): void {
            if ($invoices->isEmpty()) {
                ReportMonthlyRevenue::query()
                    ->where('property_id', $propertyId)
                    ->whereDate('month_start', $month->toDateString())
                    ->delete();

                return;
            }

            ReportMonthlyRevenue::updateOrCreate(
                ['property_id' => $propertyId, 'month_start' => $month->toDateString()],
                [
                    'invoice_count' => $invoices->count(),
                    'work_subtotal' => (int) $invoices->sum('work_subtotal'),
                    'adjustment_total' => (int) $invoices->sum('adjustment_total'),
                    'tax_amount' => (int) $invoices->sum('tax_amount'),
                    'invoiced_total' => (int) $invoices->sum('total'),
                    'payout_total' => (int) InvoiceItem::query()
                        ->whereIn('invoice_id', $invoices->pluck('id'))
                        ->sum('total_payout'),
                    'last_refreshed_at' => now(),
                ],
            );
        });
    }
}
