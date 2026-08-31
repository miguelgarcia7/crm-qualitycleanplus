<?php

namespace App\Http\Controllers;

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
use App\Http\Requests\Property\StorePropertyRequest;
use App\Http\Requests\Property\UpdatePropertyRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class PropertyController extends Controller
{
    use LogsPropertyActivity;

    public function index(): Response
    {
        $this->authorize('viewAny', Property::class);

        $user = Auth::user();

        $query = Property::query()->orderBy('name');

        // Recruiters / PMs see only the properties they're assigned to.
        if ($user instanceof Person && ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->assignedTo($user);
        }

        $properties = $query->get()->map(fn (Property $p): array => [
            'id' => $p->id,
            'name' => $p->name,
            'city' => $p->city,
            'state' => $p->state,
            'status' => $p->status->value,
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
            'property' => null,
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

    public function show(Property $property): Response
    {
        $this->authorize('view', $property);

        $user = Auth::user();
        $canContracts = $user instanceof Person && $user->can('bible.contracts.view');

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
            'departments' => $property->departments->map(fn ($d): array => [
                'id' => $d->id,
                'department_id' => $d->department_id,
                'name' => $d->department?->name,
                'manager_name' => $d->manager_name,
                'manager_phone' => $d->manager_phone,
                'is_active' => $d->is_active,
            ]),
            'rates' => $property->positionRates->map(fn ($r): array => [
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
            ]),
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
            'history' => $this->historyPayload($property),
            'holidays' => $this->holidaysPayload($property),
            // Job codes: one row per position that has (or had) a rate here.
            'jobCodes' => $property->positionRates
                ->pluck('position')
                ->filter()
                ->unique('id')
                ->sortBy('name')
                ->values()
                ->map(fn ($position): array => [
                    'position_id' => $position->id,
                    'position' => $position->name,
                    'job_code' => $property->positionCodes->firstWhere('position_id', $position->id)?->job_code,
                ]),
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
                'editRates' => $user instanceof Person && $user->can('editRates', $property),
                'viewContracts' => $canContracts,
                'editContracts' => $user instanceof Person && $user->can('bible.contracts.edit'),
                'downloadContracts' => $user instanceof Person && $user->can('bible.contracts.download'),
                'manageAssignments' => $user instanceof Person && $user->can('manageAssignments', $property),
                'inviteUsers' => $user instanceof Person && $user->can('admin.users.create'),
                'editHolidays' => $user instanceof Person && $user->can('update', $property),
            ],
        ]);
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
        $year = (int) now()->format('Y');

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

    public function edit(Property $property): Response
    {
        $this->authorize('update', $property);

        return Inertia::render('admin/properties/form', [
            'property' => $this->propertyPayload($property),
            'statuses' => $this->statusOptions(),
            'timeSources' => $this->timeSourceOptions(),
            'defaultDirectHireThresholdHours' => intdiv(Property::defaultDirectHireThresholdMinutes(), 60),
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property, UpdateProperty $action): RedirectResponse
    {
        $action->handle($property, $request->validated());

        return to_route('properties.show', $property)->with('success', 'Property updated.');
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

    /**
     * @return list<array<string, mixed>>
     */
    private function historyPayload(Property $property): array
    {
        return Activity::query()
            ->where('subject_type', $property->getMorphClass())
            ->where('subject_id', $property->getKey())
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Activity $a): array => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'causer' => $a->causer instanceof Person ? $a->causer->name : null,
                'created_at' => $a->created_at?->toDayDateTimeString(),
            ])
            ->all();
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
