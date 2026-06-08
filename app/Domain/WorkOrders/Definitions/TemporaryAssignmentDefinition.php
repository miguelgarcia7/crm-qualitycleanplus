<?php

namespace App\Domain\WorkOrders\Definitions;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Actions\OpenTemporaryWorkOrder;
use App\Domain\WorkOrders\Jobs\ProcessTemporaryAssignmentEnds;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;

/**
 * Fixed-window temporary assignment to a host property (ADR-0019). A single
 * system step opens the temp child WO (home WO untouched) and notifies the host
 * property's recruiter. The temp WO is auto-closed at its end_date by
 * {@see ProcessTemporaryAssignmentEnds}.
 */
class TemporaryAssignmentDefinition extends WorkflowDefinition
{
    public function __construct(private OpenTemporaryWorkOrder $open) {}

    public function type(): WorkflowType
    {
        return WorkflowType::TemporaryAssignment;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        return [StepBlueprint::system('apply', 'Open temporary assignment')];
    }

    public function runSystemStep(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key !== 'apply') {
            return;
        }

        $data = $workflow->data ?? [];
        $home = WorkOrder::findOrFail($data['home_work_order_id']);
        $propertyId = (int) $data['new_property_id'];

        $temp = $this->open->handle($home, [
            'person_id' => $home->person_id,
            'property_id' => $propertyId,
            'position_id' => (int) $data['position_id'],
            'pay_rate' => (int) $data['pay_rate'],
            'bill_rate' => (int) $data['bill_rate'],
            'ot_pay_rate' => (int) $data['ot_pay_rate'],
            'ot_bill_rate' => (int) $data['ot_bill_rate'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
        ], $workflow->initiator);

        $property = Property::find($propertyId);
        $recruiters = Person::query()
            ->whereHas('propertyAssignments', fn ($q) => $q->where('property_id', $propertyId)->where('role', 'recruiter'))
            ->get();

        foreach ($recruiters as $recruiter) {
            $recruiter->notify(new WorkflowNotice(
                $workflow,
                "{$home->person?->name} is temporarily assigned to {$property?->name} until {$data['end_date']}.",
                ['work_order_id' => $temp->id],
            ));
        }
    }
}
