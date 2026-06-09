<?php

namespace App\Domain\Recruiting\Policies;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Models\JobApplication;

/**
 * Applicant review authorization (Phase 08b-ii, ADR-0013). Front desk can view
 * applicants and edit ONLY the onboarding checklist; review decisions need
 * `people.applicants.edit`; promotion needs `people.applicants.promote`.
 * Reversal: the promoter may reverse their own promotion, super_admin anyone
 * (people-lifecycle.md) — and only while the person has no work orders (checked
 * in the action, not here).
 */
class JobApplicationPolicy
{
    public function viewAny(Person $user): bool
    {
        return $user->can('people.applicants.view');
    }

    public function view(Person $user, JobApplication $application): bool
    {
        return $user->can('people.applicants.view');
    }

    /** Start review / reject — a review decision. */
    public function review(Person $user, JobApplication $application): bool
    {
        return $user->can('people.applicants.edit');
    }

    /** Upload docs, verify I-9, set background status, waive items. */
    public function editChecklist(Person $user, JobApplication $application): bool
    {
        return $user->can('people.applicants.onboarding_checklist.edit');
    }

    public function promote(Person $user, JobApplication $application): bool
    {
        return $user->can('people.applicants.promote');
    }

    public function reverse(Person $user, JobApplication $application): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('people.applicants.promote') && $application->promoted_by === $user->id;
    }
}
