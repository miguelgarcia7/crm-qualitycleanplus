<?php

namespace App\Domain\WorkOrders\Definitions;

use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Definitions\StepBlueprint;
use App\Domain\Workflows\Definitions\WorkflowDefinition;
use App\Domain\Workflows\Enums\StepType;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Actions\SupersedeWorkOrder;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Notifications\WorkflowNotice;
use Illuminate\Validation\ValidationException;

/**
 * Pay increase (ADR-0020). PM-initiated requests need recruiter approval (with
 * editable rates honoring the PM's requested bill increase); recruiter-initiated
 * requests apply immediately. Either way the effect is to supersede the WO with
 * the new rates at the chosen payroll period. PM sees bill rates, the contractor
 * sees pay rates.
 */
class PayIncreaseDefinition extends WorkflowDefinition
{
    public function __construct(private SupersedeWorkOrder $supersede) {}

    public function type(): WorkflowType
    {
        return WorkflowType::PayIncrease;
    }

    /**
     * @return list<StepBlueprint>
     */
    public function steps(Workflow $workflow): array
    {
        // Recruiter-initiated requests carry approved rates already; they apply in
        // onStart with no human step. PM-initiated requests need approval first.
        if (($workflow->data['source'] ?? null) === 'recruiter') {
            return [];
        }

        return [StepBlueprint::humanRole(
            'approve_pay_increase',
            'Approve pay increase',
            'recruiter',
            'workflows.pay_increase.approve',
            StepType::Approval,
        )];
    }

    public function onStart(Workflow $workflow): void
    {
        if (($workflow->data['source'] ?? null) === 'recruiter') {
            $this->apply($workflow);

            return;
        }

        $data = $workflow->data ?? [];
        $this->logOnContractor($workflow, sprintf(
            'Pay increase requested by %s: +%s/hr on the bill rate%s',
            $workflow->initiator->name ?? 'the property manager',
            $this->money((int) ($data['pm_requested_increase_cents'] ?? 0)),
            filled($data['reason'] ?? null) ? " — \"{$data['reason']}\"" : '',
        ));
    }

    public function onCancelled(Workflow $workflow): void
    {
        $this->logOnContractor($workflow, 'Pay increase request cancelled'.($workflow->cancel_reason ? ": {$workflow->cancel_reason}" : ''));
    }

    public function onStepCompleted(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key === 'approve_pay_increase') {
            $this->apply($workflow);
        }
    }

    public function onStepRejected(Workflow $workflow, WorkflowStep $step): void
    {
        if ($step->step_key !== 'approve_pay_increase') {
            return;
        }

        $this->logOnContractor($workflow, 'Pay increase declined: '.($workflow->cancel_reason ?? ''));
        $workflow->initiator?->notify(new WorkflowNotice(
            $workflow,
            'Your pay increase request was declined: '.($workflow->cancel_reason ?? ''),
        ));
    }

    /** Create the superseding WO from approved rates and notify the parties. */
    private function apply(Workflow $workflow): void
    {
        $data = $workflow->data ?? [];
        $approved = $data['approved'] ?? null;

        if (! is_array($approved)) {
            throw ValidationException::withMessages([
                'approved' => 'Approve this pay increase from the Pay Increases page (new rates required).',
            ]);
        }

        $old = WorkOrder::findOrFail($data['work_order_id']);
        $pmIncrease = (int) ($data['pm_requested_increase_cents'] ?? 0);

        if ($pmIncrease > 0 && (int) $approved['bill_rate'] < $old->bill_rate + $pmIncrease) {
            throw ValidationException::withMessages([
                'bill_rate' => "New bill rate must honor the PM's requested increase.",
            ]);
        }

        $period = PayrollPeriod::findOrFail($approved['effective_period_id']);

        $new = $this->supersede->handle($old, [
            'pay_rate' => (int) $approved['pay_rate'],
            'bill_rate' => (int) $approved['bill_rate'],
            'ot_pay_rate' => (int) $approved['ot_pay_rate'],
            'ot_bill_rate' => (int) $approved['ot_bill_rate'],
        ], WorkOrderSource::PayIncrease, $period->week_start, $workflow->initiator);

        $effective = $period->week_start->toDateString();
        $this->logOnContractor($workflow, sprintf(
            'Pay increase %s: pay %s → %s, bill %s → %s, from %s',
            ($data['source'] ?? null) === 'recruiter' ? 'applied' : 'approved',
            $this->money($old->pay_rate), $this->money((int) $approved['pay_rate']),
            $this->money($old->bill_rate), $this->money((int) $approved['bill_rate']),
            $period->week_start->format('M j, Y'),
        ));

        $old->person?->notify(new WorkflowNotice(
            $workflow,
            'Your pay rate increases to '.$this->money((int) $approved['pay_rate'])."/hr effective {$effective}.",
            ['work_order_id' => $new->id],
        ));

        if (($data['source'] ?? null) === 'pm') {
            $workflow->initiator?->notify(new WorkflowNotice(
                $workflow,
                'Pay increase approved — new bill rate '.$this->money((int) $approved['bill_rate'])."/hr effective {$effective}.",
                ['work_order_id' => $new->id],
            ));
        }
    }

    public function taskSummary(Workflow $workflow): ?string
    {
        $wo = WorkOrder::with(['person:id,name', 'position:id,name', 'property:id,name'])->find($workflow->data['work_order_id'] ?? 0);
        if ($wo === null) {
            return null;
        }

        return sprintf(
            '%s · %s @ %s · +%s/hr',
            $wo->person?->name, $wo->position?->name, $wo->property?->name,
            $this->money((int) ($workflow->data['pm_requested_increase_cents'] ?? 0)),
        );
    }

    /** Approving needs the new rates and start week: the Pay Increases form. */
    public function reviewUrl(Workflow $workflow, WorkflowStep $step): ?string
    {
        return $step->step_key === 'approve_pay_increase' ? "/admin/pay-increases?review={$workflow->id}" : null;
    }

    /** Each step lands on the contractor's History tab (people log). */
    private function logOnContractor(Workflow $workflow, string $description): void
    {
        $contractor = WorkOrder::find($workflow->data['work_order_id'] ?? 0)?->person;
        if ($contractor === null) {
            return;
        }

        activity('people')
            ->performedOn($contractor)
            ->event('pay_increase')
            ->withProperties(['workflow_id' => $workflow->id])
            ->log($description);
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, 2);
    }
}
