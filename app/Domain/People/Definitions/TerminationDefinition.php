<?php

namespace App\Domain\People\Definitions;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\TerminationRecord;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Actions\CloseWorkOrder;
use Illuminate\Database\Eloquent\Builder;

/**
 * Termination (ADR-0018). Ends a person's tenure across three roles: system
 * steps flip status and clean up rosters; front desk recovers equipment and
 * moves the physical file; payroll processes the final paycheck. A final system
 * step records the QuickBooks removal. The flow is identical for every
 * termination type — type/reason are captured for reporting, not branching.
 *
 * Subject = the terminated `Person`; `data` carries the form payload
 * (effective_date, termination_type, reason_category, notes, rehireable) plus
 * the pre-termination status for cancellation.
 */
class TerminationDefinition extends WorkflowDefinition
{
    public function __construct(
        private CloseWorkOrder $closeWorkOrder,
        private CancelWorkflow $cancelWorkflow,
    ) {}

    public function type(): WorkflowType
    {
        return WorkflowType::Termination;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        return [
            StepBlueprint::system('initialize', 'Initialize termination'),
            StepBlueprint::system('remove_from_rosters', 'Close work orders & roster removal'),
            StepBlueprint::system('cancel_pending_workflows', 'Cancel pending workflows'),
            StepBlueprint::humanRole('recover_equipment', 'Recover equipment', 'front_desk', 'workflows.termination.physical_tasks'),
            StepBlueprint::humanRole('move_file', 'Move physical file', 'front_desk', 'workflows.termination.physical_tasks'),
            StepBlueprint::humanRole('process_final_paycheck', 'Process final paycheck', 'payroll', 'workflows.termination.payroll_tasks'),
            StepBlueprint::system('finalize', 'Record QuickBooks removal'),
        ];
    }

    public function onStart(Workflow $workflow): void
    {
        $person = $this->person($workflow);

        // Remember the pre-termination status so a super-admin cancel can revert it.
        $workflow->update(['data' => array_merge($workflow->data ?? [], [
            'previous_status' => $person->status->value,
        ])]);

        $data = $workflow->data ?? [];

        TerminationRecord::create([
            'workflow_id' => $workflow->id,
            'person_id' => $person->id,
            'initiated_by' => $workflow->initiator_id,
            'effective_date' => $data['effective_date'],
            'termination_type' => $data['termination_type'],
            'reason_category' => $data['reason_category'],
            'notes' => $data['notes'] ?? null,
            'rehireable' => $data['rehireable'] ?? true,
        ]);
    }

    public function runSystemStep(Workflow $workflow, WorkflowStep $step): void
    {
        match ($step->step_key) {
            'initialize' => $this->initialize($workflow),
            'remove_from_rosters' => $this->removeFromRosters($workflow),
            'cancel_pending_workflows' => $this->cancelPendingWorkflows($workflow),
            'finalize' => $this->finalize($workflow),
            default => null,
        };
    }

    public function onStepCompleted(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key !== 'move_file') {
            return;
        }

        $person = $this->person($workflow);
        $person->update(['status' => PersonStatus::Terminated, 'terminated_at' => now()]);

        $this->record($workflow)?->update([
            'file_moved_at' => $step->completed_at ?? now(),
            'file_moved_by' => $step->completed_by,
            'terminated_at' => now(),
        ]);
    }

    public function onCancelled(Workflow $workflow): void
    {
        $data = $workflow->data ?? [];
        $previous = $data['previous_status'] ?? PersonStatus::ContractorActive->value;

        $this->person($workflow)->update(['status' => $previous]);

        $this->record($workflow)?->update([
            'cancelled_at' => now(),
            'cancelled_by' => $workflow->completed_by,
            'cancellation_reason' => $workflow->cancel_reason,
        ]);
    }

    /** Step 1: flag the person pending termination. */
    private function initialize(Workflow $workflow): void
    {
        $this->person($workflow)->update(['status' => PersonStatus::PendingTermination]);
    }

    /** Step 2: close active work orders at the effective date; detach property assignments. */
    private function removeFromRosters(Workflow $workflow): void
    {
        $person = $this->person($workflow);
        $effectiveDate = $workflow->data['effective_date'] ?? null;

        foreach ($person->workOrders()->active()->get() as $workOrder) {
            $this->closeWorkOrder->handle($workOrder, $effectiveDate);
        }

        $person->propertyAssignments()->delete();
    }

    /** Step 3: cancel the person's other in-flight workflows (about them or started by them). */
    private function cancelPendingWorkflows(Workflow $workflow): void
    {
        $person = $this->person($workflow);
        $actor = $workflow->initiator;

        if ($actor === null) {
            return;
        }

        $others = Workflow::query()
            ->where('id', '!=', $workflow->id)
            ->whereIn('status', [WorkflowStatus::Pending->value, WorkflowStatus::InProgress->value])
            ->where(function (Builder $q) use ($person): void {
                $q->where(fn (Builder $s) => $s->where('subject_type', $person->getMorphClass())->where('subject_id', $person->id))
                    ->orWhere('initiator_id', $person->id);
            })
            ->get();

        foreach ($others as $other) {
            $this->cancelWorkflow->handle($other, $actor, 'Subject was terminated');
        }
    }

    /** Step 7: record the QuickBooks removal marker (no integration in v1). */
    private function finalize(Workflow $workflow): void
    {
        $this->record($workflow)?->update(['quickbooks_removed_at' => now()]);
    }

    private function person(Workflow $workflow): Person
    {
        return Person::findOrFail((int) $workflow->subject_id);
    }

    private function record(Workflow $workflow): ?TerminationRecord
    {
        return TerminationRecord::query()->where('workflow_id', $workflow->id)->first();
    }
}
