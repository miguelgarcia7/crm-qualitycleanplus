<?php

namespace App\Domain\People\Definitions;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\StepType;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Notifications\WorkflowNotice;

/**
 * Change personal info (20-domain/workflows.md). An employee requests a change to
 * their own name/email/phone; HR verifies and applies it (or rejects). Verifying
 * separately from applying prevents social-engineering of contact details.
 * Subject = the Person being changed; `data.changes` carries the requested fields.
 */
class ChangePersonalInfoDefinition extends WorkflowDefinition
{
    /** Fields a request may change. */
    public const FIELDS = ['name', 'email', 'phone'];

    public function type(): WorkflowType
    {
        return WorkflowType::ChangePersonalInfo;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        return [StepBlueprint::humanRole(
            'verify_change',
            'Verify info change',
            'hr',
            'workflows.change_personal_info.verify',
            StepType::Approval,
        )];
    }

    public function onStepCompleted(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key !== 'verify_change') {
            return;
        }

        $person = Person::findOrFail((int) $workflow->subject_id);
        $changes = $workflow->data['changes'] ?? [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $changes)) {
                continue;
            }

            $person->{$field} = $changes[$field];
        }

        if ($person->isDirty('email')) {
            $person->email_verified_at = null;
        }

        if ($person->isDirty('phone')) {
            $person->normalized_phone = $this->normalizePhone((string) $person->phone);
        }

        $person->save();

        $workflow->initiator?->notify(new WorkflowNotice(
            $workflow,
            "Your personal-info change for {$person->name} was approved and applied.",
        ));
    }

    public function onStepRejected(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key !== 'verify_change') {
            return;
        }

        $workflow->initiator?->notify(new WorkflowNotice(
            $workflow,
            'Your personal-info change request was declined: '.($workflow->cancel_reason ?? ''),
        ));
    }

    /** Digits-only phone for matching (mirrors how contacts are normalized). */
    private function normalizePhone(string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return ($digits === null || $digits === '') ? null : substr($digits, 0, 15);
    }
}
