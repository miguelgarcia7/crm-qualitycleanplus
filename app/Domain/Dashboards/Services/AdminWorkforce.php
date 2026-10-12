<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The admin's Workforce view (docs/50-ui/dashboard-mockups/Admin-Workforce):
 * how many contractors are working and where, hiring this month, each
 * recruiter's week, who is close to direct hire, and staff away.
 */
class AdminWorkforce
{
    private const PROPERTY_ROWS = 6;

    private const LIST_LIMIT = 5;

    private const POSTING_ROWS = 3;

    /** Contractors this close to their direct-hire hours are worth a heads-up. */
    private const DIRECT_HIRE_WEEKS = 10;

    private const STAFF_AWAY_DAYS = 30;

    public function __construct(private readonly DirectHireProgress $directHire) {}

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $today = CarbonImmutable::now(AutomationCatalog::DISPLAY_TIMEZONE)->startOfDay();
        $activeWorkOrders = WorkOrder::query()
            ->where('status', WorkOrderStatus::Active->value)
            ->get(['id', 'person_id', 'property_id', 'direct_hire_threshold_minutes']);

        return [
            'headline' => $this->headline($today),
            'byProperty' => $this->byProperty($activeWorkOrders),
            'hiring' => $this->hiring($today),
            'recruiters' => $this->recruiters($today, $activeWorkOrders),
            'directHire' => $this->directHire($today, $activeWorkOrders),
            'staffAway' => $this->staffAway($today),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function headline(CarbonImmutable $today): array
    {
        $monthStart = $today->startOfMonth()->toDateString();

        $started = WorkOrder::query()->whereDate('start_date', '>=', $monthStart)->whereDate('start_date', '<=', $today->toDateString())->count();
        $ended = WorkOrder::query()->whereNotNull('end_date')->whereDate('end_date', '>=', $monthStart)->whereDate('end_date', '<=', $today->toDateString())->count();

        $open = TimeEntry::query()->whereNotNull('start_at_utc')->whereNull('end_at_utc');
        $onClock = $open->clone()->distinct()->count('person_id');
        $atProperties = $open->clone()->distinct()->count('property_id');

        $requests = MoreStaffRequest::query()->whereIn('status', [MoreStaffStatus::Submitted->value, MoreStaffStatus::InProgress->value])->get(['quantity_requested', 'quantity_fulfilled']);
        $toPlace = (int) $requests->sum(fn (MoreStaffRequest $r): int => $r->remaining());

        $onboarding = Person::query()
            ->where('status', PersonStatus::Applicant)
            ->whereHas('jobApplications', fn ($q) => $q->where('status', JobApplicationStatus::Reviewing->value))
            ->get()
            ->reject(fn (Person $applicant): bool => OnboardingChecklist::isComplete($applicant))
            ->count();

        return [
            [
                'key' => 'active',
                'label' => 'Active contractors',
                'value' => Person::query()->where('status', PersonStatus::ContractorActive->value)->count(),
                'note' => $started + $ended > 0 ? "+{$started} placed, −{$ended} ended this month" : 'No placements started or ended this month',
                'warn' => false,
                'href' => '/admin/people',
            ],
            [
                'key' => 'clock',
                'label' => 'On the clock now',
                'value' => $onClock,
                'note' => $onClock > 0 ? 'at '.$this->plural($atProperties, 'property', 'properties') : 'Nobody clocked in',
                'warn' => false,
                'href' => '/admin/timesheets',
            ],
            [
                'key' => 'staffing',
                'label' => 'Open staffing requests',
                'value' => $requests->count(),
                'note' => $toPlace > 0 ? $this->plural($toPlace, 'contractor', 'contractors').' still to place' : 'Nothing to fill',
                'warn' => $toPlace > 0,
                'href' => '/admin/staffing-requests',
            ],
            [
                'key' => 'onboarding',
                'label' => 'Onboarding pending',
                'value' => $onboarding,
                'note' => $onboarding > 0 ? 'documents still missing' : 'Every applicant in review is complete',
                'warn' => false,
                'href' => '/admin/applicants?status=reviewing',
            ],
        ];
    }

    /**
     * Contractors placed at each property, largest first, with any open
     * staffing request's shortfall.
     *
     * @param  Collection<int, WorkOrder>  $activeWorkOrders
     * @return array<string, mixed>
     */
    private function byProperty(Collection $activeWorkOrders): array
    {
        $people = $activeWorkOrders->groupBy('property_id')->map(fn (Collection $group): int => $group->pluck('person_id')->unique()->count());

        $needed = MoreStaffRequest::query()
            ->whereIn('status', [MoreStaffStatus::Submitted->value, MoreStaffStatus::InProgress->value])
            ->get(['property_id', 'quantity_requested', 'quantity_fulfilled'])
            ->groupBy('property_id')
            ->map(fn (Collection $group): int => (int) $group->sum(fn (MoreStaffRequest $r): int => $r->remaining()));

        $properties = Property::query()->orderBy('name')->get(['id', 'name']);

        $rows = $properties
            ->map(fn (Property $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'contractors' => (int) ($people[$p->id] ?? 0),
                'needed' => (int) ($needed[$p->id] ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['contractors'] > 0 || $row['needed'] > 0)
            ->sortByDesc('contractors')
            ->values();

        return [
            'rows' => $rows->take(self::PROPERTY_ROWS)->all(),
            'total' => $properties->count(),
        ];
    }

    /**
     * This month's applications, from applied to placed, and the open
     * postings drawing the most applicants.
     *
     * @return array<string, mixed>
     */
    private function hiring(CarbonImmutable $today): array
    {
        $monthStart = $today->startOfMonth()->utc();

        $postings = JobPosting::query()
            ->where('status', JobPostingStatus::Published->value)
            ->with('property:id,name')
            ->withCount('applications')
            ->orderByDesc('applications_count')
            ->get();

        return [
            'applied' => JobApplication::query()->where('submitted_at', '>=', $monthStart)->count(),
            'inReview' => JobApplication::query()->where('status', JobApplicationStatus::Reviewing->value)->count(),
            'placed' => JobApplication::query()->whereNotNull('promoted_at')->where('promoted_at', '>=', $monthStart)->count(),
            'postings' => $postings->take(self::POSTING_ROWS)->map(fn (JobPosting $posting): array => [
                'id' => $posting->id,
                'title' => $posting->title,
                'where' => $posting->locationName(),
                'applied' => (int) $posting->getAttribute('applications_count'),
            ])->values()->all(),
            'openPostings' => $postings->count(),
        ];
    }

    /**
     * Each recruiter's week: properties, contractors, field visits, visits
     * still open, and finished weeks with hours not yet sent for approval.
     *
     * @param  Collection<int, WorkOrder>  $activeWorkOrders
     * @return list<array<string, mixed>>
     */
    private function recruiters(CarbonImmutable $today, Collection $activeWorkOrders): array
    {
        $recruiters = Person::role('recruiter')->orderBy('name')->get(['id', 'name']);

        if ($recruiters->isEmpty()) {
            return [];
        }

        $assignments = PropertyAssignment::query()
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->whereIn('person_id', $recruiters->pluck('id'))
            ->get(['person_id', 'property_id'])
            ->groupBy('person_id');

        $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY)->utc();
        $visits = FieldVisit::query()->whereIn('person_id', $recruiters->pluck('id'))->where('check_in_at', '>=', $weekStart)
            ->selectRaw('person_id, COUNT(*) as n')->groupBy('person_id')->pluck('n', 'person_id');
        $openVisits = FieldVisit::query()->open()->whereIn('person_id', $recruiters->pluck('id'))
            ->selectRaw('person_id, COUNT(*) as n')->groupBy('person_id')->pluck('n', 'person_id');

        $notSent = $this->unsentByProperty($today);

        return $recruiters->map(function (Person $recruiter) use ($assignments, $activeWorkOrders, $visits, $openVisits, $notSent): array {
            $propertyIds = $assignments->get($recruiter->id, collect())->pluck('property_id')->unique();

            return [
                'id' => $recruiter->id,
                'name' => $recruiter->name,
                'properties' => $propertyIds->count(),
                'contractors' => $activeWorkOrders->whereIn('property_id', $propertyIds)->pluck('person_id')->unique()->count(),
                'visits' => (int) ($visits[$recruiter->id] ?? 0),
                'openVisits' => (int) ($openVisits[$recruiter->id] ?? 0),
                'notSent' => (int) $propertyIds->sum(fn (int $id): int => $notSent[$id] ?? 0),
            ];
        })
            ->sortByDesc('properties')
            ->values()
            ->all();
    }

    /**
     * Finished weeks that logged hours but are still draft or declined,
     * counted per property.
     *
     * @return array<int, int>
     */
    private function unsentByProperty(CarbonImmutable $today): array
    {
        return Timesheet::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->whereIn('timesheets.status', [TimesheetStatus::Draft->value, TimesheetStatus::Declined->value])
            ->whereDate('payroll_periods.week_end', '<', $today->toDateString())
            ->whereExists(fn ($q) => $q->from('time_summaries')
                ->whereColumn('time_summaries.payroll_period_id', 'timesheets.payroll_period_id')
                ->whereRaw('regular_minutes + overtime_minutes + holiday_minutes + training_minutes > 0'))
            ->selectRaw('timesheets.property_id, COUNT(*) as n')
            ->groupBy('timesheets.property_id')
            ->pluck('n', 'timesheets.property_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /**
     * Contractors who reach their property's direct-hire hours within ten
     * weeks at their recent pace (the average of their last two finished
     * weeks), soonest first.
     *
     * @param  Collection<int, WorkOrder>  $activeWorkOrders
     * @return array<string, mixed>
     */
    private function directHire(CarbonImmutable $today, Collection $activeWorkOrders): array
    {
        $progress = $this->directHire->forMany($activeWorkOrders);

        $candidates = $activeWorkOrders->filter(fn (WorkOrder $wo): bool => ! $progress[$wo->id]['eligible'] && ! $progress[$wo->id]['unrestricted']);

        $pace = TimeSummary::query()
            ->whereIn('work_order_id', $candidates->pluck('id'))
            ->whereDate('week_end', '<', $today->toDateString())
            ->whereDate('week_start', '>=', $today->subWeeks(5)->toDateString())
            ->orderByDesc('week_start')
            ->get(['work_order_id', 'week_start', 'regular_minutes', 'overtime_minutes', 'holiday_minutes'])
            ->groupBy('work_order_id')
            ->map(fn (Collection $weeks): float => $weeks->take(2)->avg(fn (TimeSummary $s): int => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes) / 60);

        $rows = $candidates
            ->map(function (WorkOrder $wo) use ($progress, $pace): ?array {
                $hours = (float) ($pace[$wo->id] ?? 0);
                if ($hours < 1) {
                    return null;
                }

                $weeks = (int) ceil($progress[$wo->id]['remaining_hours'] / $hours);

                return $weeks <= self::DIRECT_HIRE_WEEKS ? [
                    'workOrderId' => $wo->id,
                    'personId' => $wo->person_id,
                    'propertyId' => $wo->property_id,
                    'worked' => $progress[$wo->id]['worked_hours'],
                    'threshold' => $progress[$wo->id]['threshold_hours'],
                    'weeks' => $weeks,
                ] : null;
            })
            ->filter()
            ->sortBy('weeks')
            ->values();

        $shown = $rows->take(self::LIST_LIMIT);
        $names = Person::query()->whereIn('id', $shown->pluck('personId'))->pluck('name', 'id');
        $properties = Property::withTrashed()->whereIn('id', $shown->pluck('propertyId'))->pluck('name', 'id');

        return [
            'rows' => $shown->map(fn (array $row): array => [
                'id' => $row['workOrderId'],
                'name' => $names[$row['personId']] ?? null,
                'property' => $properties[$row['propertyId']] ?? null,
                'worked' => $row['worked'],
                'threshold' => $row['threshold'],
                'weeks' => $row['weeks'],
                'href' => "/admin/people/{$row['personId']}",
            ])->all(),
            'total' => $rows->count(),
            'withinWeeks' => self::DIRECT_HIRE_WEEKS,
        ];
    }

    /**
     * Approved time off for staff in the next 30 days, soonest first.
     *
     * @return array<string, mixed>
     */
    private function staffAway(CarbonImmutable $today): array
    {
        $requests = PtoRequest::query()
            ->where('status', PtoRequestStatus::Approved->value)
            ->whereDate('end_date', '>=', $today->toDateString())
            ->whereDate('start_date', '<=', $today->addDays(self::STAFF_AWAY_DAYS)->toDateString())
            ->with('person:id,name')
            ->orderBy('start_date')
            ->get();

        return [
            'rows' => $requests->take(self::LIST_LIMIT)->map(fn (PtoRequest $request): array => [
                'id' => $request->id,
                'name' => $request->person?->name,
                'dates' => $this->range($request->start_date, $request->end_date),
                'kind' => strtolower($request->bucket->label()),
                'now' => $request->start_date->toDateString() <= $today->toDateString(),
            ])->all(),
            'total' => $requests->count(),
        ];
    }

    private function range(CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j');
        }

        return $start->format('M j').' – '.($start->isSameMonth($end) ? $end->format('j') : $end->format('M j'));
    }

    private function plural(int $count, string $one, string $many): string
    {
        return number_format($count).' '.($count === 1 ? $one : $many);
    }
}
