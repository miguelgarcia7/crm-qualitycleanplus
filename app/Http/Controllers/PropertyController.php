<?php

namespace App\Http\Controllers;

use App\Domain\Dashboards\Services\PropertyListSummary;
use App\Domain\Dashboards\Services\PropertyOverview;
use App\Domain\Devices\Models\Device;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\FieldVisits\Support\PropertyVisits;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Actions\CreateProperty;
use App\Domain\PropertyBible\Actions\UpdateProperty;
use App\Domain\PropertyBible\Concerns\LogsPropertyActivity;
use App\Domain\PropertyBible\Enums\ContractType;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Department;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\PropertyBible\Support\HolidayDateResolver;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class PropertyController extends Controller
{
    use LogsPropertyActivity;

    public function index(PropertyListSummary $summary): Response
    {
        $this->authorize('viewAny', Property::class);

        $user = Auth::user();

        $query = Property::query()->orderBy('name');

        // Recruiters / PMs see only the properties they're assigned to.
        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->assignedTo($user);
        }

        $rows = $query->get();
        $columns = $user instanceof Person ? $summary->forMany($rows, $user) : [];

        $properties = $rows->map(fn (Property $p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'city' => $p->city,
            'state' => $p->state,
            'pm_name' => $p->pm_name,
            'status' => $p->status->value,
            'time_source' => $p->time_source->value,
            ...($columns[$p->id] ?? []),
        ]);

        return Inertia::render('admin/properties/index', [
            'properties' => $properties,
            'can' => [
                'create' => $user instanceof Person && $user->can('bible.properties.edit'),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Property::class);

        return Inertia::render('admin/properties/form', [
            'statuses' => $this->statusOptions(),
            'timeSources' => $this->timeSourceOptions(),
            'defaultDirectHireThresholdHours' => intdiv(Property::defaultDirectHireThresholdMinutes(), 60),
        ]);
    }

    public function store(StorePropertyRequest $request, CreateProperty $action): RedirectResponse
    {
        $property = $action->handle($request->validated(), $request->user());

        return to_route('properties.show', $property)->with('success', 'Property created.');
    }

    public function show(Request $request, Property $property, PropertyOverview $overview, DirectHireProgress $directHire, PropertyVisits $visits): Response
    {
        $this->authorize('view', $property);

        $user = Auth::user();
        $canContracts = $user instanceof Person && $user->can('bible.contracts.view');
        // Rates and job codes are money: HR, for one, sees the property but not these.
        $canRates = $user instanceof Person && $user->can('viewRates', $property);

        $property->load([
            'departments.department',
            'positionRates.position',
            'assignments.person:id,name',
            'holidays',
            'positionCodes',
        ]);

        if ($canContracts) {
            $property->load(['contracts' => fn ($q) => $q->orderByDesc('effective_date'), 'contracts.uploadedBy:id,name']);
        }

        return Inertia::render('admin/properties/show', [
            'property' => $this->propertyPayload($property),
            'overview' => $user instanceof Person ? $overview->build($property, $user) : null,
            'workOrders' => $user instanceof Person && $user->can('work_orders.view') ? $this->workOrdersPayload($property, $directHire) : null,
            'visits' => $user instanceof Person ? $visits->build($property, $user, $request) : null,
            // A visit opened from the Visits tab (?panel=<id>), only if it's this hotel's.
            'visitPanel' => fn (): ?array => $user instanceof Person && FieldVisit::query()->whereKey($request->integer('panel'))->where('property_id', $property->id)->exists()
                ? FieldVisitController::panelFor($request->integer('panel'), $user)
                : null,
            // Pairing codes are for whoever sets the tablets up.
            'devices' => $user instanceof Person && $user->can('devices.manage')
                ? Device::query()->where('property_id', $property->id)->orderBy('name')->get()->map(fn (Device $d): array => [
                    'id' => $d->id,
                    'name' => $d->name,
                    'is_activated' => $d->is_activated,
                    'activation_code' => $d->is_activated ? null : $d->activation_code,
                    'last_seen_at' => $d->last_seen_at?->diffForHumans(),
                ])->all()
                : null,
            'timeSources' => $this->timeSourceOptions(),
            'defaultDirectHireThresholdHours' => intdiv(Property::defaultDirectHireThresholdMinutes(), 60),
            'departments' => $property->departments->map(fn ($d): array => [
                'id' => $d->id,
                'department_id' => $d->department_id,
                'name' => $d->department?->name,
                'manager_name' => $d->manager_name,
                'manager_phone' => $d->manager_phone,
                'is_active' => $d->is_active,
            ]),
            'rates' => $canRates ? $this->ratesPayload($property) : [],
            'assignments' => $property->assignments->map(fn ($a): array => [
                'id' => $a->id,
                'person_id' => $a->person_id,
                'person' => $a->person?->name,
                'role' => $a->role->value,
                'role_label' => $a->role->label(),
            ]),
            'contracts' => $canContracts ? $property->contracts->map(fn ($c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'effective_date' => $c->effective_date?->toDateString(),
                'expiration_date' => $c->expiration_date?->toDateString(),
                'uploaded_by' => $c->uploadedBy?->name,
                'is_active' => $c->is_active,
                'notes' => $c->notes,
            ]) : [],
            'history' => $this->historyPayload($property, $request),
            'holidays' => $this->holidaysPayload($property),
            // Job codes: one row per position that has (or had) a rate here.
            'jobCodes' => $canRates ? $property->positionRates
                ->pluck('position')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn ($position): array => [
                    'position_id' => $position->id,
                    'position' => $position->name,
                    'job_code' => $property->positionCodes->firstWhere('position_id', $position->id)?->job_code,
                ]) : [],
            'catalogs' => [
                'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'positions' => Position::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'assignablePeople' => Person::query()->role(['recruiter', 'property_manager'])->orderBy('name')->get(['id', 'name']),
                'contractTypes' => collect(ContractType::cases())->map(fn ($t): array => ['value' => $t->value, 'label' => $t->label()]),
                'assignmentRoles' => collect(PropertyAssignmentRole::cases())->map(fn ($r): array => ['value' => $r->value, 'label' => $r->label()]),
            ],
            'can' => [
                'editProfile' => $user instanceof Person && $user->can('update', $property),
                'editDepartments' => $user instanceof Person && $user->can('editDepartments', $property),
                'viewRates' => $canRates,
                'editRates' => $user instanceof Person && $user->can('editRates', $property),
                'viewContracts' => $canContracts,
                'editContracts' => $user instanceof Person && $user->can('bible.contracts.edit'),
                'downloadContracts' => $user instanceof Person && $user->can('bible.contracts.download'),
                'manageAssignments' => $user instanceof Person && $user->can('manageAssignments', $property),
                'inviteUsers' => $user instanceof Person && $user->can('admin.users.create'),
                'editHolidays' => $user instanceof Person && $user->can('update', $property),
                'viewGrid' => $user instanceof Person && $user->can('time_entries.view'),
                'viewInvoices' => $user instanceof Person && $user->can('invoices.view'),
                'createWorkOrders' => $user instanceof Person && $user->can('work_orders.create'),
                'editWorkOrders' => $user instanceof Person && $user->can('work_orders.edit'),
            ],
        ]);
    }

    /**
     * Every rate row, oldest last, each marked by where it stands today at the
     * property: running now, starting later, or ended.
     *
     * @return list<array<string, mixed>>
     */
    private function ratesPayload(Property $property): array
    {
        $today = CarbonImmutable::now($property->timezone)->toDateString();

        return $property->positionRates
            ->sortBy([fn ($a, $b) => strcmp((string) $a->position?->name, (string) $b->position?->name), fn ($a, $b) => $b->effective_date <=> $a->effective_date])
            ->map(fn ($r): array => [
                'id' => $r->id,
                'position_id' => $r->position_id,
                'position' => $r->position?->name,
                'pay_rate' => $r->pay_rate,
                'bill_rate' => $r->bill_rate,
                'ot_pay_rate' => $r->ot_pay_rate,
                'ot_bill_rate' => $r->ot_bill_rate,
                'effective_date' => $r->effective_date->toDateString(),
                'end_date' => $r->end_date?->toDateString(),
                'is_active' => $r->is_active,
                'notes' => $r->notes,
                'state' => match (true) {
                    ! $r->is_active || ($r->end_date !== null && $r->end_date->toDateString() < $today) => 'past',
                    $r->effective_date->toDateString() > $today => 'scheduled',
                    default => 'current',
                },
            ])
            ->values()
            ->all();
    }

    /**
     * The placements running here — active and suspended work orders, by
     * contractor — with progress toward the direct-hire threshold. Closed ones
     * stay on the Work Orders list, so this tab stays as long as the roster.
     *
     * @return array{rows: list<array<string, mixed>>, closed_count: int}
     */
    private function workOrdersPayload(Property $property, DirectHireProgress $directHire): array
    {
        $workOrders = $property->workOrders()
            ->whereIn('status', [WorkOrderStatus::Active->value, WorkOrderStatus::Suspended->value])
            ->with(['person:id,name', 'position:id,name'])
            ->get()
            ->sortBy(fn (WorkOrder $wo): array => [mb_strtolower($wo->person->name ?? ''), $wo->start_date->toDateString()])
            ->values();

        $progress = $directHire->forMany($workOrders);

        return [
            'rows' => $workOrders->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'person_id' => $wo->person_id,
                'contractor' => $wo->person?->name,
                'position' => $wo->position?->name,
                'pay_rate' => $wo->pay_rate,
                'bill_rate' => $wo->bill_rate,
                'start_date' => $wo->start_date->toDateString(),
                'status' => $wo->status->value,
                'status_label' => $wo->status->label(),
                'is_temporary_assignment' => $wo->is_temporary_assignment,
                'direct_hire' => $progress[$wo->id] ?? null,
            ])->all(),
            'closed_count' => $property->workOrders()->where('status', WorkOrderStatus::Closed->value)->count(),
        ];
    }

    /**
     * Every holiday in the calendar, flagged with whether this property
     * observes it and its date this year (in the property's timezone).
     *
     * @return Collection<int, array{id: int, name: string, type_label: string, this_year: non-falsy-string, enabled: bool}>
     */
    private function holidaysPayload(Property $property): Collection
    {
        $enabled = $property->holidays->pluck('id')->all();
        $year = CompanyTime::now()->year;

        return Holiday::query()
            ->orderByRaw("case when type = 'legal' then 0 else 1 end")
            ->orderBy('name')
            ->get()
            ->map(fn (Holiday $h): array => [
                'id' => $h->id,
                'name' => $h->name,
                'type_label' => $h->type->label(),
                'this_year' => HolidayDateResolver::resolve($h, $year, $property->timezone)->format('M j'),
                'enabled' => in_array($h->id, $enabled, true),
            ]);
    }

    public function qr(Property $property): Response
    {
        $this->authorize('view', $property);

        return Inertia::render('admin/properties/qr', [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'has_location' => $property->latitude !== null && $property->longitude !== null,
                'qr_clock_enabled' => $property->qr_clock_enabled,
            ],
            // Token-based URL — unguessable, minted when QR clock-in is enabled.
            'url' => $property->qr_token === null ? null : 'https://'.config('domains.qcminute').'/clock-in/'.$property->qr_token,
        ]);
    }

    /** Editing moved onto the property page's Settings tab; old links land there. */
    public function edit(Property $property): RedirectResponse
    {
        $this->authorize('update', $property);

        return redirect()->to(route('properties.show', $property).'#settings/details');
    }

    public function update(UpdatePropertyRequest $request, Property $property, UpdateProperty $action): RedirectResponse
    {
        $action->handle($property, $request->validated());

        return back()->with('success', 'Saved.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $this->authorize('delete', $property);

        $this->logProperty($property, 'deleted', "Deleted property \"{$property->name}\"");
        $property->delete();

        return to_route('properties.index')->with('success', 'Property deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function propertyPayload(Property $property): array
    {
        return [
            'id' => $property->id,
            'name' => $property->name,
            'pm_name' => $property->pm_name,
            'pm_phone' => $property->pm_phone,
            'main_phone' => $property->main_phone,
            'billing_email' => $property->billing_email,
            'address' => $property->address,
            'city' => $property->city,
            'state' => $property->state,
            'zip' => $property->zip,
            'timezone' => $property->timezone,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'geofence_radius_meters' => $property->geofence_radius_meters,
            'qr_clock_enabled' => $property->qr_clock_enabled,
            'has_qr_token' => $property->qr_token !== null,
            'closing_day' => $property->closing_day,
            'tax_rate' => $property->tax_rate,
            // Stored in minutes, entered in hours (how the contract states it).
            'direct_hire_threshold_hours' => $property->direct_hire_threshold_minutes !== null
                ? intdiv($property->direct_hire_threshold_minutes, 60)
                : null,
            'status' => $property->status->value,
            'time_source' => $property->time_source->value,
        ];
    }

    /** Changes per page in the History section. */
    private const HISTORY_PER_PAGE = 25;

    /**
     * The property's change log, newest first, a page at a time. The page
     * travels as `history_page` so it can't clash with another list's `page`.
     *
     * @return array{rows: list<array<string, mixed>>, current_page: int, last_page: int, total: int, from: int|null, to: int|null}
     */
    private function historyPayload(Property $property, Request $request): array
    {
        $page = Activity::query()
            ->where('subject_type', $property->getMorphClass())
            ->where('subject_id', $property->getKey())
            ->with('causer')
            ->latest()
            // Changes made together share a timestamp; without a tiebreaker a
            // row can show on two pages and another on none.
            ->orderByDesc('id')
            ->paginate(self::HISTORY_PER_PAGE, pageName: 'history_page', page: max(1, $request->integer('history_page', 1)));

        return [
            'rows' => collect($page->items())->map(fn (Activity $a): array => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'causer' => $a->causer instanceof Person ? $a->causer->name : null,
                'created_at' => CompanyTime::of($a->created_at)?->toDayDateTimeString(),
            ])->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function statusOptions(): array
    {
        return collect(PropertyStatus::cases())
            ->map(fn (PropertyStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
            ->all();
    }

    /**
     * @return list<array<string, string>>
     */
    private function timeSourceOptions(): array
    {
        return collect(PropertyTimeSource::cases())
            ->map(fn (PropertyTimeSource $s): array => ['value' => $s->value, 'label' => $s->label()])
            ->all();
    }
}
