<?php

namespace App\Domain\People\Policies;

use App\Domain\People\Models\Person;

/**
 * People directory authorization (permissions-matrix.md §People). Contractors
 * and staff are gated separately; recruiters are row-scoped to their OWN
 * contractors (primary_recruiter_id, ADR-0019) unless another role grants the
 * full roster. Applicants are never visible here — they live in the Applicants
 * section behind the `people.applicants.*` family.
 */
class PersonPolicy
{
    /** Roles whose `people.contractors.view` covers the full roster (matrix ✅, not "(own)"). */
    public const GLOBAL_CONTRACTOR_VIEWERS = ['super_admin', 'admin', 'office_manager', 'front_desk', 'hr', 'payroll'];

    /** Can open the directory at all (sees at least one tab). */
    public function viewAny(Person $user): bool
    {
        return $user->can('people.contractors.view') || $user->can('people.staff.view');
    }

    public function view(Person $user, Person $person): bool
    {
        if ($person->status->isApplicant()) {
            return false;
        }

        if ($person->status->isStaff()) {
            return $user->can('people.staff.view');
        }

        if (! $user->can('people.contractors.view')) {
            return false;
        }

        return $user->hasAnyRole(self::GLOBAL_CONTRACTOR_VIEWERS)
            || $person->primary_recruiter_id === $user->id;
    }
}
