<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\InvoiceItem;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Reports\Actions\RefreshMonthlyRevenue;
use App\Domain\Settings\Support\CompanySettings;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\TimeSummary;
use Illuminate\Support\Facades\DB;

/**
 * Generates a frozen invoice from an approved timesheet (ADR-0006). Idempotent
 * on timesheet.invoice_id; runs in a transaction; snapshots property + invoicer.
 */
class GenerateInvoice
{
    public function __construct(private readonly CompanySettings $company) {}

    public function handle(Timesheet $timesheet): Invoice
    {
        if ($timesheet->invoice_id !== null) {
            return Invoice::findOrFail($timesheet->invoice_id);
        }

        $invoice = DB::transaction(function () use ($timesheet): Invoice {
            $locked = Timesheet::query()->whereKey($timesheet->id)->lockForUpdate()->firstOrFail();
            if ($locked->invoice_id !== null) {
                return Invoice::findOrFail($locked->invoice_id);
            }

            $property = $locked->property;
            $period = $locked->payrollPeriod;

            $summaries = TimeSummary::query()
                ->where('payroll_period_id', $period->id)
                ->with(['workOrder.person:id,name', 'workOrder.position:id,name'])
                ->get();

            $invoice = Invoice::create([
                'property_id' => $property->id,
                'payroll_period_id' => $period->id,
                'timesheet_id' => $locked->id,
                'invoice_number' => $this->nextNumber(),
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays((int) config('qcp.invoice.payment_terms_days', 30))->toDateString(),
                'property_snapshot' => $this->propertySnapshot($property),
                'invoicer_snapshot' => $this->company->invoicer(),
                'tax_rate' => $property->tax_rate,
                'status' => InvoiceStatus::Draft,
            ]);

            $workSubtotal = 0;
            $reg = $ot = $hol = $trn = 0;
            $jobCodes = $property->jobCodesByPosition();

            foreach ($summaries as $summary) {
                $wo = $summary->workOrder;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'work_order_id' => $summary->work_order_id,
                    'contractor_name' => $wo->person->name,
                    'position_name' => $wo->position->name,
                    'job_code' => $jobCodes[$wo->position_id] ?? null,
                    'pay_rate' => $wo->pay_rate,
                    'ot_pay_rate' => $wo->ot_pay_rate,
                    'bill_rate' => $wo->bill_rate,
                    'ot_bill_rate' => $wo->ot_bill_rate,
                    'regular_minutes' => $summary->regular_minutes,
                    'overtime_minutes' => $summary->overtime_minutes,
                    'holiday_minutes' => $summary->holiday_minutes,
                    'training_minutes' => $summary->training_minutes,
                    'regular_amount_bill' => $summary->regular_amount_bill,
                    'overtime_amount_bill' => $summary->overtime_amount_bill,
                    'holiday_amount_bill' => $summary->holiday_amount_bill,
                    'training_amount_bill' => $summary->training_amount_bill,
                    'total_bill' => $summary->total_bill,
                    'total_payout' => $summary->total_pay,
                ]);

                $workSubtotal += $summary->total_bill;
                $reg += $summary->regular_minutes;
                $ot += $summary->overtime_minutes;
                $hol += $summary->holiday_minutes;
                $trn += $summary->training_minutes;
            }

            // Billable adjustments (always incentives — deductions are never
            // billable) add to the invoice; deductions are payroll-only (ADR-0014).
            $adjustmentTotal = (int) TimeEntryAdjustment::query()
                ->where('payroll_period_id', $period->id)
                ->where('is_billable', true)
                ->sum('value');

            $subtotal = $workSubtotal + $adjustmentTotal;
            $tax = (int) round($subtotal * (float) $property->tax_rate);

            $invoice->update([
                'work_subtotal' => $workSubtotal,
                'adjustment_total' => $adjustmentTotal,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total' => $subtotal + $tax,
                'total_regular_minutes' => $reg,
                'total_overtime_minutes' => $ot,
                'total_holiday_minutes' => $hol,
                'total_training_minutes' => $trn,
                'status' => InvoiceStatus::Invoiced,
                'frozen_at' => now(),
            ]);

            $locked->update(['invoice_id' => $invoice->id, 'status' => TimesheetStatus::Invoiced]);
            $period->update(['status' => PayrollPeriodStatus::Invoiced, 'invoiced_at' => now()]);

            return $invoice;
        });

        // Freezing changes billed truth — refresh the month's revenue cell (ADR-0028).
        app(RefreshMonthlyRevenue::class)->handle(
            $invoice->property_id,
            $timesheet->payrollPeriod->week_start->toDateString(),
        );

        return $invoice;
    }

    private function nextNumber(): string
    {
        $year = (int) now()->year;
        $seq = Invoice::query()->where('invoice_number', 'like', "INV-{$year}-%")->lockForUpdate()->count() + 1;

        return sprintf('INV-%d-%06d', $year, $seq);
    }

    /**
     * @return array<string, mixed>
     */
    private function propertySnapshot(Property $property): array
    {
        return [
            'name' => $property->name,
            'address' => $property->address,
            'city' => $property->city,
            'state' => $property->state,
            'zip' => $property->zip,
            'pm_name' => $property->pm_name,
            'main_phone' => $property->main_phone,
            'billing_email' => $property->billing_email,
        ];
    }
}
