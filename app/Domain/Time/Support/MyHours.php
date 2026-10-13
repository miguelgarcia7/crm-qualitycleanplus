<?php

namespace App\Domain\Time\Support;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\Time\Enums\TimeEntrySource;
use App\Domain\Time\Enums\TimeEntryType;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Support\CompanyTime;
use Illuminate\Support\Collection;

/**
 * A contractor's own weeks: hours and pay per job, per pay week, as the
 * contractor sees them on QC Minute (the home page's pay card and My hours).
 *
 * Each work order is a separate job with its own rates and its own 40 hours
 * before overtime, so pay is broken down per job. Their own pay only, never
 * the bill rate, and never who approved a week (ADR-0005, contractor home).
 */
class MyHours
{
    /** Once the hotel approves a week, its hours and pay are final. */
    public const FINAL = [TimesheetStatus::Approved, TimesheetStatus::Invoiced, TimesheetStatus::InvoiceSent];

    /**
     * Weeks with hours, newest first.
     *
     * @param  string|null  $from  only weeks starting on or after this date
     * @param  int|null  $job  only this work order's hours
     * @return list<array<string, mixed>>
     */
    public function weeks(Person $person, ?string $from = null, ?int $limit = null, bool $withCurrent = true, ?int $job = null): array
    {
        $today = CompanyTime::now()->toDateString();

        $summaries = TimeSummary::query()
            ->where('time_summaries.person_id', $person->id)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'time_summaries.payroll_period_id')
            ->when($from !== null, fn ($q) => $q->whereDate('payroll_periods.week_start', '>=', $from))
            ->when(! $withCurrent, fn ($q) => $q->whereDate('payroll_periods.week_end', '<', $today))
            ->when($job !== null, fn ($q) => $q->where('time_summaries.work_order_id', $job))
            ->orderByDesc('payroll_periods.week_start')
            ->select('time_summaries.*', 'payroll_periods.week_start as period_start', 'payroll_periods.week_end as period_end')
            ->with('workOrder.position:id,name', 'workOrder.property:id,name')
            ->get();

        $weeks = $summaries->groupBy(fn (TimeSummary $s): string => substr((string) $s->getAttribute('period_start'), 0, 10));
        if ($limit !== null) {
            $weeks = $weeks->take($limit);
        }

        $periodIds = $weeks->flatten()->pluck('payroll_period_id')->unique()->values();

        $final = Timesheet::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->whereIn('status', array_map(fn (TimesheetStatus $s): string => $s->value, self::FINAL))
            ->pluck('payroll_period_id')
            ->flip();

        // Incentives and deductions belong to the person's week, not one job.
        $adjustments = $job !== null ? collect() : TimeEntryAdjustment::query()
            ->where('person_id', $person->id)
            ->whereIn('payroll_period_id', $periodIds)
            ->with('adjustmentItem:id,name', 'payrollPeriod:id,week_start')
            ->get()
            ->groupBy(fn (TimeEntryAdjustment $a): string => $a->payrollPeriod?->week_start->toDateString() ?? '');

        return $weeks->map(function (Collection $week, string $start) use ($final, $adjustments, $today): array {
            $end = substr((string) $week->first()->getAttribute('period_end'), 0, 10);
            $adjust = ($adjustments[$start] ?? collect())->map(fn (TimeEntryAdjustment $a): array => [
                'label' => $a->adjustmentItem->name ?? $a->notes ?? ($a->type->value === 'deduction' ? 'Deduction' : 'Incentive'),
                'amount' => $a->signedValue(),
            ])->values();
            $gross = (int) $week->sum('total_pay');
            // Any job's week not yet approved by its hotel can still change.
            $mayChange = $week->contains(fn (TimeSummary $s): bool => ! $final->has($s->payroll_period_id));

            return [
                'start' => $start,
                'end' => $end,
                'status' => $end >= $today ? 'in_progress' : ($mayChange ? 'waiting' : 'approved'),
                'may_change' => $mayChange,
                'minutes' => (int) $week->sum(fn (TimeSummary $s): int => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes + $s->training_minutes),
                'overtime_minutes' => (int) $week->sum('overtime_minutes'),
                'holiday' => $week->sum('holiday_minutes') > 0,
                'gross' => $gross,
                'jobs' => $week->map(fn (TimeSummary $s): array => [
                    'work_order_id' => $s->work_order_id,
                    'label' => $s->workOrder === null ? 'Job' : self::label($s->workOrder),
                    'lines' => array_values(array_filter([
                        $this->line('Regular', $s->regular_minutes, $s->workOrder?->pay_rate, $s->regular_amount_pay),
                        $this->line('Overtime', $s->overtime_minutes, $s->workOrder?->ot_pay_rate, $s->overtime_amount_pay),
                        $this->line('Holiday', $s->holiday_minutes, null, $s->holiday_amount_pay),
                        $this->line('Training', $s->training_minutes, $s->workOrder?->pay_rate, $s->training_amount_pay),
                    ])),
                    'total' => $s->total_pay,
                ])->values()->all(),
                'adjustments' => $adjust->all(),
                'after_adjustments' => $gross + (int) $adjust->sum('amount'),
            ];
        })->values()->all();
    }

    /**
     * One week's punches, day by day in each hotel's own time.
     *
     * @return list<array{date: string, punches: list<array<string, mixed>>, minutes: int}>
     */
    public function days(Person $person, string $start, string $end): array
    {
        $entries = TimeEntry::query()
            ->where('time_entries.person_id', $person->id)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'time_entries.payroll_period_id')
            ->whereDate('payroll_periods.week_start', $start)
            ->whereNotNull('time_entries.start_at_utc')
            ->orderBy('time_entries.start_at_utc')
            ->select('time_entries.*')
            ->with('workOrder.position:id,name', 'workOrder.property:id,name,timezone')
            ->get();

        $local = fn (TimeEntry $e) => $e->start_at_utc?->copy()->setTimezone($e->timezone ?? $e->workOrder->property->timezone ?? CompanyTime::zone());
        $byDay = $entries->groupBy(fn (TimeEntry $e): string => $local($e)?->toDateString() ?? '');

        $days = [];
        for ($day = $start; $day <= $end; $day = date('Y-m-d', strtotime($day.' +1 day'))) {
            $list = $byDay[$day] ?? collect();
            $days[] = [
                'date' => $day,
                'punches' => $list->map(function (TimeEntry $e) use ($local): array {
                    $tz = $local($e)?->getTimezone();

                    return [
                        'work_order_id' => $e->work_order_id,
                        'in' => $local($e)?->format('g:i a'),
                        'out' => $e->end_at_utc?->copy()->setTimezone($tz)->format('g:i a'),
                        'minutes' => $e->duration_minutes ?? ($e->start_at_utc === null ? 0 : (int) $e->start_at_utc->diffInMinutes(now(), absolute: true)),
                        'training' => $e->entry_type === TimeEntryType::Training,
                        'how' => match ($e->source) {
                            TimeEntrySource::ManualEntry, TimeEntrySource::AdjustmentCredit => 'Added by the office',
                            TimeEntrySource::Imported => 'Imported',
                            default => null,
                        },
                    ];
                })->values()->all(),
                'minutes' => (int) $list->sum(fn (TimeEntry $e): int => (int) $e->duration_minutes),
            ];
        }

        return $days;
    }

    /**
     * Every job the person has hours in, for the job filter.
     *
     * @return list<array{id: int, label: string}>
     */
    public function jobs(Person $person): array
    {
        $ids = TimeSummary::query()->where('person_id', $person->id)->distinct()->pluck('work_order_id');

        return WorkOrder::query()->whereIn('id', $ids)->with('position:id,name', 'property:id,name')
            ->orderBy('start_date')->get()
            ->map(fn (WorkOrder $wo): array => ['id' => $wo->id, 'label' => self::label($wo)])
            ->values()->all();
    }

    /** A job's label: position and hotel. */
    public static function label(WorkOrder $wo): string
    {
        return trim(($wo->position->name ?? 'Job').' · '.($wo->property->name ?? ''), ' ·');
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
}
