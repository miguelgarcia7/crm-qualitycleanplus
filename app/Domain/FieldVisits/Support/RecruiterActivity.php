<?php

namespace App\Domain\FieldVisits\Support;

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Property;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Reports → Recruiter activity: how often each recruiter visits their hotels
 * over a week or a month, week by week, and which hotels nobody has visited
 * lately. Periods are company weeks and months (ADR-0030); "not visited" is
 * as of today, whatever period is picked. The page and its Excel export read
 * the same build, so the download matches the screen.
 */
class RecruiterActivity
{
    /** A hotel counts as not visited lately after this many days. */
    public const STALE_DAYS = 14;

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $period = $request->string('period')->value() === 'week' ? 'week' : 'month';
        [$start, $end] = $this->bounds($period, $request->string('at')->value());
        [$prevStart, $prevEnd] = $this->bounds($period, ($period === 'week' ? $start->subWeek() : $start->subMonthNoOverflow())->toDateString());
        $recruiterId = $request->integer('recruiter_id') ?: null;

        $visits = FieldVisit::query()
            ->whereBetween('check_in_at', [$start->utc(), $end->utc()])
            ->when($recruiterId, fn ($q, int $id) => $q->where('person_id', $id))
            ->get();

        // Active hotels with a recruiter on them, and who that is.
        $assignments = Property::query()
            ->where('status', PropertyStatus::Active->value)
            ->with(['assignments' => fn ($q) => $q->where('role', PropertyAssignmentRole::Recruiter->value)->with('person:id,name')])
            ->whereHas('assignments', fn ($q) => $q->where('role', PropertyAssignmentRole::Recruiter->value))
            ->orderBy('name')
            ->get(['id', 'name']);

        $recruiters = Person::query()
            ->where(fn ($q) => $q
                ->whereIn('id', $visits->pluck('person_id')->unique())
                ->orWhere(fn ($q) => $q->role('recruiter')->where('status', PersonStatus::StaffActive->value)))
            ->when($recruiterId, fn ($q, int $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $stale = collect($this->notVisitedLately($assignments, $recruiterId));
        $openNow = FieldVisit::query()->open()->when($recruiterId, fn ($q, int $id) => $q->where('person_id', $id))->get(['id', 'person_id']);
        $weeks = $this->buckets($period, $start, $end);

        $rows = $recruiters->map(function (Person $r) use ($visits, $assignments, $stale, $openNow, $weeks): array {
            $mine = $visits->where('person_id', $r->id);
            $hotels = $assignments->filter(fn (Property $p): bool => $p->assignments->contains('person_id', $r->id))->pluck('id');

            return [
                'id' => $r->id,
                'name' => $r->name,
                'visits' => $mine->count(),
                'hotels_visited' => $mine->pluck('property_id')->unique()->intersect($hotels)->count(),
                'hotels_assigned' => $hotels->count(),
                'not_visited' => $stale->filter(fn (array $s): bool => in_array($r->id, $s['recruiter_ids'], true))->count(),
                'avg_minutes' => $this->avgMinutes($mine),
                'off_site' => $mine->where('was_inside_geofence', false)->count(),
                'late' => $mine->where('was_late_close', true)->count(),
                'covering' => $mine->where('was_covering', true)->count(),
                'open_now' => $openNow->where('person_id', $r->id)->count(),
                'by_week' => array_map(fn (array $w): int => $mine->filter(fn (FieldVisit $v): bool => $v->check_in_at->betweenIncluded($w['start'], $w['end']))->count(), $weeks),
            ];
        })->values();

        $assignedTotal = $recruiterId ? $rows->sum('hotels_assigned') : $assignments->count();

        return [
            'filters' => ['period' => $period, 'at' => $start->toDateString(), 'recruiter_id' => $recruiterId],
            'label' => $period === 'week' ? 'Week of '.$start->format('M j, Y') : $start->format('F Y'),
            'previous' => $prevStart->toDateString(),
            'next' => $end->lt(CompanyTime::now()) ? $end->addDay()->toDateString() : null,
            'range' => ['from' => $start->toDateString(), 'to' => $end->toDateString()],
            'summary' => [
                'visits' => $visits->count(),
                'previous_visits' => FieldVisit::query()
                    ->whereBetween('check_in_at', [$prevStart->utc(), $prevEnd->utc()])
                    ->when($recruiterId, fn ($q, int $id) => $q->where('person_id', $id))
                    ->count(),
                'hotels_visited' => $visits->pluck('property_id')->unique()->intersect($assignments->pluck('id'))->count(),
                'hotels_assigned' => $assignedTotal,
                'avg_minutes' => $this->avgMinutes($visits),
                'off_site' => $visits->where('was_inside_geofence', false)->count(),
                'late' => $visits->where('was_late_close', true)->count(),
                'open_now' => $openNow->count(),
            ],
            'rows' => $rows->all(),
            'weeks' => array_map(fn (array $w): string => $w['label'], $weeks),
            'stale' => $stale->map(fn (array $s): array => collect($s)->except('recruiter_ids')->all())->values()->all(),
            'staleDays' => self::STALE_DAYS,
            'recruiters' => Person::query()->role('recruiter')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * The period's first and last moment in company time.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function bounds(string $period, string $at): array
    {
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $at) === 1
            ? CarbonImmutable::parse($at, CompanyTime::zone())
            : CompanyTime::now();

        return $period === 'week'
            ? [$day->startOfWeek(CarbonImmutable::MONDAY), $day->endOfWeek(CarbonImmutable::SUNDAY)]
            : [$day->startOfMonth(), $day->endOfMonth()];
    }

    /**
     * Columns for "week by week": the Monday weeks a month touches, or the
     * seven days of a week. Each is clipped to the period.
     *
     * @return list<array{label: string, start: CarbonImmutable, end: CarbonImmutable}>
     */
    private function buckets(string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $out = [];
        if ($period === 'week') {
            for ($d = $start; $d->lte($end); $d = $d->addDay()) {
                $out[] = ['label' => $d->format('D j'), 'start' => $d->startOfDay(), 'end' => $d->endOfDay()];
            }

            return $out;
        }

        for ($w = $start->startOfWeek(CarbonImmutable::MONDAY); $w->lte($end); $w = $w->addWeek()) {
            $out[] = ['label' => $w->format('M j'), 'start' => $w->max($start), 'end' => $w->endOfWeek(CarbonImmutable::SUNDAY)->min($end)];
        }

        return $out;
    }

    /**
     * Hotels with a recruiter assigned that no recruiter has visited in
     * STALE_DAYS or more, longest first; never visited at the top.
     *
     * @param  Collection<int, Property>  $assignments
     * @return list<array{id: int, hotel: string, recruiters: string, recruiter_ids: array<mixed>, last: string|null, days: int|null}>
     */
    private function notVisitedLately(Collection $assignments, ?int $recruiterId): array
    {
        $last = FieldVisit::query()
            ->whereIn('property_id', $assignments->pluck('id'))
            ->groupBy('property_id')
            ->selectRaw('property_id, MAX(check_in_at) as last_at')
            ->pluck('last_at', 'property_id');
        $today = CompanyTime::now()->startOfDay();

        return $assignments
            ->map(function (Property $p) use ($last, $today): array {
                $at = isset($last[$p->id]) ? CompanyTime::of(CarbonImmutable::parse($last[$p->id], 'UTC')) : null;

                return [
                    'id' => $p->id,
                    'hotel' => $p->name,
                    'recruiters' => $p->assignments->map(fn ($a) => $a->person?->name)->filter()->implode(', '),
                    'recruiter_ids' => $p->assignments->pluck('person_id')->all(),
                    'last' => $at?->format('D, M j'),
                    'days' => $at === null ? null : (int) $at->startOfDay()->diffInDays($today),
                ];
            })
            ->filter(fn (array $s): bool => ($s['days'] === null || $s['days'] >= self::STALE_DAYS)
                && ($recruiterId === null || in_array($recruiterId, $s['recruiter_ids'], true)))
            ->sortByDesc(fn (array $s): int => $s['days'] ?? PHP_INT_MAX)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, FieldVisit>  $visits
     */
    private function avgMinutes(Collection $visits): ?int
    {
        $minutes = $visits->map(fn (FieldVisit $v): ?int => $v->durationMinutes())->filter(fn (?int $m): bool => $m !== null);

        return $minutes->isEmpty() ? null : (int) round($minutes->avg());
    }
}
