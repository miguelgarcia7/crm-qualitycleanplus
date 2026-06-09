<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reverses a mistaken promotion (people-lifecycle.md): allowed only while the
 * person has NO work orders (rolling back later would orphan them). Status
 * returns to applicant, `converted_to_contractor_at` is cleared, the contractor
 * role is removed, and the application goes back to reviewing. Audit-logged.
 */
class ReversePromotion
{
    public function handle(JobApplication $application, Person $actor): void
    {
        /** @var Person $person */
        $person = $application->person;

        if ($application->status !== JobApplicationStatus::Promoted) {
            throw ValidationException::withMessages(['status' => 'Only promoted applications can be reversed.']);
        }

        if ($person->workOrders()->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This contractor already has work orders — the promotion can no longer be reversed.',
            ]);
        }

        DB::transaction(function () use ($application, $person, $actor): void {
            $person->forceFill([
                'status' => PersonStatus::Applicant,
                'converted_to_contractor_at' => null,
                'primary_recruiter_id' => null,
            ])->save();
            $person->removeRole('contractor');

            $application->update([
                'status' => JobApplicationStatus::Reviewing,
                'promoted_by' => null,
                'promoted_at' => null,
            ]);

            activity('people')
                ->performedOn($person)
                ->causedBy($actor)
                ->withProperties(['old_status' => PersonStatus::ContractorActive->value, 'new_status' => PersonStatus::Applicant->value])
                ->log("Reversed promotion of {$person->name} back to applicant");
        });
    }
}
