<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marks a human step done, runs the definition's side-effect hook, then advances
 * the workflow. The caller is responsible for authorization (WorkflowPolicy).
 */
class CompleteStep
{
    public function __construct(
        private WorkflowRegistry $registry,
        private AdvanceWorkflow $advance,
    ) {}

    public function handle(WorkflowStep $step, Person $actor, ?string $notes = null): Workflow
    {
        return DB::transaction(function () use ($step, $actor, $notes): Workflow {
            $workflow = $step->workflow;

            if ($step->status !== StepStatus::Pending || $step->step_index !== $workflow->current_step_index) {
                throw ValidationException::withMessages(['step' => 'This task is no longer actionable.']);
            }

            $step->update([
                'status' => StepStatus::Done,
                'notes' => $notes,
                'completed_at' => now(),
                'completed_by' => $actor->id,
            ]);

            $this->registry->for($workflow->workflowType())->onStepCompleted($workflow, $step);

            $workflow->update(['current_step_index' => $step->step_index + 1]);
            $this->advance->handle($workflow);

            return $workflow->refresh();
        });
    }
}
