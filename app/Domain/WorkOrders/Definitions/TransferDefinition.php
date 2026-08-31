<?php

namespace App\Domain\WorkOrders\Definitions;

use App\Domain\Adjustments\Models\ContractorChargeScheduleEntry;
use App\Domain\People\Models\Person;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Actions\SupersedeWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;
use Carbon\CarbonImmutable;

/**
 * Permanent transfer of a contractor to a new property/position (ADR-0019).
 * Fully automatic: a single system step closes the old WO and opens the new one,
 * reassigns the primary recruiter, remaps scheduled charges, and notifies.
 */
class TransferDefinition extends WorkflowDefinition
{
    public function __construct(private SupersedeWorkOrder $supersede) {}

    public function type(): WorkflowType
    {
        return WorkflowType::Transfer;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        return [StepBlueprint::system('apply', 'Apply transfer')];
    }

    public function runSystemStep(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key !== 'apply') {
            return;
        }

        $data = $workflow->data ?? [];
        $old = WorkOrder::findOrFail($data['work_order_id']);
        $contractor = $old->person;
        $newRecruiterId = isset($data['new_recruiter_id']) ? (int) $data['new_recruiter_id'] : null;

        $new = $this->supersede->handle($old, [
            'property_id' => (int) $data['new_property_id'],
            'position_id' => (int) $data['new_position_id'],
            'pay_rate' => (int) $data['pay_rate'],
            'bill_rate' => (int) $data['bill_rate'],
            'ot_pay_rate' => (int) $data['ot_pay_rate'],
            'ot_bill_rate' => (int) $data['ot_bill_rate'],
        ], WorkOrderSource::Transfer, CarbonImmutable::parse($data['effective_date']), $workflow->initiator);

        if ($newRecruiterId !== null && $contractor->primary_recruiter_id !== $newRecruiterId) {
            $contractor->update(['primary_recruiter_id' => $newRecruiterId]);
        }

        $this->remapCharges($contractor, (int) $data['new_property_id']);

        $recruiter = $newRecruiterId !== null ? Person::find($newRecruiterId) : null;
        $recruiter?->notify(new WorkflowNotice(
            $workflow,
            "{$contractor->name} has been transferred to your roster.",
            ['work_order_id' => $new->id],
        ));
    }

    /**
     * Best-effort: repoint still-scheduled charge entries to the new property's
     * payroll period for the same week (ADR-0019). Entries with no matching new
     * period are left as-is.
     */
    private function remapCharges(Person $contractor, int $newPropertyId): void
    {
        $entries = ContractorChargeScheduleEntry::query()
            ->where('status', 'scheduled')
            ->whereHas('schedule', fn ($q) => $q->where('person_id', $contractor->id)->where('status', 'active'))
            ->with('payrollPeriod')
            ->get();

        foreach ($entries as $entry) {
            $weekStart = $entry->payrollPeriod->week_start->toDateString();
            $target = PayrollPeriod::query()
                ->where('property_id', $newPropertyId)
                ->whereDate('week_start', $weekStart)
                ->first();

            if ($target !== null && $target->id !== $entry->payroll_period_id) {
                $entry->update(['payroll_period_id' => $target->id]);
            }
        }
    }
}
