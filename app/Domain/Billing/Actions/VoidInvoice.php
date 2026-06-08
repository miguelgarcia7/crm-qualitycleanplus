<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Voids a frozen invoice (ADR-0006): marks it voided with a reason, voids its
 * timesheet, and reopens the payroll period so the week can be re-billed (e.g. a
 * corrected import). Reusable beyond imports. Already-voided invoices are a no-op.
 */
class VoidInvoice
{
    public function handle(Invoice $invoice, Person $actor, string $reason): Invoice
    {
        if ($invoice->status === InvoiceStatus::Voided) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice, $actor, $reason): Invoice {
            $invoice->update([
                'status' => InvoiceStatus::Voided,
                'voided_at' => now(),
                'voided_by' => $actor->id,
                'void_reason' => $reason,
            ]);

            Timesheet::query()->where('invoice_id', $invoice->id)->each(function (Timesheet $timesheet): void {
                $timesheet->update(['status' => TimesheetStatus::Voided]);
            });

            PayrollPeriod::query()->whereKey($invoice->payroll_period_id)->update([
                'status' => PayrollPeriodStatus::Open->value,
                'invoiced_at' => null,
                'locked_at' => null,
            ]);

            activity()->performedOn($invoice)->causedBy($actor)->log("Voided invoice {$invoice->invoice_number}: {$reason}");

            return $invoice;
        });
    }
}
