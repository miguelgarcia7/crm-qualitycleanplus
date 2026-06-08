<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\StepActor;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Models\Workflow;

/**
 * Walks the workflow forward from its current step: auto-runs leading `system`
 * steps, skips already-resolved steps, and stops at the first pending `human`
 * step (or completes the workflow when no steps remain). Idempotent and safe to
 * call after every state change.
 */
class AdvanceWorkflow
{
    public function __construct(private WorkflowRegistry $registry) {}

    public function handle(Workflow $workflow): void
    {
        if ($workflow->status->isTerminal()) {
            return;
        }

        $definition = $this->registry->for($workflow->workflowType());

        while (true) {
            $step = $workflow->steps()->where('step_index', $workflow->current_step_index)->first();

            if ($step === null) {
                $workflow->update([
                    'status' => WorkflowStatus::Completed,
                    'completed_at' => now(),
                ]);

                return;
            }

            if ($step->status === StepStatus::Done || $step->status === StepStatus::Skipped) {
                $workflow->increment('current_step_index');

                continue;
            }

            if ($step->status === StepStatus::Pending && $step->actor === StepActor::System) {
                $definition->runSystemStep($workflow, $step);
                $step->update(['status' => StepStatus::Done, 'completed_at' => now()]);
                $workflow->update([
                    'status' => WorkflowStatus::InProgress,
                    'current_step_index' => $workflow->current_step_index + 1,
                ]);

                continue;
            }

            // A pending human step — wait for action.
            if ($workflow->status === WorkflowStatus::Pending) {
                $workflow->update(['status' => WorkflowStatus::InProgress]);
            }

            return;
        }
    }
}
