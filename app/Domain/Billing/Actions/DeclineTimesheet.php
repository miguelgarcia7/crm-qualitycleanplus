<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Notifications\TimesheetDecided;
use App\Notifications\TimesheetStatusChanged;
use Illuminate\Validation\ValidationException;

class DeclineTimesheet
{
    use LogsPropertyActivity;

    public function handle(Timesheet $timesheet, Person $decliner, string $reason, ?string $category = null): Timesheet
    {
        if (! $timesheet->status->canDecide()) {
            throw ValidationException::withMessages(['timesheet' => 'This timesheet is not awaiting approval.']);
        }

        $timesheet->forceFill([
            'status' => TimesheetStatus::Declined,
            'declined_at' => now(),
            'declined_by' => $decliner->id,
            'decline_reason' => $reason,
            'decline_category' => $category,
        ])->save();

        // Reopen the period so the recruiter can edit and resubmit.
        $timesheet->payrollPeriod->update([
            'status' => PayrollPeriodStatus::Open,
            'locked_at' => null,
            'locked_by' => null,
        ]);

        $this->logProperty($timesheet->property, 'updated', "Timesheet declined: {$reason}");

        if ($timesheet->sent_for_approval_by !== null) {
            $recruiter = Person::find($timesheet->sent_for_approval_by);
            $recruiter?->notify(new TimesheetStatusChanged($timesheet, 'declined', "Your timesheet was declined: {$reason}"));
            $recruiter?->notify(new TimesheetDecided($timesheet, approved: false));
        }

        return $timesheet;
    }
}
