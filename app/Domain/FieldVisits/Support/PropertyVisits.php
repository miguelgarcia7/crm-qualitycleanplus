<?php

namespace App\Domain\FieldVisits\Support;

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The Visits tab on a property page: how often recruiters come by, who they
 * are, and every visit, paged on the server (ADR-0029). Times are the hotel's
 * own, since that is where the recruiter stood.
 *
 * Managers with field_visits.view_all or view_by_property see everyone's
 * visits; a recruiter sees only their own.
 */
class PropertyVisits
{
    public const PER_PAGE = 15;

    /** Keys are days back; PHP stores the numeric ones as ints. */
    public const RANGES = ['30' => 'Last 30 days', '90' => 'Last 90 days', '365' => 'Last 12 months', 'all' => 'All time'];

    /** @var list<string> */
    public const FLAGS = ['', 'off_site', 'late', 'covering'];

    /**
     * @return array<string, mixed>|null null when the viewer can't see visits at all
     */
    public function build(Property $property, Person $user, Request $request): ?array
    {
        $everyone = $user->canAny(['field_visits.view_all', 'field_visits.view_by_property']);
        if (! $everyone && ! $user->can('field_visits.view_own')) {
            return null;
        }

        $tz = $property->timezone ?: CompanyTime::zone();
        $base = fn (): Builder => FieldVisit::query()
            ->where('property_id', $property->id)
            ->when(! $everyone, fn (Builder $q) => $q->where('person_id', $user->id));

        $range = array_key_exists($request->string('visits_range')->value(), self::RANGES) ? $request->string('visits_range')->value() : '90';
        $flag = in_array($request->string('visits_flag')->value(), self::FLAGS, true) ? $request->string('visits_flag')->value() : '';
        $recruiter = $everyone && $request->integer('visits_recruiter') > 0 ? $request->integer('visits_recruiter') : null;

        $filtered = fn (): Builder => $base()
            ->when($range !== 'all', fn (Builder $q) => $q->where('check_in_at', '>=', now()->subDays((int) $range)))
            ->when($recruiter !== null, fn (Builder $q) => $q->where('person_id', $recruiter));

        $page = $this->withFlag($filtered(), $flag)
            ->with('person:id,name')
            ->latest('check_in_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE, pageName: 'visits_page', page: max(1, $request->integer('visits_page', 1)));

        return [
            'scope' => $everyone ? 'everyone' : 'own',
            'timezone' => $tz,
            'stats' => $this->stats($base, $tz),
            'recruiters' => $everyone ? $this->recruiters($property, $tz) : null,
            'filters' => ['range' => $range, 'flag' => $flag, 'recruiter' => $recruiter],
            'ranges' => collect(self::RANGES)->map(fn (string $label, int|string $value): array => ['value' => (string) $value, 'label' => $label])->values()->all(),
            'counts' => collect(self::FLAGS)->mapWithKeys(fn (string $f): array => [$f => $this->withFlag($filtered(), $f)->count()])->all(),
            'recruiterOptions' => $everyone
                ? Person::query()->whereIn('id', $base()->select('person_id'))->orderBy('name')->get(['id', 'name'])
                    ->map(fn (Person $p): array => ['id' => $p->id, 'name' => $p->name])->all()
                : [],
            'rows' => collect($page->items())->map(fn (FieldVisit $v): array => $this->row($v, $tz))->all(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'from' => $page->firstItem(),
            'to' => $page->lastItem(),
        ];
    }

    /**
     * @param  Builder<FieldVisit>  $query
     * @return Builder<FieldVisit>
     */
    private function withFlag(Builder $query, string $flag): Builder
    {
        return match ($flag) {
            'off_site' => $query->where('was_inside_geofence', false),
            'late' => $query->where('was_late_close', true),
            'covering' => $query->where('was_covering', true),
            default => $query,
        };
    }

    /**
     * Last visit plus the last 30 days, whatever the filters below say.
     *
     * @param  callable(): Builder<FieldVisit>  $base
     * @return array<string, mixed>
     */
    private function stats(callable $base, string $tz): array
    {
        $last = $base()->with('person:id,name')->latest('check_in_at')->first();
        $recent = $base()->where('check_in_at', '>=', now()->subDays(30))->get();
        $durations = $recent->map(fn (FieldVisit $v): ?int => $v->durationMinutes())->filter(fn (?int $m): bool => $m !== null);

        return [
            'last' => $last === null ? null : [
                'ago' => $this->daysAgo($last->check_in_at, $tz),
                'date' => $last->check_in_at->setTimezone($tz)->format('D, M j'),
                'recruiter' => $last->person?->name,
            ],
            'visits30' => $recent->count(),
            'avgMinutes30' => $durations->isEmpty() ? null : (int) round($durations->avg()),
            'flagged30' => [
                'off_site' => $recent->where('was_inside_geofence', false)->count(),
                'late' => $recent->where('was_late_close', true)->count(),
                'covering' => $recent->where('was_covering', true)->count(),
            ],
        ];
    }

    /**
     * Recruiters assigned here, plus anyone who covered in the last 30 days.
     *
     * @return list<array<string, mixed>>
     */
    private function recruiters(Property $property, string $tz): array
    {
        $assigned = $property->assignments()
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->pluck('person_id')
            ->all();

        $covering = FieldVisit::query()
            ->where('property_id', $property->id)
            ->where('check_in_at', '>=', now()->subDays(30))
            ->whereNotIn('person_id', $assigned)
            ->distinct()
            ->pluck('person_id')
            ->all();

        return Person::query()
            ->whereIn('id', [...$assigned, ...$covering])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Person $p) use ($property, $assigned, $tz): array {
                $visits = FieldVisit::query()->where('property_id', $property->id)->where('person_id', $p->id);
                $last = (clone $visits)->max('check_in_at');

                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'assigned' => in_array($p->id, $assigned, true),
                    'last' => $last === null ? null : CarbonImmutable::parse($last, 'UTC')->setTimezone($tz)->format('M j'),
                    'visits30' => (clone $visits)->where('check_in_at', '>=', now()->subDays(30))->count(),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(FieldVisit $v, string $tz): array
    {
        $in = $v->check_in_at->setTimezone($tz);
        $out = $v->check_out_at?->setTimezone($tz);

        return [
            'id' => $v->id,
            'date' => $in->format('D, M j'),
            'recruiter' => $v->person?->name,
            'in' => $in->format('g:i A'),
            // A visit closed another day (forgot to check out) says which day.
            'out' => $out === null ? null : ($out->isSameDay($in) ? $out->format('g:i A') : $out->format('M j, g:i A')),
            'minutes' => $v->durationMinutes(),
            'open' => $v->check_out_at === null,
            'off_site' => ! $v->was_inside_geofence,
            'late' => $v->was_late_close,
            'covering' => $v->was_covering,
        ];
    }

    private function daysAgo(CarbonImmutable $at, string $tz): string
    {
        $days = (int) $at->setTimezone($tz)->startOfDay()->diffInDays(CarbonImmutable::now($tz)->startOfDay());

        return match (true) {
            $days <= 0 => 'Today',
            $days === 1 => 'Yesterday',
            default => "{$days} days ago",
        };
    }
}
