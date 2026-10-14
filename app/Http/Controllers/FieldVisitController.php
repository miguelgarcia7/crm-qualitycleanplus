<?php

namespace App\Http\Controllers;

use App\Domain\FieldVisits\Actions\CheckInRecruiter;
use App\Domain\FieldVisits\Actions\CheckOutRecruiter;
use App\Domain\FieldVisits\Actions\CloseVisitForRecruiter;
use App\Domain\FieldVisits\Actions\FixVisitTimes;
use App\Domain\FieldVisits\Enums\GpsStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\FieldVisits\Support\VisitList;
use App\Domain\FieldVisits\Support\VisitPanel;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Reports\Exports\ArrayReportExport;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Recruiter field-visit check-in/out (Phase 07b, ADR-0017). The check-in panel pulls
 * `context` (open visit + active hotels), posts `store`/`checkOut`; `index`
 * and `export` are the visit list (own vs all by permission), built by VisitList.
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

    public function index(Request $request, VisitList $list): Response
    {
        [$user, $everyone] = $this->viewer($request);
        $filters = $list->filters($request, $everyone);

        $page = $list->query($filters, $user, $everyone)
            ->with(['person:id,name', 'property:id,name,timezone'])
            ->latest('check_in_at')
            // Check-ins can tie; without a tiebreaker a row can show on two pages.
            ->orderByDesc('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('admin/field-visits/index', [
            'visits' => collect($page->items())->map($list->row(...))->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => $filters,
            'counts' => $list->counts($filters, $user, $everyone),
            'flags' => collect(VisitList::FLAGS)->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values()->all(),
            'ranges' => collect(VisitList::RANGES)->map(fn (string $label, int|string $key): array => ['value' => (string) $key, 'label' => $label])->values()->all(),
            'recruiters' => $everyone
                ? Person::query()->whereIn('id', FieldVisit::query()->select('person_id'))->orderBy('name')->get(['id', 'name'])
                : [],
            'properties' => Property::query()
                ->whereIn('id', FieldVisit::query()->when(! $everyone, fn ($q) => $q->where('person_id', $user->id))->select('property_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => ['viewAll' => $everyone],
            // The visit in the side panel, from ?panel=<id>. A closure, so a
            // partial reload of just the panel skips the list's queries.
            'panel' => fn (): ?array => self::panelFor($request->integer('panel'), $user),
        ]);
    }

    /**
     * One visit for the side panel, or null when there's none to show — a stale
     * or foreign id closes the panel rather than failing the page.
     *
     * @return array<string, mixed>|null
     */
    public static function panelFor(int $id, Person $user): ?array
    {
        $visit = $id > 0 ? FieldVisit::query()->find($id) : null;

        return $visit !== null && $user->can('view', $visit) ? app(VisitPanel::class)->build($visit, $user) : null;
    }

    /** The office closes a visit the recruiter forgot to check out of. */
    public function close(Request $request, FieldVisit $visit, CloseVisitForRecruiter $action): RedirectResponse
    {
        $this->authorize('close', $visit);

        $validated = $request->validate([
            'left_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $leftAt = $this->hotelTime($visit, $validated['left_at']);
        if ($leftAt->lt($visit->check_in_at) || $leftAt->gt(now())) {
            throw ValidationException::withMessages(['left_at' => 'Pick a time after they checked in and not in the future.']);
        }

        $action->handle($visit, $leftAt, $validated['note'] ?? null, $request->user());

        return back()->with('success', 'Visit closed.');
    }

    /** Super admin corrects a visit's times, with a reason. */
    public function fixTimes(Request $request, FieldVisit $visit, FixVisitTimes $action): RedirectResponse
    {
        $this->authorize('fixTimes', $visit);

        $validated = $request->validate([
            'check_in_at' => ['required', 'date_format:Y-m-d\\TH:i'],
            'check_out_at' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $in = $this->hotelTime($visit, $validated['check_in_at']);
        $out = isset($validated['check_out_at']) ? $this->hotelTime($visit, $validated['check_out_at']) : null;
        if ($in->gt(now()) || ($out !== null && ($out->lte($in) || $out->gt(now())))) {
            throw ValidationException::withMessages(['check_out_at' => 'Check-out has to come after check-in, and neither can be in the future.']);
        }

        $action->handle($visit, $in, $out, $validated['reason'], $request->user());

        return back()->with('success', 'Visit times fixed.');
    }

    /** The check-in selfie, shown in the panel to whoever may see the visit. */
    public function selfie(FieldVisit $visit): StreamedResponse
    {
        $this->authorize('view', $visit);

        $file = $visit->checkInSelfie;
        abort_unless($file !== null && Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->response($file->path, null, ['Cache-Control' => 'private, max-age=3600']);
    }

    /** A datetime-local value typed in the hotel's time, as a UTC instant. */
    private function hotelTime(FieldVisit $visit, string $value): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $visit->property?->timezone ?: CompanyTime::zone())->utc();
    }

    /** The list as an Excel file: the same filters, every page. */
    public function export(Request $request, VisitList $list): BinaryFileResponse
    {
        [$user, $everyone] = $this->viewer($request);
        $filters = $list->filters($request, $everyone);

        $rows = $list->query($filters, $user, $everyone)
            ->with(['person:id,name', 'property:id,name,timezone'])
            ->latest('check_in_at')
            ->orderByDesc('id')
            ->get()
            ->map($list->row(...))
            ->map(fn (array $r): array => [
                $r['recruiter'], $r['property'], $r['date'], $r['in'], $r['out'] ?? 'Still open',
                $r['minutes'],
                implode(', ', array_keys(array_filter([
                    'Off-site' => $r['off_site'], 'Closed late' => $r['late'], 'Covering' => $r['covering'],
                ]))),
            ]);

        return Excel::download(
            new ArrayReportExport(['Recruiter', 'Hotel', 'Date', 'Checked in', 'Checked out', 'Minutes on site', 'Flags'], $rows->all()),
            'field-visits-'.CompanyTime::now()->toDateString().'.xlsx',
        );
    }

    /**
     * @return array{0: Person, 1: bool} the viewer, and whether they see everyone's visits
     */
    private function viewer(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof Person && $user->canAny(['field_visits.view_own', 'field_visits.view_all']), 403);

        return [$user, $user->can('field_visits.view_all')];
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
