<?php

namespace App\Http\Controllers;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\WorkOrders\Actions\CloseWorkOrder;
use App\Domain\WorkOrders\Actions\CreateWorkOrder;
use App\Domain\WorkOrders\Actions\RecordMoreStaffPlacement;
use App\Domain\WorkOrders\Actions\UpdateWorkOrder;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use App\Http\Requests\WorkOrder\StoreWorkOrderRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    /**
     * Sortable column => the SQL expression behind it. A whitelist, so a
     * hand-edited query string cannot order by an arbitrary column.
     *
     * `created_at` is the default and has no header of its own: the list has
     * always read newest-first, and no visible column carries that.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'created_at' => 'work_orders.created_at',
        'contractor' => 'people.name',
        'property' => 'properties.name',
        'position' => 'positions.name',
        'pay_rate' => 'work_orders.pay_rate',
        'bill_rate' => 'work_orders.bill_rate',
        'start_date' => 'work_orders.start_date',
        'status' => 'work_orders.status',
    ];

    private const PER_PAGE = [10, 25, 50];

    public function index(Request $request, DirectHireProgress $progress): Response
    {
        $this->authorize('viewAny', WorkOrder::class);

        $user = Auth::user();
        $filters = $this->filters($request);

        $page = $this->listQuery($filters, $user)
            ->paginate($filters['per_page'])
            ->withQueryString();

        $workOrders = collect($page->items());
        // Only the rows on screen, rather than every work order in the system.
        $directHire = $progress->forMany($workOrders);

        return Inertia::render('admin/work-orders/index', [
            'workOrders' => $workOrders->map(fn (WorkOrder $wo): array => [
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
                'direct_hire' => $directHire[$wo->id] ?? null,
            ])->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => $filters,
            // From the enum, not from the rows on this page — a status filter
            // that only offers what page 1 happens to contain is useless.
            'statuses' => array_map(
                fn (WorkOrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                WorkOrderStatus::cases(),
            ),
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

    /**
     * The query string, normalised — every value checked against a whitelist.
     *
     * @return array{search: string, status: string, property_id: int|null, sort: string, direction: string, per_page: int}
     */
    private function filters(Request $request): array
    {
        $status = (string) $request->string('status');
        $sort = (string) $request->string('sort', 'created_at');
        $perPage = $request->integer('per_page', 25);

        return [
            'search' => trim((string) $request->string('search')),
            'status' => WorkOrderStatus::tryFrom($status) !== null ? $status : '',
            'property_id' => $request->integer('property_id') ?: null,
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'created_at',
            'direction' => $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * Work orders matching the filters, ordered and scoped to what the viewer
     * may see.
     *
     * The joins exist so contractor, property and position sort in SQL; all
     * three foreign keys are non-nullable, so an inner join drops nothing.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<WorkOrder>
     */
    private function listQuery(array $filters, mixed $user): Builder
    {
        return WorkOrder::query()
            ->join('people', 'people.id', '=', 'work_orders.person_id')
            ->join('properties', 'properties.id', '=', 'work_orders.property_id')
            ->join('positions', 'positions.id', '=', 'work_orders.position_id')
            ->when(
                $user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
                fn (Builder $q) => $q->whereIn('work_orders.property_id', $user->assignedProperties()->pluck('properties.id')->all()),
            )
            ->when($filters['status'] !== '', fn (Builder $q) => $q->where('work_orders.status', $filters['status']))
            ->when($filters['property_id'] !== null, fn (Builder $q) => $q->where('work_orders.property_id', $filters['property_id']))
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $q->where(fn (Builder $w) => $w
                    ->where('people.name', 'like', $like)
                    ->orWhere('properties.name', 'like', $like)
                    ->orWhere('positions.name', 'like', $like));
            })
            ->select('work_orders.*')
            ->with(['person:id,name', 'property:id,name', 'position:id,name'])
            ->orderBy(self::SORTS[$filters['sort']], $filters['direction'])
            // Start dates and statuses tie constantly — without a stable
            // tiebreaker a row can appear on two pages and another on none.
            ->orderBy('work_orders.id', 'desc');
    }

    public function create(): Response
    {
        $this->authorize('create', WorkOrder::class);

        return Inertia::render('admin/work-orders/form', [
            'workOrder' => null,
            'catalogs' => $this->catalogs(),
        ]);
    }

    public function store(StoreWorkOrderRequest $request, CreateWorkOrder $action, RecordMoreStaffPlacement $placement): RedirectResponse
    {
        $workOrder = $action->handle($this->toCentsData($request->validated()), $request->user());

        $placement->handle($workOrder, $request->user());

        return to_route('work-orders.index')->with('success', 'Work order created.');
    }

    public function edit(WorkOrder $workOrder, DirectHireProgress $progress): Response
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
                'direct_hire_threshold_hours' => intdiv((int) $workOrder->direct_hire_threshold_minutes, 60),
                'direct_hire' => $progress->for($workOrder),
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

    /** Positions a work order may be created against at a property: those with a current Bible rate. */
    public function positionLookup(Request $request): JsonResponse
    {
        $this->authorize('create', WorkOrder::class);

        $property = Property::find($request->integer('property_id'));

        return response()->json($property?->configuredPositions() ?? []);
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
            'moreStaffRequests' => MoreStaffRequest::query()
                ->whereIn('status', [MoreStaffStatus::Submitted, MoreStaffStatus::InProgress])
                ->with('position:id,name')
                ->orderBy('property_id')
                ->get()
                ->map(fn (MoreStaffRequest $r): array => [
                    'id' => $r->id,
                    'property_id' => $r->property_id,
                    'label' => "#{$r->id} · {$r->position?->name} ({$r->quantity_fulfilled} of {$r->quantity_requested} placed)",
                ])->all(),
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
