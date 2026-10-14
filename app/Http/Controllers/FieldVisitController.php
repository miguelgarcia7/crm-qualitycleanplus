<?php

namespace App\Http\Controllers;

use App\Domain\FieldVisits\Actions\CheckInRecruiter;
use App\Domain\FieldVisits\Actions\CheckOutRecruiter;
use App\Domain\FieldVisits\Enums\GpsStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Support\CompanyTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recruiter field-visit check-in/out (Phase 07b, ADR-0017). The check-in panel pulls
 * `context` (open visit + active hotels), posts `store`/`checkOut`; `index`
 * is the visit-history surface (own vs all by permission).
 */
class FieldVisitController extends Controller
{
    /**
     * Open visit (if any) + every active hotel, the user's own marked `mine` —
     * feeds the check-in panel. Recruiters may check in anywhere (covering).
     */
    public function context(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('field_visits.create'), 403);

        $open = FieldVisit::query()->where('person_id', $user->id)->open()->with('property:id,name')->first();

        $global = $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES);
        $mine = $global ? [] : Property::query()->assignedTo($user)->pluck('id')->all();

        $properties = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Property $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'latitude' => $p->latitude !== null ? (float) $p->latitude : null,
                'longitude' => $p->longitude !== null ? (float) $p->longitude : null,
                'radius' => $p->geofence_radius_meters,
                'mine' => $global || in_array($p->id, $mine, true),
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
            'property_id' => ['required', 'integer', Rule::exists('properties', 'id')->where('status', PropertyStatus::Active->value)],
            'selfie' => ['required', 'image', 'max:5120'],
        ]);

        $property = Property::findOrFail($validated['property_id']);

        $action->handle($user, $property, $this->gpsData($validated) + ['selfie' => $request->file('selfie')]);

        return back()->with('success', "Checked in at {$property->name}.");
    }

    public function checkOut(Request $request, CheckOutRecruiter $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->can('field_visits.close_own'), 403);

        $validated = $request->validate($this->gpsRules() + [
            'late' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $visit = FieldVisit::query()->where('person_id', $user->id)->open()->latest('id')->first();
        if ($visit === null) {
            throw ValidationException::withMessages(['visit' => 'You have no open visit to check out of.']);
        }

        $action->handle($visit, $this->gpsData($validated) + ['notes' => $validated['notes'] ?? null], (bool) ($validated['late'] ?? false));

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

        // How many each filter would show, so the switch says where to look.
        $counts = [
            '' => (clone $query)->count(),
            'open' => (clone $query)->open()->count(),
            'off_geofence' => (clone $query)->where('was_inside_geofence', false)->count(),
            'late' => (clone $query)->where('was_late_close', true)->count(),
            'covering' => (clone $query)->where('was_covering', true)->count(),
        ];

        match ($filter) {
            'off_geofence' => $query->where('was_inside_geofence', false),
            'late' => $query->where('was_late_close', true),
            'covering' => $query->where('was_covering', true),
            'open' => $query->open(),
            default => null,
        };

        return Inertia::render('admin/field-visits/index', [
            'visits' => $query->limit(200)->get()->map(fn (FieldVisit $v): array => [
                'id' => $v->id,
                'recruiter' => $v->person?->name,
                'property' => $v->property?->name,
                'status' => $v->status->value,
                'check_in_at' => CompanyTime::of($v->check_in_at)->toDayDateTimeString(),
                'check_out_at' => CompanyTime::of($v->check_out_at)?->toDayDateTimeString(),
                'duration' => $v->durationMinutes(),
                'inside_geofence' => $v->was_inside_geofence,
                'late_close' => $v->was_late_close,
                'covering' => $v->was_covering,
            ]),
            'filter' => $filter,
            'counts' => $counts,
            'can' => ['viewAll' => $viewAll],
        ]);
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
