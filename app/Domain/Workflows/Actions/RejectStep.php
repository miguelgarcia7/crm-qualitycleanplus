<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rejects a pending step — this terminates the workflow (status = rejected) and
 * runs the definition's rejection hook (e.g. mark a supply request denied).
 */
class RejectStep
{
    public function __construct(private WorkflowRegistry $registry) {}

    public function handle(WorkflowStep $step, Person $actor, string $reason): Workflow
    {
        return DB::transaction(function () use ($step, $actor, $reason): Workflow {
            $workflow = $step->workflow;

            if ($step->status !== StepStatus::Pending || $step->step_index !== $workflow->current_step_index) {
                throw ValidationException::withMessages(['step' => 'This task is no longer actionable.']);
            }

            $step->update([
                'status' => StepStatus::Rejected,
                'notes' => $reason,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);

            $workflow->update([
                'status' => WorkflowStatus::Rejected,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            $this->registry->for($workflow->workflowType())->onStepRejected($workflow, $step);

            return $workflow->refresh();
        });
    }
}
