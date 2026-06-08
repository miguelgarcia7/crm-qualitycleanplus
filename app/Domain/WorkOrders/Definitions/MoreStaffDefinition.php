<?php

namespace App\Domain\WorkOrders\Definitions;

use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\StepType;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Notifications\WorkflowNotice;

/**
 * More-staff request (ADR-0021). A PM asks for more contractors at a property;
 * the assigned recruiter fulfills it by placing people (linking work orders) or
 * declines. Fulfillment auto-completes the step via {@see RecordMoreStaffPlacement};
 * decline rejects it; PM/super-admin can cancel. Subject = the MoreStaffRequest.
 */
class MoreStaffDefinition extends WorkflowDefinition
{
    public function type(): WorkflowType
    {
        return WorkflowType::MoreStaff;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        return [StepBlueprint::humanRole(
            'fulfill_staffing',
            'Fulfill staffing request',
            'recruiter',
            'workflows.more_staff.fulfill',
            StepType::Action,
        )];
    }

    public function onStart(Workflow $workflow): void
    {
        $request = $this->request($workflow);

        if ($request === null) {
            return;
        }

        $request->assignedRecruiter?->notify(new WorkflowNotice(
            $workflow,
            "New staffing request: {$request->quantity_requested} × {$request->position?->name} at {$request->property?->name} by {$request->by_date->toDateString()}.",
            ['more_staff_request_id' => $request->id],
        ));
    }

    public function onStepRejected(Workflow $workflow, WorkflowStep $step): void
    {
        $request = $this->request($workflow);

        if ($request === null) {
            return;
        }

        $request->update([
            'status' => MoreStaffStatus::Declined,
            'declined_at' => now(),
            'declined_by' => $workflow->completed_by,
            'decline_reason' => $workflow->cancel_reason,
        ]);

        $request->initiatedBy?->notify(new WorkflowNotice(
            $workflow,
            'Your staffing request was declined: '.($workflow->cancel_reason ?? ''),
            ['more_staff_request_id' => $request->id],
        ));
    }

    public function onCancelled(Workflow $workflow): void
    {
        $request = $this->request($workflow);

        if ($request === null) {
            return;
        }

        $request->update([
            'status' => MoreStaffStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $workflow->completed_by,
            'cancel_reason' => $workflow->cancel_reason,
        ]);

        $request->assignedRecruiter?->notify(new WorkflowNotice(
            $workflow,
            "Staffing request for {$request->position?->name} at {$request->property?->name} was cancelled.",
            ['more_staff_request_id' => $request->id],
        ));
    }

    private function request(Workflow $workflow): ?MoreStaffRequest
    {
        $subject = $workflow->subject;

        return $subject instanceof MoreStaffRequest ? $subject : null;
    }
}
