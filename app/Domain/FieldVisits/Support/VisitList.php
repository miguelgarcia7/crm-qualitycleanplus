<?php

namespace App\Domain\FieldVisits\Support;

use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The Field Visits list: every recruiter check-in, filtered and paged on the
 * server (ADR-0029). The page, its counts and its Excel export all read the
 * same normalised filters, so the download always matches the screen.
 *
 * Whoever holds field_visits.view_all sees everyone; a recruiter sees only
 * their own visits. Times are each hotel's own.
 */
class VisitList
{
    /** @var list<int> */
    public const PER_PAGE = [10, 25, 50];

    /** Visit flags, as the toolbar pills offer them. */
    public const FLAGS = ['' => 'All', 'open' => 'Open', 'stale' => 'Open over 24 h', 'off_site' => 'Off-site', 'late' => 'Closed late', 'covering' => 'Covering'];

    public const RANGES = ['30' => 'Last 30 days', '90' => 'Last 90 days', 'month' => 'This month', 'last_month' => 'Last month', 'all' => 'All time', 'custom' => 'Pick dates…'];

    /** Links from before the pills were renamed (dashboards, bookmarks). */
    private const LEGACY_FILTERS = ['open' => 'open', 'off_geofence' => 'off_site', 'late' => 'late'];

    /**
     * The query string, normalised against whitelists.
     *
     * @return array{flag: string, search: string, range: string, from: string|null, to: string|null, recruiter_id: int|null, property_id: int|null, per_page: int}
     */
    public function filters(Request $request, bool $everyone): array
    {
        $flag = $request->string('flag')->value() ?: (self::LEGACY_FILTERS[$request->string('filter')->value()] ?? '');
        $range = $request->string('range')->value();
        $from = $this->date($request->string('from')->value());
        $to = $this->date($request->string('to')->value());
        $perPage = $request->integer('per_page', 25);

        $range = array_key_exists($range, self::RANGES) ? $range : '30';
        if ($range === 'custom' && $from === null && $to === null) {
            $range = '30';
        }

        return [
            'flag' => array_key_exists($flag, self::FLAGS) ? $flag : '',
            'search' => trim($request->string('search')->value()),
            'range' => $range,
            'from' => $range === 'custom' ? $from : null,
            'to' => $range === 'custom' ? $to : null,
            'recruiter_id' => $everyone ? ($request->integer('recruiter_id') ?: null) : null,
            'property_id' => $request->integer('property_id') ?: null,
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * Visits matching every filter but the flag — the counts start here.
     *
     * Open visits ignore the date range: a visit left open for weeks is exactly
     * the one the office is looking for.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<FieldVisit>
     */
    public function query(array $filters, Person $user, bool $everyone, ?string $flag = null): Builder
    {
        $flag ??= $filters['flag'];
        $query = FieldVisit::query()
            ->when(! $everyone, fn (Builder $q) => $q->where('field_visits.person_id', $user->id))
            ->when($filters['recruiter_id'], fn (Builder $q, int $id) => $q->where('field_visits.person_id', $id))
            ->when($filters['property_id'], fn (Builder $q, int $id) => $q->where('field_visits.property_id', $id))
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['search']).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereHas('person', fn (Builder $p) => $p->where('name', 'like', $like))
                    ->orWhereHas('property', fn (Builder $p) => $p->where('name', 'like', $like)));
            });

        if (! in_array($flag, ['open', 'stale'], true)) {
            $this->applyRange($query, $filters);
        }

        return match ($flag) {
            'open' => $query->open(),
            'stale' => $query->open()->where('check_in_at', '<=', now()->subDay()),
            'off_site' => $query->where('was_inside_geofence', false),
            'late' => $query->where('was_late_close', true),
            'covering' => $query->where('was_covering', true),
            default => $query,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    public function counts(array $filters, Person $user, bool $everyone): array
    {
        return collect(array_keys(self::FLAGS))
            ->mapWithKeys(fn (string $flag): array => [$flag => $this->query($filters, $user, $everyone, $flag)->count()])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(FieldVisit $v): array
    {
        $tz = $v->property?->timezone ?: CompanyTime::zone();
        $in = $v->check_in_at->setTimezone($tz);
        $out = $v->check_out_at?->setTimezone($tz);

        return [
            'id' => $v->id,
            'recruiter' => $v->person?->name,
            'recruiter_id' => $v->person_id,
            'property' => $v->property?->name,
            'property_id' => $v->property_id,
            'date' => $in->format('D, M j'),
            'in' => $in->format('g:i A'),
            // A visit closed on a later day (forgot to check out) says which day.
            'out' => $out === null ? null : ($out->isSameDay($in) ? $out->format('g:i A') : $out->format('M j, g:i A')),
            'minutes' => $v->durationMinutes(),
            'open_hours' => $v->check_out_at === null ? (int) $v->check_in_at->diffInHours(now()) : null,
            'off_site' => ! $v->was_inside_geofence,
            'late' => $v->was_late_close,
            'covering' => $v->was_covering,
        ];
    }

    /**
     * @param  Builder<FieldVisit>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyRange(Builder $query, array $filters): void
    {
        $today = CompanyTime::now();

        [$start, $end] = match ($filters['range']) {
            '30' => [$today->subDays(30)->startOfDay(), null],
            '90' => [$today->subDays(90)->startOfDay(), null],
            'month' => [$today->startOfMonth(), null],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                $filters['from'] ? CarbonImmutable::parse($filters['from'], CompanyTime::zone())->startOfDay() : null,
                $filters['to'] ? CarbonImmutable::parse($filters['to'], CompanyTime::zone())->endOfDay() : null,
            ],
            default => [null, null],
        };

        // Central dates against a UTC column: convert, or the binding keeps the wall clock (ADR-0030).
        $query->when($start, fn (Builder $q) => $q->where('check_in_at', '>=', $start->utc()))
            ->when($end, fn (Builder $q) => $q->where('check_in_at', '<=', $end->utc()));
    }

    private function date(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }
}
