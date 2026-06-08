<?php

namespace App\Http\Controllers;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\WorkOrders\Actions\CloseWorkOrder;
use App\Domain\WorkOrders\Actions\CreateWorkOrder;
use App\Domain\WorkOrders\Actions\UpdateWorkOrder;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', WorkOrder::class);

        $user = Auth::user();

        $query = WorkOrder::query()->with(['person:id,name', 'property:id,name', 'position:id,name'])->latest();

        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->whereIn('property_id', $user->assignedProperties()->pluck('properties.id')->all());
        }

        return Inertia::render('admin/work-orders/index', [
            'workOrders' => $query->get()->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'person_id' => $wo->person_id,
                'contractor' => $wo->person?->name,
                'property_id' => $wo->property_id,
                'property' => $wo->property?->name,
                'position_id' => $wo->position_id,
                'position' => $wo->position?->name,
                'pay_rate' => $wo->pay_rate,
                'bill_rate' => $wo->bill_rate,
                'ot_pay_rate' => $wo->ot_pay_rate,
                'ot_bill_rate' => $wo->ot_bill_rate,
                'status' => $wo->status->value,
                'is_temporary_assignment' => $wo->is_temporary_assignment,
                'start_date' => $wo->start_date->toDateString(),
            ]),
            'catalogs' => $this->catalogs() + [
                'recruiters' => Person::role('recruiter')->orderBy('name')->get(['id', 'name']),
            ],
            'can' => [
                'create' => $user instanceof Person && $user->can('work_orders.create'),
                'transfer' => $user instanceof Person && $user->can('workflows.transfer.initiate'),
                'temp' => $user instanceof Person && $user->can('workflows.temporary_assignment.initiate'),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('admin/work-orders/form', [
            'workOrder' => null,
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function store(StoreWorkOrderRequest $request, CreateWorkOrder $action): RedirectResponse
    {
        $action->handle($this->toCentsData($request->validated()), $request->user());

        return to_route('work-orders.index')->with('success', 'Work order created.');
    }

    public function edit(WorkOrder $workOrder): Response
    {
        $this->authorize('update', $workOrder);

        return Inertia::render('admin/work-orders/form', [
            'workOrder' => [
                'id' => $workOrder->id,
                'person_id' => $workOrder->person_id,
                'property_id' => $workOrder->property_id,
                'position_id' => $workOrder->position_id,
                'pay_rate' => $workOrder->pay_rate,
                'bill_rate' => $workOrder->bill_rate,
                'ot_pay_rate' => $workOrder->ot_pay_rate,
                'ot_bill_rate' => $workOrder->ot_bill_rate,
                'start_date' => $workOrder->start_date->toDateString(),
                'end_date' => $workOrder->end_date?->toDateString(),
                'status' => $workOrder->status->value,
                'notes' => $workOrder->notes,
            ],
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder, UpdateWorkOrder $action): RedirectResponse
    {
        $action->handle($workOrder, $this->toCentsData($request->validated()));

        return to_route('work-orders.index')->with('success', 'Work order updated.');
    }

    public function close(WorkOrder $workOrder, CloseWorkOrder $action): RedirectResponse
    {
        $this->authorize('close', $workOrder);

        $action->handle($workOrder);

        return to_route('work-orders.index')->with('success', 'Work order closed.');
    }

    /** Bible rate auto-fill: current (property, position) rate in cents, or null. */
    public function rateLookup(Request $request): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);

        $property = Property::find($request->integer('property_id'));
        $rate = $property?->currentRateFor($request->integer('position_id'));

        return response()->json($rate === null ? null : [
            'pay_rate' => $rate->pay_rate,
            'bill_rate' => $rate->bill_rate,
            'ot_pay_rate' => $rate->ot_pay_rate,
            'ot_bill_rate' => $rate->ot_bill_rate,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogs(): array
    {
        return [
            'contractors' => Person::query()
                ->whereIn('status', [PersonStatus::ContractorActive, PersonStatus::ContractorInactive])
                ->orderBy('name')->get(['id', 'name']),
            'properties' => Property::query()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Convert dollar rate inputs to integer cents.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function toCentsData(array $data): array
    {
        foreach (['pay_rate', 'bill_rate', 'ot_pay_rate', 'ot_bill_rate'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = (int) round(((float) $data[$key]) * 100);
            }
        }

        return $data;
    }
}
