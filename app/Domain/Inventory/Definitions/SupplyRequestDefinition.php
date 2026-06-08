<?php

namespace App\Domain\Inventory\Definitions;

use App\Domain\Inventory\Actions\FulfillSupplyRequest;
use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Models\Person;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\StepType;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;

/**
 * The supply-request workflow (ADR-0012/0014). Existing items go straight to a
 * Front Desk fulfillment step; new items first need an Admin approval step.
 * Fulfillment issues stock and (by category) creates an equipment assignment or
 * a contractor charge schedule.
 */
class SupplyRequestDefinition extends WorkflowDefinition
{
    public function __construct(private FulfillSupplyRequest $fulfill) {}

    public function type(): WorkflowType
    {
        return WorkflowType::SupplyRequest;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        $steps = [];
        $request = $workflow->subject;

        if ($request instanceof SupplyRequest && $request->isNewItem()) {
            $steps[] = StepBlueprint::humanRole(
                'approve_new_item',
                'Approve new item',
                'admin',
                'workflows.supply_request.approve_new_item',
                StepType::Approval,
            );
        }

        $steps[] = StepBlueprint::humanRole(
            'fulfill',
            'Fulfill request',
            'front_desk',
            'workflows.supply_request.fulfill',
            StepType::Action,
        );

        return $steps;
    }

    public function onStepCompleted(Workflow $workflow, WorkflowStep $step): void
    {
        $request = $workflow->subject;
        if (! $request instanceof SupplyRequest) {
            return;
        }

        match ($step->step_key) {
            'approve_new_item' => $request->update(['status' => SupplyRequestStatus::Approved]),
            'fulfill' => $this->fulfill->handle($request, $this->actor($step)),
            default => null,
        };
    }

    public function onStepRejected(Workflow $workflow, WorkflowStep $step): void
    {
        $request = $workflow->subject;

        if ($request instanceof SupplyRequest && $step->step_key === 'approve_new_item') {
            $request->update(['status' => SupplyRequestStatus::Denied]);
        }
    }

    private function actor(WorkflowStep $step): ?Person
    {
        return $step->completed_by === null ? null : Person::find($step->completed_by);
    }
}
