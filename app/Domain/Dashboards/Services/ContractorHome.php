<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\KnowledgeBase\Models\KbArticle;
use App\Domain\People\Models\Person;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The contractor's QC Minute home: whether they're on the clock, this week's
 * hours, their pay by week, what they owe for uniforms, their jobs and details.
 *
 * Each work order is a separate job with its own rates and its own 40 hours
 * before overtime, so hours and pay are shown per job. Who approved a week is
 * never shown; a week that can still change says so.
 */
class ContractorHome
{
    private const PAY_WEEKS = 6;

    /** Once the hotel approves a week, its hours and pay are final. */
    private const FINAL = [TimesheetStatus::Approved, TimesheetStatus::Invoiced, TimesheetStatus::InvoiceSent];

    /** @return array<string, mixed> */
    public function build(Person $person): array
    {
        $now = CarbonImmutable::now(AutomationCatalog::DISPLAY_TIMEZONE);
        $jobs = WorkOrder::query()
            ->where('person_id', $person->id)
            ->where('status', WorkOrderStatus::Active->value)
            ->with('property:id,name,city,timezone,closing_day', 'position:id,name')
            ->orderBy('start_date')
            ->get();

        return [
            'greeting' => DashboardFormat::greeting($person, $now),
            'jobs' => $this->jobs($person, $jobs),
            'right_now' => $this->rightNow($person),
            'this_week' => $this->thisWeek($person, $jobs),
            'pay_weeks' => $this->payWeeks($person),
            'charges' => $this->charges($person),
            'recruiter' => $person->primaryRecruiter?->name,
            'details' => ['email' => $person->email, 'phone' => $person->phone],
            'help' => KbArticle::query()->published()->visibleTo($person)
                ->orderByDesc('is_featured')->latest('published_at')->limit(3)
                ->get(['id', 'slug', 'title'])
                ->map(fn (KbArticle $a): array => ['slug' => $a->slug, 'title' => $a->title])->all(),
        ];
    }

    /** A job's label: position, and the hotel when it tells two jobs apart. */
    private function label(WorkOrder $wo): string
    {
        return trim(($wo->position->name ?? 'Job').' · '.($wo->property->name ?? ''), ' ·');
    }

    /**
     * @param  Collection<int, WorkOrder>  $jobs
     * @return list<array<string, mixed>>
     */
    private function jobs(Person $person, Collection $jobs): array
    {
        // A pay raise opens a new work order; "since" is the first one in that job.
        $firstStart = WorkOrder::query()
            ->where('person_id', $person->id)
            ->groupBy('property_id', 'position_id')
            ->selectRaw('property_id, position_id, MIN(start_date) as first_start')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => ["{$row->property_id}:{$row->position_id}" => substr((string) $row->first_start, 0, 10)]);

        return $jobs->map(fn (WorkOrder $wo): array => [
            'id' => $wo->id,
            'label' => $this->label($wo),
            'position' => $wo->position?->name,
            'property' => $wo->property?->name,
            'city' => $wo->property?->city,
            'since' => $firstStart["{$wo->property_id}:{$wo->position_id}"] ?? $wo->start_date->toDateString(),
            // Their own pay, never the hotel's bill rate.
            'pay_rate' => $wo->pay_rate,
            'ot_pay_rate' => $wo->ot_pay_rate,
            'temporary' => $wo->is_temporary_assignment || $wo->source === WorkOrderSource::TemporaryAssignment,
        ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function rightNow(Person $person): array
    {
        $open = TimeEntry::query()
            ->where('person_id', $person->id)
            ->whereNull('end_at_utc')
            ->with('workOrder.property:id,name,timezone', 'workOrder.position:id,name')
            ->latest('start_at_utc')
            ->first();

        if ($open !== null) {
            $tz = $open->workOrder->property->timezone ?? AutomationCatalog::DISPLAY_TIMEZONE;

            return [
                'on_the_clock' => true,
                'job' => $open->workOrder === null ? null : $this->label($open->workOrder),
                'property' => $open->workOrder?->property?->name,
                'at' => $open->start_at_utc?->copy()->setTimezone($tz)->format('g:i a'),
                'minutes' => $open->start_at_utc === null ? 0 : (int) $open->start_at_utc->diffInMinutes(now(), absolute: true),
            ];
        }

        $last = TimeEntry::query()
            ->where('person_id', $person->id)
            ->whereNotNull('end_at_utc')
            ->with('workOrder.property:id,name,timezone')
            ->latest('end_at_utc')
            ->first();
        $tz = $last->workOrder->property->timezone ?? AutomationCatalog::DISPLAY_TIMEZONE;

        return [
            'on_the_clock' => false,
            'property' => $last?->workOrder?->property?->name,
            'at' => $last?->end_at_utc?->copy()->setTimezone($tz)->format('D g:i a'),
            'minutes' => 0,
        ];
    }

    /**
     * Hours per day this week, per job. The week is the first job's property
     * week; every demo and legacy property runs Monday to Sunday.
     *
     * @param  Collection<int, WorkOrder>  $jobs
     * @return array<string, mixed>
     */
    private function thisWeek(Person $person, Collection $jobs): array
    {
        $anchor = $jobs->first()?->property;
        $tz = $anchor->timezone ?? AutomationCatalog::DISPLAY_TIMEZONE;
        $start = $anchor?->weekStartFor(CarbonImmutable::now($tz)) ?? CarbonImmutable::now($tz)->startOfWeek();
        $days = collect(range(0, 6))->map(fn (int $i): string => $start->addDays($i)->toDateString());

        $entries = TimeEntry::query()
            ->where('person_id', $person->id)
            ->where('start_at_utc', '>=', $start->utc())
            ->where('start_at_utc', '<', $start->addDays(7)->utc())
            ->get(['id', 'work_order_id', 'start_at_utc', 'end_at_utc', 'duration_minutes']);

        $minutes = fn (TimeEntry $e): int => $e->duration_minutes
            ?? ($e->start_at_utc === null ? 0 : (int) $e->start_at_utc->diffInMinutes(now(), absolute: true));

        return [
            'start' => $start->toDateString(),
            'end' => $start->addDays(6)->toDateString(),
            'today' => CarbonImmutable::now($tz)->toDateString(),
            'days' => $days->map(fn (string $day): array => [
                'date' => $day,
                // Minutes per job on this day, keyed by work order id.
                'by_job' => $entries
                    ->filter(fn (TimeEntry $e): bool => $e->start_at_utc?->copy()->setTimezone($tz)->toDateString() === $day)
                    ->groupBy('work_order_id')
                    ->map(fn (Collection $list): int => $list->sum($minutes))
                    ->all(),
            ])->all(),
            'total_by_job' => $entries->groupBy('work_order_id')->map(fn (Collection $list): int => $list->sum($minutes))->all(),
        ];
    }

    /**
     * Gross pay for the last few weeks, per job, with any incentives and
     * deductions. Weeks the hotel hasn't approved yet are marked as able to change.
     *
     * @return list<array<string, mixed>>
     */
    private function payWeeks(Person $person): array
    {
        $summaries = TimeSummary::query()
            ->where('time_summaries.person_id', $person->id)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'time_summaries.payroll_period_id')
            ->whereDate('payroll_periods.week_end', '<', now()->toDateString())
            ->orderByDesc('payroll_periods.week_start')
            ->select('time_summaries.*', 'payroll_periods.week_start as period_start', 'payroll_periods.week_end as period_end')
            ->with('workOrder.position:id,name', 'workOrder.property:id,name')
            ->get();

        $weeks = $summaries->groupBy(fn (TimeSummary $s): string => substr((string) $s->getAttribute('period_start'), 0, 10))->take(self::PAY_WEEKS);
        $periodIds = $weeks->flatten()->pluck('payroll_period_id')->unique()->values();

        $final = Timesheet::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->whereIn('status', array_map(fn (TimesheetStatus $s): string => $s->value, self::FINAL))
            ->pluck('payroll_period_id')
            ->flip();

        $adjustments = TimeEntryAdjustment::query()
            ->where('person_id', $person->id)
            ->whereIn('payroll_period_id', $periodIds)
            ->with('adjustmentItem:id,name', 'payrollPeriod:id,week_start')
            ->get()
            ->groupBy(fn (TimeEntryAdjustment $a): string => $a->payrollPeriod?->week_start->toDateString() ?? '');

        return $weeks->map(function (Collection $week, string $start) use ($final, $adjustments): array {
            $jobs = $week->map(fn (TimeSummary $s): array => [
                'label' => $s->workOrder === null ? 'Job' : $this->label($s->workOrder),
                'lines' => array_values(array_filter([
                    $this->line('Regular', $s->regular_minutes, $s->workOrder?->pay_rate, $s->regular_amount_pay),
                    $this->line('Overtime', $s->overtime_minutes, $s->workOrder?->ot_pay_rate, $s->overtime_amount_pay),
                    $this->line('Holiday', $s->holiday_minutes, null, $s->holiday_amount_pay),
                    $this->line('Training', $s->training_minutes, $s->workOrder?->pay_rate, $s->training_amount_pay),
                ])),
                'total' => $s->total_pay,
            ])->values();

            $adjust = ($adjustments[$start] ?? collect())->map(fn (TimeEntryAdjustment $a): array => [
                'label' => $a->adjustmentItem->name ?? $a->notes ?? ($a->type->value === 'deduction' ? 'Deduction' : 'Incentive'),
                'amount' => $a->signedValue(),
            ])->values();

            $gross = (int) $week->sum('total_pay');

            return [
                'start' => $start,
                'end' => substr((string) $week->first()->getAttribute('period_end'), 0, 10),
                'minutes' => (int) $week->sum(fn (TimeSummary $s): int => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes + $s->training_minutes),
                'gross' => $gross,
                'jobs' => $jobs->all(),
                'adjustments' => $adjust->all(),
                'after_adjustments' => $gross + (int) $adjust->sum('amount'),
                // Any job's week not yet approved by its hotel can still change.
                'may_change' => $week->contains(fn (TimeSummary $s): bool => ! $final->has($s->payroll_period_id)),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed>|null */
    private function line(string $label, int $minutes, ?int $rate, int $amount): ?array
    {
        if ($minutes <= 0) {
            return null;
        }

        // Holiday's rate isn't stored; read it back from the frozen amount.
        $rate ??= (int) round($amount / ($minutes / 60));

        return ['label' => $label, 'minutes' => $minutes, 'rate' => $rate, 'amount' => $amount];
    }

    /**
     * Uniform and equipment charges still being paid off from each check.
     *
     * @return list<array<string, mixed>>
     */
    private function charges(Person $person): array
    {
        return ContractorChargeSchedule::query()
            ->where('person_id', $person->id)
            ->where('status', ChargeScheduleStatus::Active->value)
            ->with('entries')
            ->get()
            ->map(function (ContractorChargeSchedule $c): array {
                $paid = $c->entries->where('status', ChargeEntryStatus::Applied);

                return [
                    'reason' => $c->reason,
                    'total' => $c->total_amount,
                    'per_payment' => $c->amount_per_payment,
                    'payments' => $c->num_payments,
                    'paid_payments' => $paid->count(),
                    // As ContractorChargeSchedule::remainingAmount(): payments still scheduled.
                    'remaining' => (int) $c->entries->where('status', ChargeEntryStatus::Scheduled)->sum('amount'),
                ];
            })->values()->all();
    }
}
