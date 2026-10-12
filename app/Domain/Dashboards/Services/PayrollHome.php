<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Enums\ChargeReason;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Dashboards\Support\DashboardFormat;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The payroll dashboard (docs/50-ui/dashboard-mockups/Payroll): the pay run
 * and what it adds up to, hours worth a second look before paying, payroll's
 * own workflow tasks, overdue invoices to mark paid, and staff time off.
 *
 * Pay goes out a week in arrears, so "this pay run" is the week before last
 * (Monday to Sunday). Each property anchors its own pay week (ADR-0009), so a
 * run takes in every property's pay week that ENDED inside that span.
 */
class PayrollHome
{
    private const LIST_LIMIT = 5;

    /** Overtime past this in one week, for one placement, is worth a look. */
    private const OVERTIME_FLAG_MINUTES = 10 * 60;

    /** A single shift longer than this is usually a missed clock-out. */
    private const LONG_SHIFT_MINUTES = 12 * 60;

    /** @var list<string> */
    private const APPROVED = ['approved', 'invoiced', 'invoice_sent'];

    /**
     * @return array<string, mixed>
     */
    public function build(Person $user, ?string $week): array
    {
        $now = CarbonImmutable::now(AutomationCatalog::DISPLAY_TIMEZONE);
        $today = $now->startOfDay();

        $default = $today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(2);
        $latest = $today->startOfWeek(CarbonImmutable::MONDAY)->subWeek();
        $runStart = $this->pickWeek($week, $default, $latest);
        $runEnd = $runStart->addDays(6);

        $periods = PayrollPeriod::query()
            ->whereDate('week_end', '>=', $runStart->toDateString())
            ->whereDate('week_end', '<=', $runEnd->toDateString())
            ->get(['id', 'property_id', 'week_start', 'week_end']);

        return [
            'header' => [
                'greeting' => DashboardFormat::greeting($user, $now),
                'date' => $now->format('l, M j'),
                'properties' => Property::query()->count(),
            ],
            'actions' => [
                'upload' => $user->can('imports.upload'),
                'payouts' => $user->can('reports.payroll.view'),
            ],
            'run' => [
                'start' => $runStart->toDateString(),
                'label' => DashboardFormat::range($runStart, $runEnd),
                'isDefault' => $runStart->equalTo($default),
                'previous' => $runStart->subWeek()->toDateString(),
                'next' => $runStart->lessThan($latest) ? $runStart->addWeek()->toDateString() : null,
                ...$this->run($periods),
            ],
            'checks' => $this->checks($periods),
            'tasks' => $this->tasks($user, $now),
            'overdue' => $user->can('invoices.mark_paid') ? $this->overdue($today) : null,
            'timeOff' => $this->timeOff($runStart, $today),
        ];
    }

    /** A Monday no later than last week; anything else falls back to the default run. */
    private function pickWeek(?string $week, CarbonImmutable $default, CarbonImmutable $latest): CarbonImmutable
    {
        if ($week === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $week)) {
            return $default;
        }

        $monday = CarbonImmutable::parse($week, $default->timezone)->startOfWeek(CarbonImmutable::MONDAY);

        return $monday->greaterThan($latest) ? $default : $monday;
    }

    /**
     * Gross pay and hours, deductions and incentives, and who is not approved
     * yet, for the run's pay weeks.
     *
     * @param  Collection<int, PayrollPeriod>  $periods
     * @return array<string, mixed>
     */
    private function run(Collection $periods): array
    {
        $summaries = TimeSummary::query()
            ->whereIn('payroll_period_id', $periods->pluck('id'))
            ->get(['person_id', 'property_id', 'payroll_period_id', 'regular_minutes', 'overtime_minutes', 'holiday_minutes', 'training_minutes', 'total_pay'])
            ->filter(fn (TimeSummary $s): bool => $s->regular_minutes + $s->overtime_minutes + $s->holiday_minutes + $s->training_minutes > 0);

        $worked = $summaries->pluck('property_id')->unique();
        $approvedProperties = Timesheet::query()
            ->whereIn('payroll_period_id', $periods->pluck('id'))
            ->whereIn('status', self::APPROVED)
            ->pluck('property_id')
            ->unique();
        $pendingProperties = $worked->diff($approvedProperties);

        $minutes = fn (string $bucket): int => (int) $summaries->sum("{$bucket}_minutes");

        return [
            'gross' => round(((int) $summaries->sum('total_pay')) / 100, 2),
            'contractors' => $summaries->pluck('person_id')->unique()->count(),
            'hours' => [
                'regular' => round($minutes('regular') / 60, 2),
                'overtime' => round($minutes('overtime') / 60, 2),
                'holiday' => round($minutes('holiday') / 60, 2),
                'training' => round($minutes('training') / 60, 2),
            ],
            'adjustments' => $this->adjustments($periods),
            'propertiesWorked' => $worked->count(),
            'propertiesApproved' => $worked->intersect($approvedProperties)->count(),
            'notApproved' => [
                'properties' => $pendingProperties->count(),
                'contractors' => $summaries->whereIn('property_id', $pendingProperties)->pluck('person_id')->unique()->count(),
            ],
        ];
    }

    /**
     * Deductions by kind (hiring fee and uniform installments, everything
     * else) and incentives, each with a count and total.
     *
     * @param  Collection<int, PayrollPeriod>  $periods
     * @return array<string, mixed>
     */
    private function adjustments(Collection $periods): array
    {
        $adjustments = TimeEntryAdjustment::query()
            ->whereIn('payroll_period_id', $periods->pluck('id'))
            ->get(['id', 'type', 'value']);

        $reasons = DB::table('contractor_charge_schedule_entries')
            ->join('contractor_charge_schedules', 'contractor_charge_schedules.id', '=', 'contractor_charge_schedule_entries.schedule_id')
            ->whereIn('contractor_charge_schedule_entries.applied_adjustment_id', $adjustments->pluck('id'))
            ->pluck('contractor_charge_schedules.reason', 'contractor_charge_schedule_entries.applied_adjustment_id');

        $kind = function (TimeEntryAdjustment $a) use ($reasons): string {
            if ($a->type === AdjustmentType::Incentive) {
                return 'incentives';
            }

            return match ($reasons[$a->id] ?? null) {
                ChargeReason::HiringFee->value => 'hiringFee',
                ChargeReason::Uniform->value => 'uniform',
                default => 'other',
            };
        };

        $labels = [
            'hiringFee' => 'Hiring fee installments',
            'uniform' => 'Uniform charges',
            'other' => 'Other deductions',
            'incentives' => 'Incentives',
        ];

        $grouped = $adjustments->groupBy($kind);

        $lines = collect($labels)
            ->map(function (string $label, string $key) use ($grouped): array {
                $group = $grouped->get($key, collect());
                $cents = (int) $group->sum('value');

                return [
                    'key' => $key,
                    'label' => $label,
                    'count' => $group->count(),
                    'amount' => round(($key === 'incentives' ? $cents : -$cents) / 100, 2),
                ];
            })
            ->filter(fn (array $line): bool => $line['count'] > 0)
            ->values();

        return [
            'net' => round((float) $lines->sum('amount'), 2),
            'lines' => $lines->all(),
        ];
    }

    /**
     * Hours that look unusual this run: big overtime, very long shifts,
     * punches edited by hand, and first weeks. Biggest pay first within
     * each kind.
     *
     * @param  Collection<int, PayrollPeriod>  $periods
     * @return array<string, mixed>
     */
    private function checks(Collection $periods): array
    {
        $periodIds = $periods->pluck('id');
        $weekOf = $periods->mapWithKeys(fn (PayrollPeriod $p): array => [$p->id => $p->week_start->toDateString()]);

        $summaries = TimeSummary::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->get(['work_order_id', 'person_id', 'property_id', 'payroll_period_id', 'overtime_minutes', 'total_pay'])
            ->keyBy(fn (TimeSummary $s): string => $s->work_order_id.'-'.$s->payroll_period_id);

        $rows = collect();

        foreach ($summaries as $summary) {
            if ($summary->overtime_minutes >= self::OVERTIME_FLAG_MINUTES) {
                $rows->push($this->check($summary, 1, 'overtime', round($summary->overtime_minutes / 60, 1).' h overtime', 'warning'));
            }
        }

        $entries = TimeEntry::query()
            ->whereIn('payroll_period_id', $periodIds)
            ->where(fn ($q) => $q->where('duration_minutes', '>', self::LONG_SHIFT_MINUTES)->orWhere('was_updated', true))
            ->get(['id', 'work_order_id', 'payroll_period_id', 'start_at_utc', 'duration_minutes', 'was_updated', 'timezone']);

        foreach ($entries->groupBy(fn (TimeEntry $e): string => $e->work_order_id.'-'.$e->payroll_period_id) as $key => $group) {
            $summary = $summaries->get($key);
            if ($summary === null) {
                continue;
            }

            $long = $group->firstWhere(fn (TimeEntry $e): bool => (int) $e->duration_minutes > self::LONG_SHIFT_MINUTES);
            if ($long !== null) {
                $day = $long->start_at_utc?->setTimezone($long->timezone ?? AutomationCatalog::DISPLAY_TIMEZONE)->format('M j');
                $rows->push($this->check($summary, 2, 'long', round(((int) $long->duration_minutes) / 60, 1).' h shift'.($day ? " on {$day}" : ''), 'warning'));
            }

            $edited = $group->where('was_updated', true)->count();
            if ($edited > 0) {
                $rows->push($this->check($summary, 3, 'edited', $edited === 1 ? 'Punch edited by hand' : "{$edited} punches edited by hand", 'info'));
            }
        }

        // A placement with no hours before this run is in its first week.
        $earlier = TimeSummary::query()
            ->whereIn('work_order_id', $summaries->pluck('work_order_id')->unique())
            ->whereNotIn('payroll_period_id', $periodIds)
            ->where('week_start', '<', $periods->min('week_start') ?? now())
            ->distinct()
            ->pluck('work_order_id')
            ->all();

        foreach ($summaries as $summary) {
            if (! in_array($summary->work_order_id, $earlier, true)) {
                $rows->push($this->check($summary, 4, 'first', 'First week', 'gray'));
            }
        }

        $sorted = $rows->sortBy([['rank', 'asc'], ['pay', 'desc']])->values();
        $shown = $sorted->take(self::LIST_LIMIT);

        $names = Person::query()->whereIn('id', $shown->pluck('personId'))->pluck('name', 'id');
        $properties = Property::withTrashed()->whereIn('id', $shown->pluck('propertyId'))->pluck('name', 'id');

        return [
            'rows' => $shown->map(fn (array $row): array => [
                'key' => $row['key'],
                'contractor' => $names[$row['personId']] ?? null,
                'personId' => $row['personId'],
                'property' => $properties[$row['propertyId']] ?? null,
                'what' => $row['what'],
                'tone' => $row['tone'],
                'pay' => round($row['pay'] / 100, 2),
                'href' => "/admin/properties/{$row['propertyId']}/grid?week=".($weekOf[$row['periodId']] ?? ''),
            ])->all(),
            'total' => $sorted->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function check(TimeSummary $summary, int $rank, string $kind, string $what, string $tone): array
    {
        return [
            'key' => "{$kind}-{$summary->work_order_id}-{$summary->payroll_period_id}",
            'rank' => $rank,
            'personId' => $summary->person_id,
            'propertyId' => $summary->property_id,
            'periodId' => $summary->payroll_period_id,
            'what' => $what,
            'tone' => $tone,
            'pay' => (int) $summary->total_pay,
        ];
    }

    /**
     * Payroll's open workflow steps (final paychecks and the like), oldest
     * first.
     *
     * @return array<string, mixed>
     */
    private function tasks(Person $user, CarbonImmutable $now): array
    {
        $steps = WorkflowStep::query()->openForPerson($user);

        return [
            'rows' => $steps->clone()
                ->with(['workflow.subject', 'workflow.initiator:id,name'])
                ->oldest('id')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(function (WorkflowStep $step) use ($now): array {
                    $workflow = $step->workflow;
                    $type = WorkflowType::tryFrom((string) $workflow->type);
                    $subject = $workflow->subject instanceof Person ? $workflow->subject->name : null;

                    return [
                        'id' => $step->id,
                        'title' => $step->name.($subject !== null ? " · {$subject}" : ''),
                        'detail' => collect([
                            $type?->label(),
                            $workflow->initiator?->name !== null ? 'raised by '.$workflow->initiator->name : null,
                        ])->filter()->implode(' · '),
                        'age' => $step->created_at !== null ? DashboardFormat::age($step->created_at->toImmutable(), $now) : null,
                        'href' => $type === WorkflowType::Termination ? "/admin/terminations/{$workflow->id}" : '/admin/tasks',
                    ];
                })
                ->all(),
            'total' => $steps->clone()->count(),
        ];
    }

    /**
     * Unpaid invoices past their due date, oldest due first.
     *
     * @return array<string, mixed>
     */
    private function overdue(CarbonImmutable $today): array
    {
        $invoices = Invoice::query()
            ->whereIn('status', ['invoiced', 'invoice_sent'])
            ->whereNull('paid_at')
            ->whereDate('due_date', '<', $today->toDateString())
            ->with('property:id,name')
            ->orderBy('due_date');

        return [
            'rows' => $invoices->clone()->limit(self::LIST_LIMIT)->get()->map(function (Invoice $invoice) use ($today): array {
                $late = (int) CarbonImmutable::parse($invoice->due_date->toDateString())->diffInDays(CarbonImmutable::parse($today->toDateString()), true);

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->invoice_number,
                    'property' => $invoice->property?->name,
                    'total' => round($invoice->total / 100, 2),
                    'daysLate' => $late,
                    'href' => "/admin/invoices/{$invoice->id}",
                ];
            })->all(),
            'total' => $invoices->clone()->count(),
            'amount' => round(((int) $invoices->clone()->sum('total')) / 100, 2),
        ];
    }

    /**
     * Approved staff time off from the start of this run through the next
     * month, flagged by whether it falls in this run, the next one, or later.
     *
     * @return array<string, mixed>
     */
    private function timeOff(CarbonImmutable $runStart, CarbonImmutable $today): array
    {
        $nextRunStart = $runStart->addWeek();

        $requests = PtoRequest::query()
            ->where('status', PtoRequestStatus::Approved->value)
            ->whereDate('end_date', '>=', $runStart->toDateString())
            ->whereDate('start_date', '<=', $today->addDays(30)->toDateString())
            ->with('person:id,name')
            ->orderBy('start_date')
            ->get();

        return [
            'rows' => $requests->take(self::LIST_LIMIT)->map(fn (PtoRequest $request): array => [
                'id' => $request->id,
                'name' => $request->person?->name,
                'detail' => DashboardFormat::range($request->start_date, $request->end_date).' · '.rtrim(rtrim((string) $request->hours, '0'), '.').' h '.strtolower($request->bucket->label()),
                'when' => match (true) {
                    $request->start_date->toDateString() <= $runStart->addDays(6)->toDateString() => 'this run',
                    $request->start_date->toDateString() <= $nextRunStart->addDays(6)->toDateString() => 'next run',
                    default => 'upcoming',
                },
            ])->all(),
            'total' => $requests->count(),
        ];
    }
}
