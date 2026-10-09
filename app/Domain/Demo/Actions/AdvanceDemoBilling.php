<?php

namespace App\Domain\Demo\Actions;

use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\MarkInvoicePaid;
use App\Domain\Billing\Actions\SendInvoice;
use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Demo\Concerns\TravelsInTime;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Moves the demo property's finished weeks through the real billing pipeline
 * on a human cadence, so every stage is always on screen at once: the
 * recruiter submits Monday morning, the PM approves a week later (freezing the
 * invoice), it's emailed the next day, and paid about three weeks after the
 * week closed. Each step runs once its planned time has passed, stamped at
 * that time; anything a viewer already moved by hand (a decline, a manual
 * approval) is simply picked up from where it stands.
 */
class AdvanceDemoBilling
{
    use TravelsInTime;

    public function __construct(
        private SubmitTimesheetForApproval $submit,
        private ApproveTimesheet $approve,
        private SendInvoice $send,
        private MarkInvoicePaid $markPaid,
    ) {}

    public function handle(Property $property, CarbonImmutable $until): void
    {
        $recruiter = $this->assigned($property, PropertyAssignmentRole::Recruiter);
        $pm = $this->assigned($property, PropertyAssignmentRole::PropertyManager);
        $payroll = Person::role('payroll')->first();
        if ($recruiter === null || $pm === null || $payroll === null) {
            return;
        }

        $tz = $property->timezone;
        $periods = $property->payrollPeriods()
            ->with('timesheet')
            ->whereDate('week_end', '<', $until->setTimezone($tz)->toDateString())
            ->whereHas('timeEntries')
            ->orderBy('week_start')
            ->get();

        foreach ($periods as $period) {
            $timesheet = $period->timesheet;
            if ($timesheet === null) {
                continue;
            }

            $closed = CarbonImmutable::parse($period->week_end->toDateString(), $tz);
            $submitAt = $closed->addDay()->setTime(9, 30);
            $approveAt = $closed->addDays(8)->setTime(11, 0);
            $sendAt = $closed->addDays(9)->setTime(10, 0);
            $paidAt = $closed->addDays(22)->setTime(15, 0);

            if ($timesheet->status === TimesheetStatus::Draft && $submitAt <= $until) {
                try {
                    $this->at($submitAt, fn () => $this->submit->handle($timesheet, $recruiter));
                } catch (ValidationException) {
                    // A punch is still open (no clock-out) — leave the week in
                    // draft for a viewer to fix, as a recruiter would.
                    continue;
                }
            }

            if ($timesheet->status === TimesheetStatus::PendingApproval && $approveAt <= $until) {
                $timesheet = $this->at($approveAt, fn () => $this->approve->handle($timesheet, $pm));
            }

            $invoice = $timesheet->invoice_id !== null ? Invoice::find($timesheet->invoice_id) : null;
            if ($invoice === null) {
                continue;
            }

            if ($invoice->status === InvoiceStatus::Invoiced && $sendAt <= $until) {
                $recipient = $property->billing_email ?? (string) $pm->email;
                $this->at($sendAt, fn () => $this->send->handle($invoice, $recruiter, $recipient));
            }

            if ($invoice->status === InvoiceStatus::InvoiceSent && $invoice->paid_at === null && $paidAt <= $until) {
                $this->at($paidAt, fn () => $this->markPaid->handle($invoice, $payroll));
            }
        }
    }

    private function assigned(Property $property, PropertyAssignmentRole $role): ?Person
    {
        return Person::query()
            ->whereHas('propertyAssignments', fn ($q) => $q->where('property_id', $property->id)->where('role', $role->value))
            ->first();
    }
}
