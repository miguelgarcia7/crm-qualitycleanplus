<?php

namespace App\Domain\Recruiting\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A recruiter confirms a flagged application (see {@see SubmitApplication}) was
 * made by the existing person its email matched: the application moves onto
 * that record — restored if archived, so a returning person resurfaces with
 * their history — and the placeholder applicant made at intake is archived.
 * Restores one identity per human (people-lifecycle.md).
 *
 * Only before review starts: once onboarding documents land on the placeholder
 * there is more to move than an application. The existing record is never
 * overwritten with what the form said; the recruiter edits it if needed.
 */
class LinkApplicationToMatchedPerson
{
    public function handle(JobApplication $application): void
    {
        $match = $application->matchedPerson;

        if ($match === null) {
            throw ValidationException::withMessages(['match' => 'This application has no email match to link.']);
        }

        if ($application->status !== JobApplicationStatus::Submitted) {
            throw ValidationException::withMessages(['match' => 'Link the application before starting review.']);
        }

        /** @var Person $placeholder */
        $placeholder = $application->person;

        DB::transaction(function () use ($application, $match, $placeholder): void {
            if ($match->trashed()) {
                $match->restore();
            }

            $application->update(['person_id' => $match->id, 'matched_person_id' => null]);

            if (! $placeholder->jobApplications()->exists()) {
                $placeholder->delete();
            }
        });
    }
}
