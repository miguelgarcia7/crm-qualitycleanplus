<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Dashboards\Support\ApprovalQueue;
use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\Dashboards\Support\DirectHireSoon;
use App\Domain\Dashboards\Support\RequestQueue;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;

/**
 * The recruiter's dashboard (docs/50-ui/dashboard-mockups/Recruiter): their
 * own properties only. Last week's timesheets to fix and submit, everything
 * else waiting on them, weeks waiting on a property manager, and what expires,
 * nears direct hire or hasn't been visited lately.
 */
class RecruiterHome
{
    private const LIST_LIMIT = 5;

    private const SIDE_LIMIT = 3;

    private const EXPIRING_DAYS = 30;

    /** A property not visited for longer than this is flagged. */
    private const VISIT_WARN_DAYS = 14;

    public function __construct(
        private readonly ApprovalQueue $approvals,
        private readonly RequestQueue $requests,
        private readonly DirectHireSoon $directHire,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Person $user): array
    {
        $now = CompanyTime::now();
        $today = $now->startOfDay();

        $propertyIds = PropertyAssignment::query()
            ->where('person_id', $user->id)
            ->where('role', PropertyAssignmentRole::Recruiter->value)
            ->distinct()
            ->pluck('property_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $activeWorkOrders = WorkOrder::query()
            ->where('status', WorkOrderStatus::Active->value)
            ->whereIn('property_id', $propertyIds)
            ->get(['id', 'person_id', 'property_id', 'direct_hire_threshold_minutes']);

        return [
            'header' => [
                'greeting' => DashboardFormat::greeting($user, $now),
                'date' => $now->format('l, M j'),
                'properties' => count($propertyIds),
                'contractors' => $activeWorkOrders->pluck('person_id')->unique()->count(),
            ],
            'actions' => [
                'checkIn' => $user->can('field_visits.create'),
                'newWorkOrder' => $user->can('work_orders.create'),
            ],
            'lastWeek' => $this->lastWeek($user, $propertyIds, $today),
            'waiting' => $this->waiting($user, $propertyIds, $now),
            'managers' => [
                'rows' => $this->approvals->oldest($propertyIds, $now, self::LIST_LIMIT),
                'total' => $this->approvals->query($propertyIds)->count(),
                'slaDays' => ApprovalQueue::SLA_DAYS,
            ],
            'expiring' => $this->expiring($propertyIds, $today),
            'directHire' => $this->directHire->build($activeWorkOrders, $today, self::SIDE_LIMIT),
            'visits' => $this->visits($user, $propertyIds, $today),
        ];
    }

    /**
     * Each property's pay week that ended in the past seven days and logged
     * hours: open punches to fix first, then weeks to submit. Weeks already
     * with the property manager or billed are only counted.
     *
     * @param  list<int>  $propertyIds
     * @return array<string, mixed>
     */
    private function lastWeek(Person $user, array $propertyIds, CarbonImmutable $today): array
    {
        $periods = PayrollPeriod::query()
            ->whereIn('property_id', $propertyIds)
            ->whereDate('week_end', '>=', $today->subDays(7)->toDateString())
            ->whereDate('week_end', '<', $today->toDateString())
            ->with(['property:id,name', 'timesheet'])
            ->get();

        $minutes = TimeSummary::query()
            ->whereIn('payroll_period_id', $periods->pluck('id'))
            ->groupBy('payroll_period_id')
            ->selectRaw('payroll_period_id, SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as minutes')
            ->pluck('minutes', 'payroll_period_id');

        $open = TimeEntry::query()
            ->whereIn('payroll_period_id', $periods->pluck('id'))
            ->whereNotNull('start_at_utc')
            ->whereNull('end_at_utc')
            ->groupBy('payroll_period_id')
            ->selectRaw('payroll_period_id, COUNT(*) as n')
            ->pluck('n', 'payroll_period_id');

        $canSubmit = $user->can('timesheets.submit_for_approval');

        $weeks = $periods
            ->map(function (PayrollPeriod $period) use ($minutes, $open, $canSubmit): array {
                $status = $period->timesheet !== null ? $period->timesheet->status : TimesheetStatus::Draft;
                $openPunches = (int) ($open[$period->id] ?? 0);
                $grid = "/admin/properties/{$period->property_id}/grid?week={$period->week_start->toDateString()}";
                $toSend = $status->canSubmit();

                return [
                    'id' => $period->id,
                    'property' => $period->property?->name,
                    'week' => DashboardFormat::range($period->week_start, $period->week_end),
                    'hours' => round(((int) ($minutes[$period->id] ?? 0)) / 60, 2),
                    'openPunches' => $openPunches,
                    'status' => $status->value,
                    'statusLabel' => $status === TimesheetStatus::Declined ? 'Sent back' : $status->label(),
                    'needsAction' => $toSend,
                    'action' => match (true) {
                        ! $toSend => null,
                        $openPunches > 0 => ['label' => 'Fix punches', 'href' => $grid],
                        $canSubmit && $period->timesheet !== null => ['label' => 'Submit', 'submitId' => $period->timesheet->id],
                        default => ['label' => 'Open week', 'href' => $grid],
                    },
                    'href' => $grid,
                ];
            })
            ->filter(fn (array $week): bool => $week['hours'] > 0 || $week['openPunches'] > 0)
            ->values();

        $toDo = $weeks->where('needsAction', true)
            ->sortBy([
                fn (array $a, array $b) => ($b['openPunches'] > 0) <=> ($a['openPunches'] > 0),
                fn (array $a, array $b) => ($b['status'] === TimesheetStatus::Declined->value) <=> ($a['status'] === TimesheetStatus::Declined->value),
                fn (array $a, array $b) => strcmp((string) $a['property'], (string) $b['property']),
            ])
            ->values();

        return [
            'week' => $weeks->pluck('week')->countBy()->sortDesc()->keys()->first(),
            'rows' => $toDo->map(fn (array $week): array => collect($week)->except('needsAction')->all())->all(),
            'total' => $weeks->count(),
            'submitted' => $weeks->count() - $toDo->count(),
        ];
    }

    /**
     * Everything else waiting on the recruiter at their properties, oldest
     * first.
     *
     * @param  list<int>  $propertyIds
     * @return array<string, mixed>
     */
    private function waiting(Person $user, array $propertyIds, CarbonImmutable $now): array
    {
        $groups = array_values(array_filter([
            $user->can('workflows.more_staff.fulfill') ? $this->requests->staffing($propertyIds) : null,
            $user->can('workflows.pay_increase.approve') ? $this->requests->payIncreases($propertyIds) : null,
            $user->can('invoices.send') ? $this->requests->invoicesToSend($propertyIds) : null,
            $user->can('people.applicants.view') ? $this->requests->applications($propertyIds) : null,
            $user->can('people.applicants.view') ? $this->requests->onboarding($propertyIds) : null,
        ]));

        return $this->requests->merge($groups, $now, self::LIST_LIMIT);
    }

    /**
     * Contracts at the recruiter's properties ending in the next 30 days.
     *
     * @param  list<int>  $propertyIds
     * @return array<string, mixed>
     */
    private function expiring(array $propertyIds, CarbonImmutable $today): array
    {
        $contracts = Contract::query()
            ->whereIn('property_id', $propertyIds)
            ->expiringWithin(self::EXPIRING_DAYS)
            ->with('property:id,name')
            ->orderBy('expiration_date');

        return [
            'rows' => $contracts->clone()->limit(self::SIDE_LIMIT)->get()->map(function (Contract $contract) use ($today): array {
                $ends = CarbonImmutable::parse((string) $contract->expiration_date?->toDateString());
                $days = (int) CarbonImmutable::parse($today->toDateString())->diffInDays($ends, true);

                return [
                    'id' => $contract->id,
                    'title' => "{$contract->property?->name} · {$contract->name}",
                    'detail' => 'Ends '.$ends->format('M j'),
                    'days' => $days,
                    'tone' => $days <= 14 ? 'danger' : ($days <= 21 ? 'warning' : 'gray'),
                    'href' => "/admin/properties/{$contract->property_id}#settings/contracts",
                ];
            })->all(),
            'total' => $contracts->clone()->count(),
        ];
    }

    /**
     * The recruiter's properties they haven't checked in at for longest,
     * never-visited first, plus this week's visits.
     *
     * @param  list<int>  $propertyIds
     * @return array<string, mixed>
     */
    private function visits(Person $user, array $propertyIds, CarbonImmutable $today): array
    {
        $last = FieldVisit::query()
            ->where('person_id', $user->id)
            ->whereIn('property_id', $propertyIds)
            ->groupBy('property_id')
            ->selectRaw('property_id, MAX(check_in_at) as last_at')
            ->pluck('last_at', 'property_id');

        $rows = Property::query()
            ->whereIn('id', $propertyIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Property $property) use ($last, $today): array {
                $at = isset($last[$property->id]) ? CarbonImmutable::parse((string) $last[$property->id])->setTimezone($today->timezone) : null;
                $days = $at === null ? null : (int) $at->startOfDay()->diffInDays($today, true);

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'lastVisit' => $at?->format('M j'),
                    'days' => $days,
                    'warn' => $days === null || $days > self::VISIT_WARN_DAYS,
                ];
            })
            ->sortByDesc(fn (array $row): int => $row['days'] ?? PHP_INT_MAX)
            ->values();

        $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY)->utc();

        return [
            'rows' => $rows->take(self::SIDE_LIMIT)->all(),
            'thisWeek' => FieldVisit::query()->where('person_id', $user->id)->where('check_in_at', '>=', $weekStart)->count(),
            'open' => FieldVisit::query()->where('person_id', $user->id)->open()->count(),
        ];
    }
}
