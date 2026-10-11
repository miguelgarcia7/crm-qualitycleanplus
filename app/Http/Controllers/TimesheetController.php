<?php

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\SubmitTimesheetForApproval;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Reports\Exports\ArrayReportExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Back-office timesheets: the history list across all properties (Phase 09)
 * and the recruiter submit action (Phase 03). Route-gated:
 * `timesheets.view_history` for the list, `timesheets.export` for Excel.
 */
class TimesheetController extends Controller
{
    /**
     * Tab key => the statuses it covers. `all` covers everything.
     *
     * @var array<string, list<string>|null>
     */
    private const TABS = [
        'all' => null,
        'draft' => ['draft'],
        'pending' => ['pending_approval'],
        // Declined is back with the recruiter; voided needs nothing from anyone.
        'declined' => ['declined'],
        'billed' => ['approved', 'invoiced', 'invoice_sent'],
        'voided' => ['voided'],
    ];

    /**
     * Sortable column => the SQL expression behind it. A whitelist, so a
     * hand-edited query string cannot order by an arbitrary column.
     *
     * @var array<string, string>
     */
    private const SORTS = [
        'property' => 'properties.name',
        'week_start' => 'payroll_periods.week_start',
        'total_minutes' => 'sums.total_minutes',
        'total_bill' => 'sums.total_bill',
        'submitted_at' => 'timesheets.sent_for_approval_at',
        'approved_at' => 'timesheets.approved_at',
        'invoice_number' => 'invoices.invoice_number',
        'status' => 'timesheets.status',
    ];

    private const PER_PAGE = [10, 25, 50];

    public function index(Request $request): Response
    {
        /** @var Person $user */
        $user = $request->user();
        $filters = $this->filters($request);

        $page = $this->historyQuery($filters, $user)
            ->paginate($filters['per_page'])
            ->withQueryString();

        return Inertia::render('admin/timesheets/index', [
            'timesheets' => collect($page->items())->map($this->toRow(...))->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => $filters,
            'counts' => $this->tabCounts($filters, $user),
            'oldest_pending_days' => $this->oldestPendingDays($filters, $user),
            'properties' => $this->propertyOptions($user),
            'can_export' => $user->can('timesheets.export'),
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        // Export what is on screen, not the whole table — a filtered list that
        // exports something else is a reporting bug waiting to happen.
        /** @var Person $user */
        $user = $request->user();
        $rows = $this->historyQuery($this->filters($request), $user)->get()->map($this->toRow(...));

        return Excel::download(new ArrayReportExport(
            ['Property', 'Week Start', 'Week End', 'Status', 'Hours', 'Billed ($)', 'Submitted', 'Approved', 'Invoice #'],
            $rows->map(fn (array $row): array => [
                $row['property'], $row['week_start'], $row['week_end'], $row['status_label'],
                round($row['total_minutes'] / 60, 2), $row['total_bill'] / 100,
                $row['submitted_at'] ?? '', $row['approved_at'] ?? '', $row['invoice_number'] ?? '',
            ])->all(),
        ), 'timesheets-'.now()->toDateString().'.xlsx');
    }

    /**
     * The query string, normalised — every value validated against a whitelist
     * so the same array can drive the list, the counts and the export.
     *
     * @return array{tab: string, search: string, property_id: int|null, sort: string, direction: string, per_page: int}
     */
    private function filters(Request $request): array
    {
        $tab = (string) $request->string('tab', 'all');
        $sort = (string) $request->string('sort', 'week_start');
        $perPage = $request->integer('per_page', 25);

        return [
            'tab' => array_key_exists($tab, self::TABS) ? $tab : 'all',
            'search' => trim((string) $request->string('search')),
            'property_id' => $request->integer('property_id') ?: null,
            'sort' => array_key_exists($sort, self::SORTS) ? $sort : 'week_start',
            'direction' => $request->string('direction')->lower()->toString() === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * Timesheets matching the filters, ordered, with the hour and billing
     * totals joined in so they can be sorted on in SQL rather than in PHP.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Timesheet>
     */
    private function historyQuery(array $filters, Person $user): Builder
    {
        return $this->filteredQuery($filters, $user)
            ->select('timesheets.*', 'sums.total_minutes', 'sums.total_bill')
            ->with([
                'property:id,name,timezone',
                'payrollPeriod:id,week_start,week_end',
                'invoice:id,invoice_number',
            ])
            ->orderBy(self::SORTS[$filters['sort']], $filters['direction'])
            // Weeks tie constantly — without a stable tiebreaker the same row
            // can appear on two pages and another on none.
            ->orderBy('timesheets.id', 'desc');
    }

    /**
     * Joins and filters shared by the list, the counts and the export. A
     * recruiter or property manager sees only the properties they're assigned.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Timesheet>
     */
    private function filteredQuery(array $filters, Person $user, bool $applyTab = true): Builder
    {
        $sums = DB::table('time_summaries')
            ->groupBy('payroll_period_id')
            ->select([
                'payroll_period_id',
                DB::raw('SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as total_minutes'),
                DB::raw('SUM(total_bill) as total_bill'),
            ]);

        return Timesheet::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->join('properties', 'properties.id', '=', 'timesheets.property_id')
            ->leftJoin('invoices', 'invoices.id', '=', 'timesheets.invoice_id')
            ->leftJoinSub($sums, 'sums', 'sums.payroll_period_id', '=', 'timesheets.payroll_period_id')
            // This is the timesheet *history* — hide future ahead-periods (kept
            // ready for manual entry, but they have no hours yet).
            ->whereDate('payroll_periods.week_start', '<=', now())
            ->when(
                ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
                fn (Builder $q) => $q->whereIn('timesheets.property_id', $user->assignedProperties()->pluck('properties.id')->all()),
            )
            ->when(
                $applyTab && self::TABS[$filters['tab']] !== null,
                fn (Builder $q) => $q->whereIn('timesheets.status', self::TABS[$filters['tab']]),
            )
            ->when(
                $filters['property_id'] !== null,
                fn (Builder $q) => $q->where('timesheets.property_id', $filters['property_id']),
            )
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $like = '%'.$filters['search'].'%';
                $q->where(fn (Builder $w) => $w
                    ->where('properties.name', 'like', $like)
                    ->orWhere('invoices.invoice_number', 'like', $like));
            });
    }

    /**
     * Row counts per tab, under every filter *except* the tab itself — so the
     * numbers describe what switching tabs would actually show.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function tabCounts(array $filters, Person $user): array
    {
        $byStatus = $this->filteredQuery($filters, $user, applyTab: false)
            ->groupBy('timesheets.status')
            ->pluck(DB::raw('count(*)'), 'timesheets.status');

        $counts = [];

        foreach (self::TABS as $tab => $statuses) {
            $counts[$tab] = $statuses === null
                ? (int) $byStatus->sum()
                : (int) collect($statuses)->sum(fn (string $status): int => (int) $byStatus->get($status, 0));
        }

        return $counts;
    }

    /**
     * Whole days the longest-waiting pending sheet has sat with the property
     * manager, under the same filters as the counts; null when none is waiting.
     *
     * @param  array<string, mixed>  $filters
     */
    private function oldestPendingDays(array $filters, Person $user): ?int
    {
        $oldest = $this->filteredQuery($filters, $user, applyTab: false)
            ->where('timesheets.status', TimesheetStatus::PendingApproval->value)
            ->min('timesheets.sent_for_approval_at');

        return $oldest === null ? null : (int) Carbon::parse($oldest)->diffInDays(now(), absolute: true);
    }

    /**
     * Properties the viewer can see that actually have a timesheet, for the
     * filter dropdown.
     *
     * @return list<array{id: int, name: string}>
     */
    private function propertyOptions(Person $user): array
    {
        return Property::query()
            ->whereHas('timesheets')
            ->when(
                ! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES),
                fn (Builder $q) => $q->whereIn('id', $user->assignedProperties()->pluck('properties.id')->all()),
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Property $p): array => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Timesheet $timesheet): array
    {
        $period = $timesheet->payrollPeriod;
        $today = now($timesheet->property->timezone ?? config('app.timezone'))->toDateString();

        return [
            'id' => $timesheet->id,
            'property' => $timesheet->property->name ?? '—',
            'property_id' => $timesheet->property_id,
            'week_start' => $period->week_start->toDateString(),
            'week_end' => $period->week_end->toDateString(),
            // The week in progress at the property, in its own timezone.
            'is_current' => $period->week_start->toDateString() <= $today && $today <= $period->week_end->toDateString(),
            'status' => $timesheet->status->value,
            'status_label' => $timesheet->status->label(),
            'total_minutes' => (int) ($timesheet->getAttribute('total_minutes') ?? 0),
            'total_bill' => (int) ($timesheet->getAttribute('total_bill') ?? 0),
            'submitted_at' => $timesheet->sent_for_approval_at?->format('M j, Y'),
            'approved_at' => $timesheet->approved_at?->format('M j, Y'),
            // Whole days a pending sheet has waited on the property manager.
            'waiting_days' => $timesheet->status === TimesheetStatus::PendingApproval && $timesheet->sent_for_approval_at !== null
                ? (int) $timesheet->sent_for_approval_at->diffInDays(now(), absolute: true)
                : null,
            'decline_reason' => $timesheet->status === TimesheetStatus::Declined ? $timesheet->decline_reason : null,
            'invoice_id' => $timesheet->invoice_id,
            'invoice_number' => $timesheet->invoice->invoice_number ?? null,
        ];
    }

    public function submit(Timesheet $timesheet, SubmitTimesheetForApproval $action): RedirectResponse
    {
        $this->authorize('submit', $timesheet);

        $user = Auth::user();
        abort_unless($user instanceof Person, 403);

        $action->handle($timesheet, $user);

        return back()->with('success', 'Timesheet sent for approval.');
    }
}
