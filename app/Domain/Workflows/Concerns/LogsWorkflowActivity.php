<?php

namespace App\Domain\Workflows\Concerns;

use App\Domain\Workflows\Models\Workflow;

/**
 * Records workflow events to the `workflows` activity log (ADR-0010 audit trail).
 */
trait LogsWorkflowActivity
{
    protected function logWorkflow(Workflow $workflow, string $event, string $description): void
    {
        activity('workflows')
            ->performedOn($workflow)
            ->event($event)
            ->withProperties(['type' => $workflow->type, 'status' => $workflow->status->value])
            ->log($description);
    }
}
