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
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        // Recruiters work their own properties; global roles see everything.
        $propertyIds = $user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)
            ? $user->assignedProperties()->pluck('properties.id')->all()
            : null;

        $pending = Workflow::query()
            ->where('type', WorkflowType::PayIncrease->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->with(['initiator:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (Workflow $wf): ?array => $this->pendingPayload($wf, $propertyIds))
            ->filter()
            ->values();

        // Active WOs the recruiter can raise directly (own-property scoped).
        $woQuery = WorkOrder::query()->where('status', WorkOrderStatus::Active->value)
            ->with(['person:id,name', 'property:id,name,timezone,closing_day', 'position:id,name'])
            ->orderBy('property_id');
        if ($propertyIds !== null) {
            $woQuery->whereIn('property_id', $propertyIds);
        }

        return Inertia::render('admin/pay-increases/index', [
            'pending' => $pending,
            'history' => $this->historyRows($propertyIds),
            'workOrders' => $woQuery->get()
                ->sortBy(fn (WorkOrder $wo) => [$wo->property?->name, $wo->person?->name])
                ->values()
                ->map(fn (WorkOrder $wo): array => [
                    'id' => $wo->id,
                    'contractor' => $wo->person?->name,
                    'property' => $wo->property?->name,
                    'position' => $wo->position?->name,
                    'pay_rate' => $wo->pay_rate,
                    'bill_rate' => $wo->bill_rate,
                    'ot_pay_rate' => $wo->ot_pay_rate,
                    'ot_bill_rate' => $wo->ot_bill_rate,
                    ...$this->periodChoices($wo->property),
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
        $this->ensureRaise($workOrder, $validated);

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
        $this->ensureRaise($workOrder, $validated);
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
     * @param  list<int>|null  $propertyIds  the viewer's properties; null = all
     * @return array<string, mixed>|null
     */
    private function pendingPayload(Workflow $workflow, ?array $propertyIds): ?array
    {
        $workOrder = WorkOrder::with(['person:id,name', 'property:id,name,timezone,closing_day', 'position:id,name'])
            ->find($workflow->data['work_order_id'] ?? 0);
        $step = $workflow->steps()->where('step_key', 'approve_pay_increase')->where('status', 'pending')->first();

        if ($workOrder === null || $step === null) {
            return null;
        }
        if ($propertyIds !== null && ! in_array($workOrder->property_id, $propertyIds, true)) {
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
            ...$this->periodChoices($workOrder->property),
        ];
    }

    /**
     * Decided pay increases, newest first: approved by a recruiter, applied
     * directly, declined, or cancelled by the PM — with who decided and why.
     *
     * @param  list<int>|null  $propertyIds  the viewer's properties; null = all
     * @return list<array<string, mixed>>
     */
    private function historyRows(?array $propertyIds): array
    {
        $workflows = Workflow::query()
            ->where('type', WorkflowType::PayIncrease->value)
            ->whereIn('status', [WorkflowStatus::Completed->value, WorkflowStatus::Rejected->value, WorkflowStatus::Cancelled->value])
            ->with(['initiator:id,name', 'steps'])
            ->latest('completed_at')
            ->latest('id')
            ->limit(200)
            ->get();

        $workOrders = WorkOrder::query()
            ->whereIn('id', $workflows->map(fn (Workflow $wf) => $wf->data['work_order_id'] ?? null)->filter())
            ->with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->get()
            ->keyBy('id');
        $deciderIds = $workflows->flatMap(fn (Workflow $wf) => [$wf->completed_by, ...$wf->steps->pluck('completed_by')])->filter()->unique();
        $names = Person::query()->whereIn('id', $deciderIds)->pluck('name', 'id');
        $periods = PayrollPeriod::query()
            ->whereIn('id', $workflows->map(fn (Workflow $wf) => $wf->data['approved']['effective_period_id'] ?? null)->filter())
            ->pluck('week_start', 'id');

        return $workflows
            ->map(function (Workflow $wf) use ($workOrders, $names, $periods, $propertyIds): ?array {
                $data = $wf->data ?? [];
                $wo = $workOrders->get($data['work_order_id'] ?? 0);
                if ($wo === null || ($propertyIds !== null && ! in_array($wo->property_id, $propertyIds, true))) {
                    return null;
                }

                $direct = ($data['source'] ?? null) === 'recruiter';
                $approved = is_array($data['approved'] ?? null) ? $data['approved'] : null;
                $outcome = match ($wf->status) {
                    WorkflowStatus::Completed => $direct ? 'applied' : 'approved',
                    WorkflowStatus::Rejected => 'declined',
                    default => 'cancelled',
                };
                // A direct raise is decided by whoever applied it; an approval
                // by whoever completed the step; a decline or cancel is on the
                // workflow itself.
                $deciderId = match ($outcome) {
                    'applied' => $wf->initiator_id,
                    'approved' => $wf->steps->firstWhere('step_key', 'approve_pay_increase')?->completed_by,
                    default => $wf->completed_by,
                };
                $effective = $approved !== null ? $periods->get($approved['effective_period_id'] ?? 0) : null;

                return [
                    'id' => $wf->id,
                    'contractor' => $wo->person?->name,
                    'position' => $wo->position?->name,
                    'property' => $wo->property?->name,
                    'requested_by' => $wf->initiator?->name,
                    'requested_at' => $wf->created_at?->format('M j, Y'),
                    'pm_requested_increase' => (int) ($data['pm_requested_increase_cents'] ?? 0),
                    // The work order a request was made against keeps its
                    // rates once superseded, so it is always the "before".
                    'from' => ['pay_rate' => $wo->pay_rate, 'bill_rate' => $wo->bill_rate],
                    'to' => $approved !== null ? ['pay_rate' => (int) $approved['pay_rate'], 'bill_rate' => (int) $approved['bill_rate']] : null,
                    'effective' => $effective !== null ? CarbonImmutable::parse($effective)->format('M j, Y') : null,
                    'outcome' => $outcome,
                    'decided_by' => $deciderId !== null ? $names->get($deciderId) ?? $wf->initiator?->name : null,
                    'decided_at' => $wf->completed_at?->format('M j, Y'),
                    'reason' => $data['reason'] ?? null,
                    'note' => $outcome === 'declined' || $outcome === 'cancelled' ? $wf->cancel_reason : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * The weeks a raise can start: this week and the next two, while open.
     * Defaults to next week — a raise from this week only re-rates punches
     * from now on, splitting the week across two rates.
     *
     * @return array{periods: list<array{id: int, label: string}>, default_period_id: int|null}
     */
    private function periodChoices(?Property $property): array
    {
        $periods = $property === null ? collect() : $this->startablePeriods($property);
        $thisWeek = $property?->weekStartFor(now($property->timezone))->toDateString();

        $options = $periods->map(function (PayrollPeriod $p) use ($thisWeek): array {
            $start = $p->week_start->toDateString();
            $range = $p->week_start->format('D M j').' to '.$p->week_end->format('D M j');
            $name = match (true) {
                $start === $thisWeek => 'This week',
                $start === CarbonImmutable::parse($thisWeek)->addWeek()->toDateString() => 'Next week',
                default => 'Week of '.$p->week_start->format('M j'),
            };

            return ['id' => $p->id, 'label' => "{$name} — {$range}", 'week_start' => $start];
        });

        $default = $options->first(fn (array $o) => $o['week_start'] > $thisWeek) ?? $options->first();

        return [
            'periods' => $options->map(fn (array $o) => ['id' => $o['id'], 'label' => $o['label']])->values()->all(),
            'default_period_id' => $default['id'] ?? null,
        ];
    }

    /** @return Collection<int, PayrollPeriod> */
    private function startablePeriods(Property $property): Collection
    {
        return PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->where('status', 'open')
            ->whereDate('week_start', '>=', $property->weekStartFor(now($property->timezone))->toDateString())
            ->orderBy('week_start')
            ->limit(3)
            ->get();
    }

    /**
     * A pay increase raises at least one rate and lowers neither, and starts in
     * one of the work order's own startable weeks.
     *
     * @param  array<string, mixed>  $v
     */
    private function ensureRaise(WorkOrder $workOrder, array $v): void
    {
        $pay = (int) round(((float) $v['pay_rate']) * 100);
        $bill = (int) round(((float) $v['bill_rate']) * 100);

        if ($pay < $workOrder->pay_rate || $bill < $workOrder->bill_rate) {
            throw ValidationException::withMessages(['pay_rate' => 'A pay increase cannot lower the pay or bill rate.']);
        }
        if ($pay === $workOrder->pay_rate && $bill === $workOrder->bill_rate) {
            throw ValidationException::withMessages(['pay_rate' => 'Enter a higher pay or bill rate.']);
        }
        if (! $this->startablePeriods($workOrder->property)->contains('id', (int) $v['effective_period_id'])) {
            throw ValidationException::withMessages(['effective_period_id' => 'Pick this week or a coming week at this property.']);
        }
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
