<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an in-flight workflow (initiator or super_admin, per the workflow's
 * cancel permission). Runs the definition's cancellation hook. Its open steps
 * are skipped, so they leave the assignees' My Tasks inbox with it.
 */
class CancelWorkflow
{
    public function __construct(private WorkflowRegistry $registry) {}

    public function handle(Workflow $workflow, Person $actor, string $reason): Workflow
    {
        return DB::transaction(function () use ($workflow, $actor, $reason): Workflow {
            $workflow->update([
                'status' => WorkflowStatus::Cancelled,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);
            $workflow->steps()->where('status', StepStatus::Pending)->update(['status' => StepStatus::Skipped]);

            $this->registry->for($workflow->workflowType())->onCancelled($workflow);

            return $workflow->refresh();
        });
    }
}
