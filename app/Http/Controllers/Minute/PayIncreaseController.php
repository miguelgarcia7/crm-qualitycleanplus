<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Workflows\Actions\CancelWorkflow;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PM-facing pay increase requests on QC Minute (ADR-0020). The PM frames the ask
 * as a per-hour increase (never sees the contractor's pay rate); a recruiter
 * approves it in the back office.
 */
class PayIncreaseController extends Controller
{
    public function index(Request $request): Response
    {
        $pm = $request->user();

        $requests = Workflow::query()
            ->where('type', WorkflowType::PayIncrease->value)
            ->where('initiator_id', $pm->id)
            ->latest('id')
            ->get();
        $workOrders = WorkOrder::query()
            ->whereIn('id', $requests->map(fn (Workflow $wf) => $wf->data['work_order_id'] ?? null)->filter())
            ->with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->get()
            ->keyBy('id');

        return Inertia::render('minute/pay-increases/index', [
            'requests' => $requests->map(fn (Workflow $wf): array => $this->requestPayload($wf, $workOrders->get($wf->data['work_order_id'] ?? 0)))->values(),
            // The bill rate is the hotel's own number, shown so the PM can see
            // what an increase does to their rate. Never add pay_rate here:
            // PMs must not see what QCP pays the contractor (ADR-0020).
            'workOrders' => $this->assignableWorkOrders($pm)->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'label' => "{$wo->person?->name} — {$wo->position?->name} @ {$wo->property?->name}",
                'bill_rate' => $wo->bill_rate,
            ])->values(),
            'can' => ['initiate' => $pm->can('workflows.pay_increase.initiate')],
        ]);
    }

    public function store(Request $request, StartWorkflow $start): RedirectResponse
    {
        $pm = $request->user();
        abort_unless($pm instanceof Person && $pm->can('workflows.pay_increase.initiate'), 403);

        $validated = $request->validate([
            'work_order_id' => ['required', 'integer', 'exists:work_orders,id'],
            'increase_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $workOrder = $this->assignableWorkOrders($pm)->firstWhere('id', (int) $validated['work_order_id']);
        abort_if($workOrder === null, 403);

        $start->handle(WorkflowType::PayIncrease, $workOrder->person, $pm, [
            'work_order_id' => $workOrder->id,
            'source' => 'pm',
            'pm_requested_increase_cents' => (int) round(((float) $validated['increase_amount']) * 100),
            'reason' => $validated['reason'],
        ]);

        return back()->with('success', 'Pay increase request submitted.');
    }

    /** While a recruiter hasn't acted on it, the PM can change the amount or reason. */
    public function update(Request $request, Workflow $workflow): RedirectResponse
    {
        $pm = $request->user();
        $this->ensureOwnOpenRequest($pm, $workflow);

        $validated = $request->validate([
            'increase_amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $workflow->update(['data' => array_merge($workflow->data ?? [], [
            'pm_requested_increase_cents' => (int) round(((float) $validated['increase_amount']) * 100),
            'reason' => $validated['reason'],
        ])]);

        return back()->with('success', 'Pay increase request updated.');
    }

    public function cancel(Request $request, Workflow $workflow, CancelWorkflow $cancel): RedirectResponse
    {
        $pm = $request->user();
        abort_unless($pm instanceof Person && $pm->can('workflows.pay_increase.cancel_own'), 403);
        $this->ensureOwnOpenRequest($pm, $workflow);

        $cancel->handle($workflow, $pm, 'Withdrawn by the property manager.');

        return back()->with('success', 'Pay increase request cancelled.');
    }

    /**
     * What the PM sees of a request: who it's for, the hotel's bill rate now and
     * asked for, and how it ended. Never the contractor's pay rate (ADR-0020).
     *
     * @return array<string, mixed>
     */
    private function requestPayload(Workflow $workflow, ?WorkOrder $workOrder): array
    {
        $data = $workflow->data ?? [];
        $increase = (int) ($data['pm_requested_increase_cents'] ?? 0);
        $approved = is_array($data['approved'] ?? null) ? $data['approved'] : null;
        $effective = $approved !== null ? PayrollPeriod::find($approved['effective_period_id'] ?? 0)?->week_start : null;

        return [
            'id' => $workflow->id,
            'status' => $workflow->status->value,
            'contractor' => $workOrder?->person?->name,
            'position' => $workOrder?->position?->name,
            'property' => $workOrder?->property?->name,
            'increase' => $increase,
            // The rate the request was made against: the work order it raises
            // (closed once approved, so its rate is still the "before").
            'current_bill_rate' => $workOrder?->bill_rate,
            'approved_bill_rate' => $approved['bill_rate'] ?? null,
            'effective' => $effective?->format('M j, Y'),
            'decision_note' => $workflow->status === WorkflowStatus::Rejected ? $workflow->cancel_reason : null,
            'reason' => $data['reason'] ?? null,
            'created_at' => $workflow->created_at?->format('M j, Y'),
            'can_change' => $workflow->status === WorkflowStatus::InProgress,
        ];
    }

    private function ensureOwnOpenRequest(mixed $pm, Workflow $workflow): void
    {
        abort_unless($pm instanceof Person && (int) $workflow->initiator_id === $pm->id, 403);
        abort_unless($workflow->workflowType() === WorkflowType::PayIncrease, 404);

        if ($workflow->status !== WorkflowStatus::InProgress) {
            throw ValidationException::withMessages(['request' => 'A recruiter has already acted on this request.']);
        }
    }

    /**
     * Active work orders at the PM's assigned properties.
     *
     * @return Collection<int, WorkOrder>
     */
    private function assignableWorkOrders(Person $pm): Collection
    {
        $propertyIds = $pm->assignedProperties()->pluck('properties.id')->all();

        return WorkOrder::query()
            ->where('status', WorkOrderStatus::Active->value)
            ->whereIn('property_id', $propertyIds)
            ->with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->get();
    }
}
