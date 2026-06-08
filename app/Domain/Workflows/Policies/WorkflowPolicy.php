<?php

namespace App\Domain\Workflows\Policies;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Enums\StepStatus;
use App\Domain\Workflows\Models\WorkflowStep;

/**
 * Authorizes acting on workflow steps via the shared My Tasks surface. A step is
 * actionable when it is the workflow's current pending step, the user is its
 * assignee (by person or by role), and the user holds the step's required
 * permission (if any).
 */
class WorkflowPolicy
{
    public function act(Person $user, WorkflowStep $step): bool
    {
        if ($step->status !== StepStatus::Pending) {
            return false;
        }

        if ($step->step_index !== $step->workflow->current_step_index) {
            return false;
        }

        if (! $this->isAssignee($user, $step)) {
            return false;
        }

        return $step->required_permission === null || $user->can($step->required_permission);
    }

    private function isAssignee(Person $user, WorkflowStep $step): bool
    {
        if ($step->assigned_to !== null && $step->assigned_to === $user->id) {
            return true;
        }

        return $step->assigned_role !== null && $user->hasRole($step->assigned_role);
    }
}
