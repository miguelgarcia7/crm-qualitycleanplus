<?php

namespace App\Http\Controllers;

use App\Domain\FieldVisits\Actions\CheckInRecruiter;
use App\Domain\FieldVisits\Actions\CheckOutRecruiter;
use App\Domain\FieldVisits\Enums\GpsStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiter field-visit check-in/out (Phase 07b, ADR-0017). The check-in FAB pulls
 * `context` (open visit + assignable properties), posts `store`/`checkOut`; `index`
 * is the visit-history surface (own vs all by permission).
 */
class FieldVisitController extends Controller
{
    /** Open visit (if any) + the user's assignable properties — feeds the check-in FAB. */
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('field_visits.create'), 403);

        $open = FieldVisit::query()->where('person_id', $user->id)->open()->with('property:id,name')->first();

        $properties = $this->assignableProperties($user)
            ->map(fn (Property $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'latitude' => $p->latitude !== null ? (float) $p->latitude : null,
                'longitude' => $p->longitude !== null ? (float) $p->longitude : null,
                'radius' => $p->geofence_radius_meters,
            ])->values();

        return response()->json([
            'open_visit' => $open === null ? null : [
                'id' => $open->id,
                'property' => $open->property?->name,
                'since' => $open->check_in_at->toIso8601String(),
            ],
            'properties' => $properties,
        ]);
    }

    public function store(Request $request, CheckInRecruiter $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('field_visits.create'), 403);

        $validated = $request->validate($this->gpsRules() + [
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);

        $property = Property::findOrFail($validated['property_id']);
        $this->authorizeProperty($user, $property);

        $action->handle($user, $property, $this->gpsData($validated) + ['selfie' => $request->file('selfie')]);

        return back()->with('success', "Checked in at {$property->name}.");
    }

    public function checkOut(Request $request, CheckOutRecruiter $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('field_visits.close_own'), 403);

        $validated = $request->validate($this->gpsRules() + ['late' => ['nullable', 'boolean']]);

        $visit = FieldVisit::query()->where('person_id', $user->id)->open()->latest('id')->first();
        if ($visit === null) {
            throw ValidationException::withMessages(['visit' => 'You have no open visit to check out of.']);
        }

        $action->handle($visit, $this->gpsData($validated), (bool) ($validated['late'] ?? false));

        return back()->with('success', 'Checked out.');
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->canAny(['field_visits.view_own', 'field_visits.view_all']), 403);

        $viewAll = $user->can('field_visits.view_all');
        $filter = (string) $request->query('filter', '');

        $query = FieldVisit::query()->with(['person:id,name', 'property:id,name'])->latest('check_in_at');
        if (! $viewAll) {
            $query->where('person_id', $user->id);
        }
        match ($filter) {
            'off_geofence' => $query->where('was_inside_geofence', false),
            'late' => $query->where('was_late_close', true),
            'open' => $query->open(),
            default => null,
        };

        return Inertia::render('admin/field-visits/index', [
            'visits' => $query->limit(200)->get()->map(fn (FieldVisit $v): array => [
                'id' => $v->id,
                'recruiter' => $v->person?->name,
                'property' => $v->property?->name,
                'status' => $v->status->value,
                'check_in_at' => $v->check_in_at->toDayDateTimeString(),
                'check_out_at' => $v->check_out_at?->toDayDateTimeString(),
                'duration' => $v->durationMinutes(),
                'inside_geofence' => $v->was_inside_geofence,
                'late_close' => $v->was_late_close,
            ]),
            'filter' => $filter,
            'can' => ['viewAll' => $viewAll],
        ]);
    }

    /**
     * @return Collection<int, Property>
     */
    private function assignableProperties(Person $user): Collection
    {
        $query = Property::query()->orderBy('name');
        if (! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            $query->assignedTo($user);
        }

        return $query->get();
    }

    private function authorizeProperty(Person $user, Property $property): void
    {
        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return;
        }

        abort_unless($user->isAssignedTo($property), 403);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function gpsRules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'integer', 'min:0'],
            'gps_status' => ['required', Rule::enum(GpsStatus::class)],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{lat: float|null, lng: float|null, accuracy: int|null, gps_status: string}
     */
    private function gpsData(array $validated): array
    {
        return [
            'lat' => isset($validated['lat']) ? (float) $validated['lat'] : null,
            'lng' => isset($validated['lng']) ? (float) $validated['lng'] : null,
            'accuracy' => isset($validated['accuracy']) ? (int) $validated['accuracy'] : null,
            'gps_status' => $validated['gps_status'],
        ];
    }
}
