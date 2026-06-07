<?php

namespace App\Domain\Billing\Policies;

use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Policies\PropertyPolicy;

/**
 * Timesheet authorization (10-architecture/permissions-matrix.md). Recruiters
 * submit on their own properties; PMs approve/decline on theirs.
 */
class TimesheetPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('timesheets.view_live');
    }

    public function view(Person $user, Timesheet $timesheet): bool
    {
        return $this->passes($user, $timesheet, 'timesheets.view_live');
    }

    public function submit(Person $user, Timesheet $timesheet): bool
    {
        return $this->passes($user, $timesheet, 'timesheets.submit_for_approval');
    }

    public function approve(Person $user, Timesheet $timesheet): bool
    {
        return $this->passes($user, $timesheet, 'timesheets.approve');
    }

    public function decline(Person $user, Timesheet $timesheet): bool
    {
        return $this->passes($user, $timesheet, 'timesheets.decline');
    }

    private function passes(Person $user, Timesheet $timesheet, string $permission): bool
    {
        if (! $user->can($permission)) {
            return false;
        }

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return true;
        }

        return $timesheet->property !== null && $user->isAssignedTo($timesheet->property);
    }
}
