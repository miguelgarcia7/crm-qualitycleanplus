<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Billing\Support\TimesheetWeekTotals;
use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Support\ScheduleCalendar;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Support\DirectHireProgress;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The property manager's QC Minute landing page: the week waiting on them,
 * this week so far, what they owe, what they asked for, who is close to direct
 * hire, and hours per week.
 *
 * Bill side only. A property manager never sees what a contractor is paid.
 */
class PropertyManagerHome
{
    private const LIST_LIMIT = 4;

    private const WEEKS = 8;

    public function __construct(
        private readonly DirectHireProgress $directHire,
        private readonly ScheduleCalendar $schedules,
    ) {}

    /** @return array<string, mixed> */
    public function build(Person $user): array
    {
        $now = CompanyTime::now();
        $properties = Property::query()->assignedTo($user)->orderBy('name')->get();
        $ids = $properties->pluck('id')->all();

        return [
            'greeting' => DashboardFormat::greeting($user, $now),
            'scope' => $properties->count() === 1
                ? $properties->first()->name
                : DashboardFormat::plural($properties->count(), 'property', 'properties'),
            'today' => $now->format('l, F j'),
            'many_properties' => $properties->count() > 1,
            'waiting' => $this->waiting($ids, $now),
            'next_up' => $this->nextUp($properties),
            'this_week' => $this->thisWeek($properties),
            'invoices' => $this->invoices($ids),
            'requests' => $this->requests($user, $ids),
            'direct_hire' => $this->directHire($ids),
            'hours' => $this->hours($properties),
            // This week and the next two, per hotel: is the schedule up yet?
            'schedules' => $user->can('schedules.upload')
                ? $properties->map(fn (Property $p): array => ['id' => $p->id, 'name' => $p->name, 'weeks' => $this->schedules->upcoming($p)])->values()->all()
                : null,
        ];
    }

    /**
     * Weeks sent to the PM and not yet decided, the longest waiting first.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function waiting(array $ids, CarbonImmutable $now): array
    {
        $timesheets = Timesheet::query()
            ->whereIn('property_id', $ids)
            ->where('status', TimesheetStatus::PendingApproval->value)
            ->with('property:id,name', 'payrollPeriod:id,week_start,week_end', 'sentForApprovalBy:id,name')
            ->orderBy('sent_for_approval_at')
            ->get();

        $totals = TimesheetWeekTotals::forPeriods($timesheets->pluck('payroll_period_id'));

        return $timesheets->map(function (Timesheet $t) use ($totals, $now): array {
            $sum = $totals->get($t->payroll_period_id);

            return [
                'id' => $t->id,
                'property' => $t->property?->name,
                'week' => DashboardFormat::range($t->payrollPeriod->week_start, $t->payrollPeriod->week_end),
                'sent_at' => CompanyTime::of($t->sent_for_approval_at)?->format('M j'),
                'sent_by' => $t->sentForApprovalBy?->name,
                'waiting' => $t->sent_for_approval_at === null ? null : DashboardFormat::age($t->sent_for_approval_at->toImmutable(), $now),
                'people' => $sum['people'] ?? 0,
                'total_minutes' => $sum['total_minutes'] ?? 0,
                'overtime_minutes' => $sum['overtime_minutes'] ?? 0,
                'amount' => $sum['amount'] ?? 0,
            ];
        })->values()->all();
    }

    /**
     * Closed weeks still with the recruiter (not sent yet, or declined and
     * being fixed), so the PM knows what comes next.
     *
     * @param  Collection<int, Property>  $properties
     * @return list<array<string, mixed>>
     */
    private function nextUp(Collection $properties): array
    {
        $recruiters = Person::query()
            ->join('property_assignments', 'property_assignments.person_id', '=', 'people.id')
            ->whereIn('property_assignments.property_id', $properties->pluck('id'))
            ->where('property_assignments.role', PropertyAssignmentRole::Recruiter->value)
            ->pluck('people.name', 'property_assignments.property_id');

        return $properties->map(function (Property $property) use ($recruiters): ?array {
            $today = CarbonImmutable::now($property->timezone)->toDateString();
            $timesheet = Timesheet::query()
                ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
                ->where('timesheets.property_id', $property->id)
                ->whereIn('timesheets.status', [TimesheetStatus::Draft->value, TimesheetStatus::Declined->value])
                ->whereDate('payroll_periods.week_end', '<', $today)
                ->with('payrollPeriod:id,week_start,week_end')
                ->orderBy('payroll_periods.week_start')
                ->select('timesheets.*')
                ->first();

            return $timesheet === null ? null : [
                'property' => $property->name,
                'week' => DashboardFormat::range($timesheet->payrollPeriod->week_start, $timesheet->payrollPeriod->week_end),
                'declined' => $timesheet->status === TimesheetStatus::Declined,
                'recruiter' => $recruiters[$property->id] ?? null,
            ];
        })->filter()->values()->all();
    }

    /**
     * The week in progress across the PM's properties: hours so far and who is
     * on the clock right now.
     *
     * @param  Collection<int, Property>  $properties
     * @return array<string, mixed>
     */
    private function thisWeek(Collection $properties): array
    {
        $periods = $properties->mapWithKeys(function (Property $p): array {
            $start = $p->weekStartFor(CarbonImmutable::now($p->timezone));

            return [$p->id => [
                'start' => $start,
                'period' => PayrollPeriod::query()->where('property_id', $p->id)->whereDate('week_start', $start->toDateString())->first(),
            ]];
        });
        $periodIds = $periods->pluck('period.id')->filter()->values();
        $tz = $properties->pluck('timezone', 'id');

        $first = $periods->first();

        return [
            'week' => $first === null ? null : DashboardFormat::range($first['start'], $first['start']->addDays(6)),
            'total_minutes' => (int) TimeSummary::query()->whereIn('payroll_period_id', $periodIds)
                ->selectRaw('SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as minutes')
                ->value('minutes'),
            'on_the_clock' => TimeEntry::query()
                ->whereIn('payroll_period_id', $periodIds)
                ->whereNotNull('start_at_utc')->whereNull('end_at_utc')
                ->with('workOrder:id,person_id,property_id', 'workOrder.person:id,name', 'workOrder.property:id,name')
                ->orderBy('start_at_utc')
                ->get()
                ->map(fn (TimeEntry $e): array => [
                    'name' => $e->workOrder?->person?->name,
                    'property' => $e->workOrder?->property?->name,
                    'since' => $e->start_at_utc?->copy()->setTimezone($tz[$e->workOrder?->property_id] ?? CompanyTime::zone())->format('g:i a'),
                ])
                ->values()->all(),
            'href' => $properties->count() === 1 ? "/timesheets/this-week?property={$properties->first()->id}" : '/timesheets',
        ];
    }

    /**
     * What the PM owes: open and overdue totals, and the open invoices due soonest.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function invoices(array $ids): array
    {
        $open = fn () => Invoice::query()
            ->whereIn('property_id', $ids)
            ->whereNull('paid_at')
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Voided->value]);
        // Overdue by each property's own date (Invoice::scopeOverdue).
        $overdue = fn () => Invoice::query()->whereIn('property_id', $ids)->overdue();

        return [
            'open_total' => (int) $open()->sum('total'),
            'open_count' => $open()->count(),
            'overdue_total' => (int) $overdue()->sum('total'),
            'overdue_count' => $overdue()->count(),
            'rows' => $open()
                ->with('payrollPeriod:id,week_start,week_end', 'property:id,timezone')
                ->orderBy('due_date')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (Invoice $i): array => [
                    'id' => $i->id,
                    'number' => $i->invoice_number,
                    'week' => $i->payrollPeriod === null ? null : DashboardFormat::range($i->payrollPeriod->week_start, $i->payrollPeriod->week_end),
                    'due' => $i->due_date->format('M j'),
                    'days_overdue' => $i->daysOverdue(),
                    'total' => $i->total,
                ])->all(),
        ];
    }

    /**
     * Open staffing requests at the PM's properties and the pay increases they asked for.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function requests(Person $user, array $ids): array
    {
        $staffing = MoreStaffRequest::query()
            ->whereIn('property_id', $ids)
            ->whereIn('status', [MoreStaffStatus::Submitted->value, MoreStaffStatus::InProgress->value])
            ->with('position:id,name', 'property:id,name', 'assignedRecruiter:id,name')
            ->orderBy('by_date')
            ->get()
            ->map(fn (MoreStaffRequest $r): array => [
                'kind' => 'staffing',
                'title' => "{$r->quantity_requested} × {$r->position?->name}",
                'property' => $r->property?->name,
                'needed_by' => $r->by_date->format('M j'),
                'asked' => CompanyTime::of($r->created_at)?->format('M j'),
                'progress' => "{$r->quantity_fulfilled} of {$r->quantity_requested} placed",
                'overdue' => $r->isOverdue(),
                'with' => $r->assignedRecruiter?->name,
            ]);

        $payIncreases = Workflow::query()
            ->where('type', WorkflowType::PayIncrease->value)
            ->where('initiator_id', $user->id)
            ->whereIn('status', [WorkflowStatus::Pending->value, WorkflowStatus::InProgress->value])
            ->latest('id')
            ->get();
        $workOrders = WorkOrder::query()
            ->whereIn('id', $payIncreases->map(fn (Workflow $w) => $w->data['work_order_id'] ?? null)->filter())
            ->with('person:id,name', 'property:id,name')
            ->get()
            ->keyBy('id');

        $increases = $payIncreases->map(function (Workflow $w) use ($workOrders): array {
            $wo = $workOrders->get($w->data['work_order_id'] ?? 0);
            $cents = (int) ($w->data['pm_requested_increase_cents'] ?? 0);

            return [
                'kind' => 'pay_increase',
                'title' => ($wo->person->name ?? 'A contractor').' · +$'.number_format($cents / 100, 2).'/h',
                'property' => $wo?->property?->name,
                'needed_by' => null,
                'asked' => CompanyTime::of($w->created_at)?->format('M j'),
                'progress' => 'With your recruiter',
                'overdue' => false,
                'with' => null,
            ];
        });

        return $staffing->concat($increases)->take(self::LIST_LIMIT)->values()->all();
    }

    /**
     * Contractors with the most hours toward direct hire, as on the Contractors page.
     *
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function directHire(array $ids): array
    {
        $workOrders = WorkOrder::query()
            ->whereIn('property_id', $ids)
            ->where('status', WorkOrderStatus::Active->value)
            ->with('person:id,name', 'position:id,name', 'property:id,name')
            ->get();
        $progress = $this->directHire->forMany($workOrders);

        $rows = $workOrders
            ->reject(fn (WorkOrder $wo): bool => (bool) ($progress[$wo->id]['unrestricted'] ?? true))
            ->sortByDesc(fn (WorkOrder $wo): float => (float) $progress[$wo->id]['worked_hours'])
            ->take(3)
            ->map(fn (WorkOrder $wo): array => [
                'name' => $wo->person?->name,
                'position' => $wo->position?->name,
                'property' => $wo->property?->name,
                'worked' => $progress[$wo->id]['worked_hours'],
                'threshold' => $progress[$wo->id]['threshold_hours'],
                'remaining' => $progress[$wo->id]['remaining_hours'],
                'percent' => $progress[$wo->id]['percent'],
                'eligible' => $progress[$wo->id]['eligible'],
            ])
            ->values()->all();

        return ['rows' => $rows, 'contractors' => $workOrders->pluck('person_id')->unique()->count()];
    }

    /**
     * Hours per week for the last eight weeks, with overtime apart. The week in
     * progress is flagged so the page can show it as partial.
     *
     * @param  Collection<int, Property>  $properties
     * @return list<array<string, mixed>>
     */
    private function hours(Collection $properties): array
    {
        $first = $properties->first();
        if ($first === null) {
            return [];
        }

        $current = $first->weekStartFor(CarbonImmutable::now($first->timezone));
        $from = $current->subWeeks(self::WEEKS - 1);

        $sums = TimeSummary::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'time_summaries.payroll_period_id')
            ->whereIn('payroll_periods.property_id', $properties->pluck('id'))
            ->whereDate('payroll_periods.week_start', '>=', $from->toDateString())
            ->groupBy('payroll_periods.week_start')
            ->selectRaw('payroll_periods.week_start as week_start')
            ->selectRaw('SUM(regular_minutes + holiday_minutes + training_minutes) as straight')
            ->selectRaw('SUM(overtime_minutes) as overtime')
            ->toBase()
            ->get()
            ->keyBy(fn (object $row): string => substr((string) $row->week_start, 0, 10));

        return collect(range(0, self::WEEKS - 1))->map(function (int $i) use ($from, $current, $sums): array {
            $start = $from->addWeeks($i);
            $row = $sums->get($start->toDateString());

            return [
                'label' => $start->format('M j'),
                'straight_minutes' => (int) ($row->straight ?? 0),
                'overtime_minutes' => (int) ($row->overtime ?? 0),
                'current' => $start->equalTo($current),
            ];
        })->all();
    }
}
