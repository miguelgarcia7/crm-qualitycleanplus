<?php

namespace App\Http\Controllers\Minute;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Actions\StartWorkflow;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ->get()
            ->map(fn (Workflow $wf): array => [
                'id' => $wf->id,
                'status' => $wf->status->value,
                'increase' => (int) ($wf->data['pm_requested_increase_cents'] ?? 0),
                'reason' => $wf->data['reason'] ?? null,
                'created_at' => $wf->created_at?->toDateString(),
            ]);

        return Inertia::render('minute/pay-increases/index', [
            'requests' => $requests,
            'workOrders' => $this->assignableWorkOrders($pm)->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'label' => "{$wo->person?->name} — {$wo->position?->name} @ {$wo->property?->name}",
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
