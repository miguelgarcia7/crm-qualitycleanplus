<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Actions\CompleteStep;
use App\Domain\Workflows\Actions\RejectStep;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiter-facing pay increases (ADR-0020): a queue of PM-initiated requests to
 * approve/decline (with editable rates honoring the PM's bill increase) plus a
 * recruiter-initiated create that applies immediately.
 */
class PayIncreaseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $pending = Workflow::query()
            ->where('type', WorkflowType::PayIncrease->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->with(['initiator:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Workflow $wf): ?array => $this->pendingPayload($wf))
            ->filter()
            ->values();

        // Active WOs the recruiter can raise directly (own-property scoped).
        $woQuery = WorkOrder::query()->where('status', WorkOrderStatus::Active->value)
            ->with(['person:id,name', 'property:id,name', 'position:id,name']);
        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $woQuery->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        return Inertia::render('admin/pay-increases/index', [
            'pending' => $pending,
            'workOrders' => $woQuery->get()->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'contractor' => $wo->person?->name,
                'property' => $wo->property?->name,
                'position' => $wo->position?->name,
                'pay_rate' => $wo->pay_rate,
                'bill_rate' => $wo->bill_rate,
                'ot_pay_rate' => $wo->ot_pay_rate,
                'ot_bill_rate' => $wo->ot_bill_rate,
                'periods' => $this->periodOptions($wo->property_id),
            ]),
            'can' => [
                'approve' => $user instanceof Person && $user->can('workflows.pay_increase.approve'),
                'initiate' => $user instanceof Person && $user->can('workflows.pay_increase.initiate'),
            ],
        ]);
    }

    /** Recruiter-initiated increase — applies immediately (no approval). */
    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        $validated = $this->validateRates($request);
        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);
        $this->authorizeProperty($workOrder, 'workflows.pay_increase.initiate');

        $start->handle(WorkflowType::PayIncrease, $workOrder->person, $request->user(), [
            'work_order_id' => $workOrder->id,
            'source' => 'recruiter',
            'reason' => $validated['reason'],
            'approved' => $this->approvedBlock($validated),
        ]);

        return back()->with('success', 'Pay increase applied.');
    }

    public function approve(Request $request, Workflow $workflow, CompleteStep $action): RedirectResponse
    {
        $step = $this->approvalStep($workflow);
        $workOrder = WorkOrder::findOrFail($workflow->data['work_order_id'] ?? 0);
        $this->authorizeProperty($workOrder, 'workflows.pay_increase.approve');

        $validated = $this->validateRates($request, requireWorkOrder: false);
        $workflow->update(['data' => array_merge($workflow->data ?? [], ['approved' => $this->approvedBlock($validated)])]);

        $action->handle($step->fresh(), $request->user());

        return back()->with('success', 'Pay increase approved.');
    }

    public function decline(Request $request, Workflow $workflow, RejectStep $action): RedirectResponse
    {
        $step = $this->approvalStep($workflow);
        $workOrder = WorkOrder::findOrFail($workflow->data['work_order_id'] ?? 0);
        $this->authorizeProperty($workOrder, 'workflows.pay_increase.approve');

        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $action->handle($step, $request->user(), $validated['reason']);

        return back()->with('success', 'Pay increase declined.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pendingPayload(Workflow $workflow): ?array
    {
        $workOrder = WorkOrder::with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->find($workflow->data['work_order_id'] ?? 0);
        $step = $workflow->steps()->where('step_key', 'approve_pay_increase')->where('status', 'pending')->first();

        if ($workOrder === null || $step === null) {
            return null;
        }

        $increase = (int) ($workflow->data['pm_requested_increase_cents'] ?? 0);

        return [
            'workflow_id' => $workflow->id,
            'contractor' => $workOrder->person?->name,
            'property' => $workOrder->property?->name,
            'position' => $workOrder->position?->name,
            'initiator' => $workflow->initiator?->name,
            'reason' => $workflow->data['reason'] ?? null,
            'pm_requested_increase' => $increase,
            'current' => [
                'pay_rate' => $workOrder->pay_rate,
                'bill_rate' => $workOrder->bill_rate,
                'ot_pay_rate' => $workOrder->ot_pay_rate,
                'ot_bill_rate' => $workOrder->ot_bill_rate,
            ],
            'suggested' => [
                'pay_rate' => $workOrder->pay_rate + $increase,
                'bill_rate' => $workOrder->bill_rate + $increase,
                'ot_pay_rate' => (int) round(($workOrder->pay_rate + $increase) * 1.5),
                'ot_bill_rate' => (int) round(($workOrder->bill_rate + $increase) * 1.5),
            ],
            'periods' => $this->periodOptions($workOrder->property_id),
        ];
    }

    /** Next two open payroll periods for a property (effective options). */
    private function periodOptions(int $propertyId): array
    {
        return PayrollPeriod::query()
            ->where('property_id', $propertyId)
            ->where('status', 'open')
            ->orderBy('week_start')
            ->limit(2)
            ->get()
            ->map(fn (PayrollPeriod $p): array => ['id' => $p->id, 'label' => $p->week_start->toDateString()])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRates(Request $request, bool $requireWorkOrder = true): array
    {
        return $request->validate([
            'work_order_id' => [$requireWorkOrder ? 'required' : 'nullable', 'integer', 'exists:work_orders,id'],
            'pay_rate' => ['required', 'numeric', 'min:0'],
            'bill_rate' => ['required', 'numeric', 'min:0'],
            'ot_pay_rate' => ['required', 'numeric', 'min:0'],
            'ot_bill_rate' => ['required', 'numeric', 'min:0'],
            'effective_period_id' => ['required', 'integer', 'exists:payroll_periods,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, int>
     */
    private function approvedBlock(array $v): array
    {
        return [
            'pay_rate' => (int) round(((float) $v['pay_rate']) * 100),
            'bill_rate' => (int) round(((float) $v['bill_rate']) * 100),
            'ot_pay_rate' => (int) round(((float) $v['ot_pay_rate']) * 100),
            'ot_bill_rate' => (int) round(((float) $v['ot_bill_rate']) * 100),
            'effective_period_id' => (int) $v['effective_period_id'],
        ];
    }

    private function approvalStep(Workflow $workflow): WorkflowStep
    {
        $step = $workflow->steps()->where('step_key', 'approve_pay_increase')->where('status', 'pending')->first();

        if ($step === null) {
            throw ValidationException::withMessages(['workflow' => 'This request is not awaiting approval.']);
        }

        return $step;
    }

    private function authorizeProperty(WorkOrder $workOrder, string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof Person && $user->can($permission), 403);

        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless($user->isAssignedTo($workOrder->property), 403);
    }
}
