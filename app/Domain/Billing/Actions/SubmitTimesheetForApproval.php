<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Notifications\TimesheetStatusChanged;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SubmitTimesheetForApproval
{
    use LogsPropertyActivity;

    public function handle(Timesheet $timesheet, Person $recruiter): Timesheet
    {
        if (! $timesheet->status->canSubmit()) {
            throw ValidationException::withMessages(['timesheet' => 'This timesheet cannot be submitted in its current state.']);
        }

        $timesheet->forceFill([
            'status' => TimesheetStatus::PendingApproval,
            'sent_for_approval_at' => now(),
            'sent_for_approval_by' => $recruiter->id,
            'declined_at' => null,
            'declined_by' => null,
            'decline_reason' => null,
            'decline_category' => null,
        ])->save();

        // Lock the period — no further edits except super_admin.
        $timesheet->payrollPeriod->update(['status' => PayrollPeriodStatus::Locked, 'locked_at' => now(), 'locked_by' => $recruiter->id]);

        $pms = Person::query()
            ->whereHas('propertyAssignments', fn ($q) => $q
                ->where('property_id', $timesheet->property_id)
                ->where('role', PropertyAssignmentRole::PropertyManager->value))
            ->get();
        Notification::send($pms, new TimesheetStatusChanged($timesheet, 'submitted', 'A timesheet is awaiting your approval.'));

        $this->logProperty($timesheet->property, 'updated', 'Timesheet sent for approval');

        return $timesheet;
    }
}
