<?php

namespace App\Domain\People\Actions;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Promotes an applicant to contractor_active (Phase 08b-ii, people-lifecycle.md).
 * Gated on the onboarding checklist (every non-waived item complete). Sets
 * `converted_to_contractor_at` on the first transition only, assigns the
 * contractor role, and defaults `primary_recruiter_id` to the promoting
 * recruiter. Reversible via {@see ReversePromotion} until work orders exist.
 */
class PromoteApplicantToContractor
{
    public function handle(JobApplication $application, Person $promoter): void
    {
        /** @var Person $person */
        $person = $application->person;

        if (! $person->status->isApplicant()) {
            throw ValidationException::withMessages(['status' => 'Only applicants can be promoted.']);
        }

        if (! $application->status->isPending()) {
            throw ValidationException::withMessages(['status' => 'This application has already been decided.']);
        }

        $missing = OnboardingChecklist::missingItems($person);
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'checklist' => 'Onboarding incomplete: '.implode(', ', $missing).'. Complete or waive these items first.',
            ]);
        }

        DB::transaction(function () use ($application, $person, $promoter): void {
            $person->forceFill([
                'status' => PersonStatus::ContractorActive,
                'converted_to_contractor_at' => $person->converted_to_contractor_at ?? now(),
                'primary_recruiter_id' => $promoter->hasRole('recruiter') ? $promoter->id : $person->primary_recruiter_id,
            ])->save();
            $person->syncRoles('contractor');

            $application->update([
                'status' => JobApplicationStatus::Promoted,
                'promoted_by' => $promoter->id,
                'promoted_at' => now(),
            ]);

            activity('people')
                ->performedOn($person)
                ->causedBy($promoter)
                ->withProperties(['old_status' => PersonStatus::Applicant->value, 'new_status' => PersonStatus::ContractorActive->value])
                ->log("Promoted {$person->name} to contractor");
        });
    }
}
