<?php

namespace App\Domain\FieldVisits\Policies;

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;

/**
 * One field visit: who may open it (and its selfie), close it for a recruiter
 * who forgot, or fix its times. Recruiters see only their own; nobody edits a
 * visit after the fact except super admin, and always with a reason.
 */
class FieldVisitPolicy
{
    public function view(Person $user, FieldVisit $visit): bool
    {
        return $user->canAny(['field_visits.view_all', 'field_visits.view_by_property'])
            || ($user->can('field_visits.view_own') && $visit->person_id === $user->id);
    }

    /** Close a visit someone forgot to check out of. */
    public function close(Person $user, FieldVisit $visit): bool
    {
        return $visit->check_out_at === null && $user->can('field_visits.close_any');
    }

    public function fixTimes(Person $user, FieldVisit $visit): bool
    {
        return $user->can('field_visits.manual_edit');
    }
}
