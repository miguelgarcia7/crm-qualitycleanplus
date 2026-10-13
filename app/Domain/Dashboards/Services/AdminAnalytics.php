<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeSummary;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The admin's Analytics view (docs/50-ui/dashboard-mockups/Admin-Analytics):
 * billed, collected, still owed and gross margin, revenue and payouts by
 * month, who owes what, each property's margin and overtime, and hours per
 * week.
 *
 * Every money figure comes from frozen invoices (ADR-0006), so the headline
 * numbers and the monthly chart always agree. Billed is the invoice total;
 * margin compares the work billed with what the contractors were paid on the
 * same invoice lines. Comparisons are like for like: "October so far" is
 * compared with the same days of September, never all of it.
 */
class AdminAnalytics
{
    public const PERIODS = ['mtd', 'last_month', 'quarter'];

    private const CHART_MONTHS = 6;

    private const CHART_WEEKS = 8;

    private const LATE_PROPERTIES = 3;

    /**
     * @return array<string, mixed>
     */
    public function build(string $period): array
    {
        $today = CompanyTime::now()->startOfDay();
        $window = $this->window(in_array($period, self::PERIODS, true) ? $period : 'mtd', $today);

        $current = $this->work($window['from'], $window['to']);
        $previous = $this->work($window['compareFrom'], $window['compareTo']);

        return [
            'period' => $window['key'],
            'periods' => [
                ['key' => 'mtd', 'label' => $today->format('F').' so far'],
                ['key' => 'last_month', 'label' => $today->subMonthNoOverflow()->format('F')],
                ['key' => 'quarter', 'label' => 'Last 3 months'],
            ],
            'periodLabel' => $window['label'],
            'headline' => $this->headline($window, $current, $previous, $today),
            'months' => $this->months($today),
            'owed' => $this->owed($today),
            'properties' => $this->properties($window),
            'weeks' => $this->weeks($today),
        ];
    }

    /**
     * The period on show and the one it is compared with.
     *
     * @return array{key: string, label: string, from: CarbonImmutable, to: CarbonImmutable, compareFrom: CarbonImmutable, compareTo: CarbonImmutable, compareLabel: string}
     */
    private function window(string $period, CarbonImmutable $today): array
    {
        $monthStart = $today->startOfMonth();

        if ($period === 'last_month') {
            $from = $monthStart->subMonthNoOverflow();
            $compareFrom = $from->subMonthNoOverflow();

            return [
                'key' => $period,
                'label' => $from->format('F'),
                'from' => $from,
                'to' => $from->endOfMonth()->startOfDay(),
                'compareFrom' => $compareFrom,
                'compareTo' => $compareFrom->endOfMonth()->startOfDay(),
                'compareLabel' => $compareFrom->format('M'),
            ];
        }

        if ($period === 'quarter') {
            $from = $monthStart->subMonthsNoOverflow(3);
            $compareFrom = $from->subMonthsNoOverflow(3);

            return [
                'key' => $period,
                'label' => $from->format('M').' – '.$monthStart->subDay()->format('M'),
                'from' => $from,
                'to' => $monthStart->subDay(),
                'compareFrom' => $compareFrom,
                'compareTo' => $from->subDay(),
                'compareLabel' => $compareFrom->format('M').' – '.$from->subDay()->format('M'),
            ];
        }

        // Month to date, against the same number of days last month.
        $compareFrom = $monthStart->subMonthNoOverflow();
        $compareTo = $compareFrom->addDays($today->day - 1)->min($compareFrom->endOfMonth()->startOfDay());

        return [
            'key' => 'mtd',
            'label' => $today->format('F').' so far',
            'from' => $monthStart,
            'to' => $today,
            'compareFrom' => $compareFrom,
            'compareTo' => $compareTo,
            'compareLabel' => 'same point in '.$compareFrom->format('M'),
        ];
    }

    /**
     * @param  array<string, mixed>  $window
     * @param  array{billed: int, bill: int, payout: int, minutes: int, overtime: int}  $current
     * @param  array{billed: int, bill: int, payout: int, minutes: int, overtime: int}  $previous
     * @return list<array<string, mixed>>
     */
    private function headline(array $window, array $current, array $previous, CarbonImmutable $today): array
    {
        $collected = (int) Invoice::query()
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$this->startUtc($window['from']), $this->endUtc($window['to'])])
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->sum('total');

        $owed = $this->unpaid();
        // Overdue by each property's own date (Invoice::scopeOverdue).
        $overdue = $owed->clone()->overdue();

        $margin = $this->margin($current);
        $previousMargin = $this->margin($previous);
        $billedChange = $previous['billed'] > 0 ? round(($current['billed'] - $previous['billed']) / $previous['billed'] * 100, 1) : null;
        $marginChange = $margin !== null && $previousMargin !== null ? round($margin - $previousMargin, 1) : null;
        $overtimeUp = $this->share($current['overtime'], $current['minutes']) > $this->share($previous['overtime'], $previous['minutes']);

        $overdueCount = $overdue->clone()->count();

        return [
            [
                'key' => 'billed',
                'label' => 'Billed',
                'value' => $this->dollars($current['billed']),
                'note' => $billedChange === null
                    ? 'Nothing billed to compare with'
                    : ($billedChange === 0.0 ? 'No change' : $this->signed($billedChange).'%').' vs '.$window['compareLabel'],
                'trend' => $this->trend($billedChange),
            ],
            [
                'key' => 'collected',
                'label' => 'Collected',
                'value' => $this->dollars($collected),
                'note' => 'Marked paid '.($window['key'] === 'mtd' ? 'this month' : 'in '.$window['label']),
                'trend' => null,
            ],
            [
                'key' => 'owed',
                'label' => 'Still owed',
                'value' => $this->dollars((int) $owed->clone()->sum('total')),
                'note' => $overdueCount > 0
                    ? '$'.number_format($this->dollars((int) $overdue->clone()->sum('total')), 2).' overdue · '.$overdueCount.' '.($overdueCount === 1 ? 'invoice' : 'invoices')
                    : 'Nothing overdue',
                'trend' => $overdueCount > 0 ? 'down' : null,
            ],
            [
                'key' => 'margin',
                'label' => 'Gross margin',
                'value' => $margin,
                'note' => $marginChange === null
                    ? 'Billed work less contractor pay'
                    : ($marginChange === 0.0 ? 'No change' : $this->signed($marginChange).' pts').' vs '.$window['compareLabel'].($marginChange < 0 && $overtimeUp ? ' · overtime up' : ''),
                'trend' => $this->trend($marginChange),
            ],
        ];
    }

    /**
     * Revenue (invoice totals) and payouts (contractor pay on those invoices)
     * by month of issue. The current month is marked as partial.
     *
     * @return list<array<string, mixed>>
     */
    private function months(CarbonImmutable $today): array
    {
        $first = $today->startOfMonth()->subMonthsNoOverflow(self::CHART_MONTHS - 1);

        $invoices = $this->billedInvoices()
            ->whereDate('issue_date', '>=', $first->toDateString())
            ->get(['id', 'issue_date', 'total']);

        $payouts = DB::table('invoice_items')
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->groupBy('invoice_id')
            ->selectRaw('invoice_id, SUM(total_payout) as payout')
            ->pluck('payout', 'invoice_id');

        $months = [];
        for ($i = 0; $i < self::CHART_MONTHS; $i++) {
            $month = $first->addMonthsNoOverflow($i);
            $months[$month->format('Y-m')] = [
                'label' => $month->format('M'),
                'revenue' => 0,
                'payouts' => 0,
                'partial' => $month->isSameMonth($today),
            ];
        }

        foreach ($invoices as $invoice) {
            $key = $invoice->issue_date->format('Y-m');
            if (isset($months[$key])) {
                $months[$key]['revenue'] += $invoice->total;
                $months[$key]['payouts'] += (int) ($payouts[$invoice->id] ?? 0);
            }
        }

        return array_values(array_map(fn (array $m): array => [
            ...$m,
            'revenue' => $this->dollars($m['revenue']),
            'payouts' => $this->dollars($m['payouts']),
        ], $months));
    }

    /**
     * Unpaid invoices by how late they are, and the properties most behind.
     *
     * @return array<string, mixed>
     */
    private function owed(CarbonImmutable $today): array
    {
        $unpaid = $this->unpaid()->with('property:id,name,timezone')->get(['id', 'property_id', 'due_date', 'total', 'paid_at', 'status']);

        $buckets = [
            'current' => ['label' => 'Not due yet', 'amount' => 0],
            'late30' => ['label' => '1–30 days late', 'amount' => 0],
            'late60' => ['label' => '31–60 days late', 'amount' => 0],
            'late90' => ['label' => 'Over 60 days', 'amount' => 0],
        ];

        foreach ($unpaid as $invoice) {
            // Days past due at the property, not by the office's clock.
            $late = $invoice->daysPastDue();
            $key = match (true) {
                $late <= 0 => 'current',
                $late <= 30 => 'late30',
                $late <= 60 => 'late60',
                default => 'late90',
            };
            $buckets[$key]['amount'] += $invoice->total;
        }

        $late = $unpaid
            ->filter(fn (Invoice $invoice): bool => $invoice->isOverdue())
            ->groupBy('property_id')
            ->map(fn (Collection $group): array => [
                'id' => (int) $group->first()->property_id,
                'name' => $group->first()->property?->name,
                'count' => $group->count(),
                'amount' => (int) $group->sum('total'),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'total' => $this->dollars((int) $unpaid->sum('total')),
            'buckets' => collect($buckets)->map(fn (array $b, string $key): array => [
                'key' => $key,
                'label' => $b['label'],
                'amount' => $this->dollars($b['amount']),
            ])->values()->all(),
            'late' => $late->take(self::LATE_PROPERTIES)->map(fn (array $row): array => [
                ...$row,
                'amount' => $this->dollars($row['amount']),
            ])->all(),
            'lateCount' => $late->count(),
        ];
    }

    /**
     * Every property billed in the period, with hours, margin and overtime
     * share. The page sorts and shows the top five.
     *
     * @param  array<string, mixed>  $window
     * @return list<array<string, mixed>>
     */
    private function properties(array $window): array
    {
        $rows = $this->work($window['from'], $window['to'], byProperty: true);
        $names = Property::withTrashed()->whereIn('id', array_keys($rows))->pluck('name', 'id');

        return collect($rows)
            ->map(fn (array $row, int $id): array => [
                'id' => $id,
                'name' => $names[$id] ?? 'Property #'.$id,
                'hours' => (int) round($row['minutes'] / 60),
                'billed' => $this->dollars($row['billed']),
                'margin' => $this->margin($row),
                'overtime' => $this->share($row['overtime'], $row['minutes']),
            ])
            ->sortByDesc('billed')
            ->values()
            ->all();
    }

    /**
     * Hours per week for the last eight weeks, overtime apart. Weeks are
     * grouped by the Monday they fall in, since each property anchors its own
     * pay week (ADR-0009).
     *
     * @return array<string, mixed>
     */
    private function weeks(CarbonImmutable $today): array
    {
        $thisWeek = $today->startOfWeek(CarbonImmutable::MONDAY);
        $first = $thisWeek->subWeeks(self::CHART_WEEKS - 1);

        $weeks = [];
        for ($i = 0; $i < self::CHART_WEEKS; $i++) {
            $week = $first->addWeeks($i);
            $weeks[$week->toDateString()] = [
                'label' => $week->format('M j'),
                'regular' => 0,
                'overtime' => 0,
                'partial' => $week->equalTo($thisWeek),
            ];
        }

        TimeSummary::query()
            // A property's pay week can start a few days before the Monday.
            ->whereDate('week_start', '>=', $first->subDays(6)->toDateString())
            ->get(['week_start', 'regular_minutes', 'overtime_minutes', 'holiday_minutes', 'training_minutes'])
            ->each(function (TimeSummary $row) use (&$weeks): void {
                $key = CarbonImmutable::parse($row->week_start->toDateString())->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
                if (isset($weeks[$key])) {
                    $weeks[$key]['regular'] += $row->regular_minutes + $row->holiday_minutes + $row->training_minutes;
                    $weeks[$key]['overtime'] += $row->overtime_minutes;
                }
            });

        $rows = array_values(array_map(fn (array $w): array => [
            ...$w,
            'regular' => (int) round($w['regular'] / 60),
            'overtime' => (int) round($w['overtime'] / 60),
            'share' => $this->share($w['overtime'], $w['regular'] + $w['overtime']),
        ], $weeks));

        // The current week is still filling in, so the comparison uses the
        // last complete one.
        $complete = array_values(array_filter($rows, fn (array $w): bool => ! $w['partial'] && $w['regular'] + $w['overtime'] > 0));

        return [
            'rows' => $rows,
            'latestShare' => $complete === [] ? null : end($complete)['share'],
            'latestLabel' => $complete === [] ? null : end($complete)['label'],
            'firstShare' => count($complete) > 1 ? $complete[0]['share'] : null,
            'firstLabel' => count($complete) > 1 ? $complete[0]['label'] : null,
        ];
    }

    /**
     * Billed totals, work billed and contractor pay for invoices issued
     * between two dates, overall or per property.
     *
     * @return ($byProperty is true ? array<int, array{billed: int, bill: int, payout: int, minutes: int, overtime: int}> : array{billed: int, bill: int, payout: int, minutes: int, overtime: int})
     */
    private function work(CarbonImmutable $from, CarbonImmutable $to, bool $byProperty = false): array
    {
        $invoices = $this->billedInvoices()
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString());

        $billed = $invoices->clone()
            ->groupBy('property_id')
            ->selectRaw('property_id, SUM(total) as billed')
            ->pluck('billed', 'property_id');

        $items = DB::table('invoice_items')
            ->joinSub($invoices->clone()->select(['id', 'property_id']), 'inv', 'inv.id', '=', 'invoice_items.invoice_id')
            ->groupBy('inv.property_id')
            ->selectRaw('inv.property_id as property_id, SUM(total_bill) as bill, SUM(total_payout) as payout,
                SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as minutes,
                SUM(overtime_minutes) as overtime')
            ->get()
            ->keyBy('property_id');

        $rows = [];
        foreach ($billed as $propertyId => $amount) {
            $item = $items->get($propertyId);
            $rows[(int) $propertyId] = [
                'billed' => (int) $amount,
                'bill' => (int) ($item->bill ?? 0),
                'payout' => (int) ($item->payout ?? 0),
                'minutes' => (int) ($item->minutes ?? 0),
                'overtime' => (int) ($item->overtime ?? 0),
            ];
        }

        if ($byProperty) {
            return $rows;
        }

        return [
            'billed' => (int) array_sum(array_column($rows, 'billed')),
            'bill' => (int) array_sum(array_column($rows, 'bill')),
            'payout' => (int) array_sum(array_column($rows, 'payout')),
            'minutes' => (int) array_sum(array_column($rows, 'minutes')),
            'overtime' => (int) array_sum(array_column($rows, 'overtime')),
        ];
    }

    /**
     * Invoices that count as billed: generated and frozen, not voided.
     *
     * @return Builder<Invoice>
     */
    private function billedInvoices(): Builder
    {
        return Invoice::query()->whereIn('status', [InvoiceStatus::Invoiced->value, InvoiceStatus::InvoiceSent->value]);
    }

    /**
     * @return Builder<Invoice>
     */
    private function unpaid(): Builder
    {
        return $this->billedInvoices()->whereNull('paid_at');
    }

    /**
     * @param  array{bill: int, payout: int}  $work
     */
    private function margin(array $work): ?float
    {
        return $work['bill'] > 0 ? round(($work['bill'] - $work['payout']) / $work['bill'] * 100, 1) : null;
    }

    private function share(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    private function trend(?float $change): ?string
    {
        return match (true) {
            $change === null, $change === 0.0 => null,
            $change > 0 => 'up',
            default => 'down',
        };
    }

    private function signed(float $value): string
    {
        return ($value > 0 ? '+' : ($value < 0 ? '−' : '')).number_format(abs($value), 1);
    }

    private function startUtc(CarbonImmutable $day): CarbonImmutable
    {
        return $day->startOfDay()->utc();
    }

    private function endUtc(CarbonImmutable $day): CarbonImmutable
    {
        return $day->endOfDay()->utc();
    }

    private function dollars(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
