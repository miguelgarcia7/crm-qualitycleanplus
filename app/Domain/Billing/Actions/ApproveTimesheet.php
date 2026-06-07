<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Notifications\TimesheetStatusChanged;
use Illuminate\Validation\ValidationException;

class ApproveTimesheet
{
    use LogsPropertyActivity;

    public function __construct(private GenerateInvoice $generateInvoice) {}

    public function handle(Timesheet $timesheet, Person $approver): Timesheet
    {
        if (! $timesheet->status->canDecide()) {
            throw ValidationException::withMessages(['timesheet' => 'This timesheet is not awaiting approval.']);
        }

        $timesheet->forceFill([
            'status' => TimesheetStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ])->save();

        $this->logProperty($timesheet->property, 'updated', 'Timesheet approved');

        // Approval auto-generates the frozen invoice (ADR-0006/0007) → invoiced.
        $this->generateInvoice->handle($timesheet);
        $timesheet->refresh();

        if ($timesheet->sent_for_approval_by !== null) {
            $recruiter = Person::find($timesheet->sent_for_approval_by);
            $recruiter?->notify(new TimesheetStatusChanged($timesheet, 'approved', 'Your timesheet was approved; the invoice is ready to send.'));
        }

        return $timesheet;
    }
}
