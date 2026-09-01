<?php

namespace App\Http\Controllers;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Reports\Exports\ArrayReportExport;
use App\Domain\Reports\Models\ReportMonthlyRevenue;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Report catalog + the standard reports (Phase 09, ADR-0028). Financial reads
 * report_monthly_revenue / report_weekly_rollups; payroll reads time_summaries
 * (already materialized per ADR-0008). Each report is gated by its permission
 * at the route layer; the catalog only lists what the viewer may open. Excel
 * exports reuse the page's data builder, so the download always matches the
 * screen.
 */
class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Person $user */
        $user = $request->user();

        abort_unless($user->canAny([
            'reports.financial.view', 'reports.operational.view', 'reports.payroll.view',
        ]), 403);

        $catalog = [
            [
                'group' => 'Financial',
                'permission' => 'reports.financial.view',
                'reports' => [
                    ['href' => '/admin/reports/revenue', 'icon' => 'report-money', 'title' => 'Revenue by property', 'description' => 'Invoiced totals by property and month — work, adjustments, tax, payouts.'],
                    ['href' => '/admin/reports/income-vs-payouts', 'icon' => 'scale', 'title' => 'Gross income vs payouts', 'description' => 'Billed vs paid with margin, by property over a date range.'],
                ],
            ],
            [
                'group' => 'Operational',
                'permission' => 'reports.operational.view',
                'reports' => [
                    ['href' => '/admin/reports/hours-by-position', 'icon' => 'clock', 'title' => 'Hours by position', 'description' => 'Regular, overtime and training hours per position over a date range.'],
                ],
            ],
            [
                'group' => 'Payroll',
                'permission' => 'reports.payroll.view',
                'reports' => [
                    ['href' => '/admin/reports/payouts', 'icon' => 'cash', 'title' => 'Payouts by contractor', 'description' => 'Hours and pay per contractor for a payroll week.'],
                ],
            ],
        ];

        return Inertia::render('admin/reports/index', [
            'groups' => collect($catalog)
                ->filter(fn (array $group): bool => $user->can($group['permission']))
                ->map(fn (array $group): array => ['group' => $group['group'], 'reports' => $group['reports']])
                ->values()
                ->all(),
        ]);
    }

    /** Revenue by property by month — billed truth from report_monthly_revenue. */
    public function revenue(Request $request): Response
    {
        return Inertia::render('admin/reports/revenue', $this->revenueData($request) + [
            'properties' => $this->propertyOptions(),
        ]);
    }

    public function revenueExport(Request $request): BinaryFileResponse
    {
        $data = $this->revenueData($request);

        return Excel::download(new ArrayReportExport(
            ['Month', 'Property', 'Invoices', 'Work ($)', 'Adjustments ($)', 'Tax ($)', 'Invoiced Total ($)', 'Payouts ($)'],
            collect($data['rows'])->map(fn (array $row): array => [
                $row['month'], $row['property'], $row['invoice_count'],
                $row['work_subtotal'] / 100, $row['adjustment_total'] / 100, $row['tax_amount'] / 100,
                $row['invoiced_total'] / 100, $row['payout_total'] / 100,
            ])->all(),
        ), "revenue-by-property-{$data['filters']['from']}-{$data['filters']['to']}.xlsx");
    }

    /** Gross income vs payouts (margin) by property — from report_weekly_rollups. */
    public function incomeVsPayouts(Request $request): Response
    {
        return Inertia::render('admin/reports/income-vs-payouts', $this->incomeVsPayoutsData($request) + [
            'properties' => $this->propertyOptions(),
        ]);
    }

    public function incomeVsPayoutsExport(Request $request): BinaryFileResponse
    {
        $data = $this->incomeVsPayoutsData($request);

        return Excel::download(new ArrayReportExport(
            ['Property', 'Hours', 'Billed ($)', 'Paid Out ($)', 'Margin ($)'],
            collect($data['rows'])->map(fn (array $row): array => [
                $row['property'], round($row['total_minutes'] / 60, 1),
                $row['total_bill'] / 100, $row['total_pay'] / 100, $row['margin'] / 100,
            ])->all(),
        ), "income-vs-payouts-{$data['filters']['from']}-{$data['filters']['to']}.xlsx");
    }

    /** Hours by position — from report_weekly_rollups. */
    public function hoursByPosition(Request $request): Response
    {
        return Inertia::render('admin/reports/hours-by-position', $this->hoursByPositionData($request) + [
            'properties' => $this->propertyOptions(),
        ]);
    }

    public function hoursByPositionExport(Request $request): BinaryFileResponse
    {
        $data = $this->hoursByPositionData($request);

        return Excel::download(new ArrayReportExport(
            ['Position', 'Regular (h)', 'Overtime (h)', 'Training (h)', 'Total (h)', 'Weeks'],
            collect($data['rows'])->map(fn (array $row): array => [
                $row['position'], round($row['regular_minutes'] / 60, 1), round($row['overtime_minutes'] / 60, 1),
                round($row['training_minutes'] / 60, 1), round($row['total_minutes'] / 60, 1), $row['weeks'],
            ])->all(),
        ), "hours-by-position-{$data['filters']['from']}-{$data['filters']['to']}.xlsx");
    }

    /** Payouts by contractor for one payroll week — from time_summaries. */
    public function payouts(Request $request): Response
    {
        return Inertia::render('admin/reports/payouts', $this->payoutsData($request) + [
            'properties' => $this->propertyOptions(),
        ]);
    }

    public function payoutsExport(Request $request): BinaryFileResponse
    {
        $data = $this->payoutsData($request);

        return Excel::download(new ArrayReportExport(
            ['Contractor', 'Property', 'Position', 'Job Code', 'Regular (h)', 'Overtime (h)', 'Training (h)', 'Total Pay ($)'],
            collect($data['rows'])->map(fn (array $row): array => [
                $row['contractor'], $row['property'], $row['position'], $row['job_code'] ?? '',
                round($row['regular_minutes'] / 60, 1), round($row['overtime_minutes'] / 60, 1),
                round($row['training_minutes'] / 60, 1), $row['total_pay'] / 100,
            ])->all(),
        ), 'payouts-'.($data['filters']['week'] ?? 'none').'.xlsx');
    }

    public function payoutsPdf(Request $request): HttpResponse
    {
        $data = $this->payoutsData($request);

        return Pdf::loadView('pdf.payouts', [
            'week' => $data['filters']['week'],
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'generatedAt' => now(),
        ])->download('payouts-'.($data['filters']['week'] ?? 'none').'.pdf');
    }

    /**
     * @return array{filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    private function revenueData(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m'],
            'to' => ['nullable', 'date_format:Y-m'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $from = CarbonImmutable::parse(($validated['from'] ?? now()->subMonths(11)->format('Y-m')).'-01');
        $to = CarbonImmutable::parse(($validated['to'] ?? now()->format('Y-m')).'-01')->endOfMonth();

        $cells = ReportMonthlyRevenue::query()
            ->with('property:id,name')
            ->whereDate('month_start', '>=', $from->toDateString())
            ->whereDate('month_start', '<=', $to->toDateString())
            ->when($validated['property_id'] ?? null, fn ($q, $id) => $q->where('property_id', $id))
            ->orderByDesc('month_start')
            ->orderBy('property_id')
            ->get();

        return [
            'filters' => [
                'from' => $from->format('Y-m'),
                'to' => $to->format('Y-m'),
                'property_id' => $validated['property_id'] ?? null,
            ],
            'rows' => $cells->map(fn (ReportMonthlyRevenue $cell): array => [
                'month' => $cell->month_start->format('M Y'),
                'property' => $cell->property->name ?? '—',
                'invoice_count' => $cell->invoice_count,
                'work_subtotal' => $cell->work_subtotal,
                'adjustment_total' => $cell->adjustment_total,
                'tax_amount' => $cell->tax_amount,
                'invoiced_total' => $cell->invoiced_total,
                'payout_total' => $cell->payout_total,
            ])->all(),
            'totals' => [
                'invoice_count' => (int) $cells->sum('invoice_count'),
                'work_subtotal' => (int) $cells->sum('work_subtotal'),
                'adjustment_total' => (int) $cells->sum('adjustment_total'),
                'tax_amount' => (int) $cells->sum('tax_amount'),
                'invoiced_total' => (int) $cells->sum('invoiced_total'),
                'payout_total' => (int) $cells->sum('payout_total'),
            ],
        ];
    }

    /**
     * @return array{filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    private function incomeVsPayoutsData(Request $request): array
    {
        [$from, $to, $propertyId] = $this->weekRangeFilters($request);

        $rows = DB::table('report_weekly_rollups')
            ->join('properties', 'properties.id', '=', 'report_weekly_rollups.property_id')
            ->whereDate('week_start', '>=', $from->toDateString())
            ->whereDate('week_start', '<=', $to->toDateString())
            ->when($propertyId, fn ($q, $id) => $q->where('report_weekly_rollups.property_id', $id))
            ->groupBy('report_weekly_rollups.property_id', 'properties.name')
            ->select([
                'properties.name as property',
                DB::raw('SUM(total_minutes) as total_minutes'),
                DB::raw('SUM(total_bill) as total_bill'),
                DB::raw('SUM(total_pay) as total_pay'),
            ])
            ->orderBy('properties.name')
            ->get()
            ->map(fn (object $row): array => [
                'property' => (string) $row->property,
                'total_minutes' => (int) $row->total_minutes,
                'total_bill' => (int) $row->total_bill,
                'total_pay' => (int) $row->total_pay,
                'margin' => (int) $row->total_bill - (int) $row->total_pay,
            ]);

        return [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'property_id' => $propertyId],
            'rows' => $rows->all(),
            'totals' => [
                'total_minutes' => (int) $rows->sum('total_minutes'),
                'total_bill' => (int) $rows->sum('total_bill'),
                'total_pay' => (int) $rows->sum('total_pay'),
                'margin' => (int) $rows->sum('margin'),
            ],
        ];
    }

    /**
     * @return array{filters: array<string, mixed>, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    private function hoursByPositionData(Request $request): array
    {
        [$from, $to, $propertyId] = $this->weekRangeFilters($request);

        $rows = DB::table('report_weekly_rollups')
            ->join('positions', 'positions.id', '=', 'report_weekly_rollups.position_id')
            ->whereDate('week_start', '>=', $from->toDateString())
            ->whereDate('week_start', '<=', $to->toDateString())
            ->when($propertyId, fn ($q, $id) => $q->where('report_weekly_rollups.property_id', $id))
            ->groupBy('report_weekly_rollups.position_id', 'positions.name')
            ->select([
                'positions.name as position',
                DB::raw('SUM(regular_minutes) as regular_minutes'),
                DB::raw('SUM(overtime_minutes) as overtime_minutes'),
                DB::raw('SUM(training_minutes) as training_minutes'),
                DB::raw('SUM(total_minutes) as total_minutes'),
                DB::raw('COUNT(DISTINCT week_start) as weeks'),
            ])
            ->orderBy('positions.name')
            ->get()
            ->map(fn (object $row): array => [
                'position' => (string) $row->position,
                'regular_minutes' => (int) $row->regular_minutes,
                'overtime_minutes' => (int) $row->overtime_minutes,
                'training_minutes' => (int) $row->training_minutes,
                'total_minutes' => (int) $row->total_minutes,
                'weeks' => (int) $row->weeks,
            ]);

        return [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'property_id' => $propertyId],
            'rows' => $rows->all(),
            'totals' => [
                'regular_minutes' => (int) $rows->sum('regular_minutes'),
                'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
                'training_minutes' => (int) $rows->sum('training_minutes'),
                'total_minutes' => (int) $rows->sum('total_minutes'),
            ],
        ];
    }

    /**
     * @return array{filters: array<string, mixed>, weeks: list<string>, rows: list<array<string, mixed>>, totals: array<string, int>}
     */
    private function payoutsData(Request $request): array
    {
        $validated = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        $weeks = DB::table('time_summaries')
            ->select('week_start')
            ->distinct()
            ->orderByDesc('week_start')
            ->limit(26)
            ->pluck('week_start')
            ->map(fn ($week): string => CarbonImmutable::parse((string) $week)->toDateString());

        $week = $validated['week'] ?? $weeks->first();
        $propertyId = isset($validated['property_id']) ? (int) $validated['property_id'] : null;

        $rows = collect();
        if ($week !== null) {
            $rows = DB::table('time_summaries')
                ->join('people', 'people.id', '=', 'time_summaries.person_id')
                ->join('properties', 'properties.id', '=', 'time_summaries.property_id')
                ->join('work_orders', 'work_orders.id', '=', 'time_summaries.work_order_id')
                ->join('positions', 'positions.id', '=', 'work_orders.position_id')
                ->leftJoin('property_position_codes', function ($join): void {
                    $join->on('property_position_codes.property_id', '=', 'time_summaries.property_id')
                        ->on('property_position_codes.position_id', '=', 'work_orders.position_id');
                })
                ->whereDate('time_summaries.week_start', $week)
                ->when($propertyId, fn ($q, $id) => $q->where('time_summaries.property_id', $id))
                ->select([
                    'people.name as contractor',
                    'properties.name as property',
                    'positions.name as position',
                    'property_position_codes.job_code',
                    'time_summaries.regular_minutes',
                    'time_summaries.overtime_minutes',
                    'time_summaries.training_minutes',
                    'time_summaries.total_pay',
                ])
                ->orderBy('people.name')
                ->get()
                ->map(fn (object $row): array => [
                    'contractor' => (string) $row->contractor,
                    'property' => (string) $row->property,
                    'position' => (string) $row->position,
                    'job_code' => $row->job_code === null ? null : (string) $row->job_code,
                    'regular_minutes' => (int) $row->regular_minutes,
                    'overtime_minutes' => (int) $row->overtime_minutes,
                    'training_minutes' => (int) $row->training_minutes,
                    'total_pay' => (int) $row->total_pay,
                ]);
        }

        return [
            'filters' => ['week' => $week, 'property_id' => $propertyId],
            'weeks' => $weeks->all(),
            'rows' => $rows->all(),
            'totals' => [
                'regular_minutes' => (int) $rows->sum('regular_minutes'),
                'overtime_minutes' => (int) $rows->sum('overtime_minutes'),
                'training_minutes' => (int) $rows->sum('training_minutes'),
                'total_pay' => (int) $rows->sum('total_pay'),
            ],
        ];
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable, int|null}
     */
    private function weekRangeFilters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
        ]);

        // Rollup rows carry property-anchored week_starts (closing day, ADR-0009),
        // so the range filters on raw dates rather than normalizing to a weekday.
        $from = CarbonImmutable::parse($validated['from'] ?? now()->subWeeks(12)->toDateString())->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? now()->toDateString());

        return [$from, $to, isset($validated['property_id']) ? (int) $validated['property_id'] : null];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function propertyOptions(): array
    {
        return Property::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Property $property): array => ['id' => (int) $property->id, 'name' => $property->name])
            ->all();
    }
}
