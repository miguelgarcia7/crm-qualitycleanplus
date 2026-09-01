<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\Imports\Models\ImportBatch;
use App\Domain\Inventory\Models\ItemVariant;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Support\OnboardingChecklist;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Reports\Models\ReportMonthlyRevenue;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\Workflows\Models\WorkflowStep;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Cross-context read service that assembles role-aware dashboard widgets (Phase 06).
 * Returns plain serializable arrays grouped as stats / lists / charts; each builder is
 * gated by the viewer's roles + permissions and property-scoped where relevant. No
 * writes — widgets only surface counts and deep-link into existing pages.
 */
class DashboardMetrics
{
    public function __construct(private readonly BackOfficePulse $pulse) {}

    /**
     * @return array<string, mixed>
     */
    public function forBackOffice(Person $user): array
    {
        $stats = [];
        $lists = [];
        $charts = [];

        $propertyIds = $this->scopedPropertyIds($user);

        // Margin is the headline chart for anyone allowed to see money, so it
        // goes in first and takes the wide slot on the landing page.
        if ($user->can('reports.financial.view')) {
            $charts[] = $this->revenueVsPayoutsChart();
        }

        $stats[] = [
            'title' => 'My open tasks',
            'value' => WorkflowStep::query()->openForPerson($user)->count(),
            'icon' => 'checklist',
            'href' => '/admin/tasks',
        ];

        if ($user->can('timesheets.view_live')) {
            [$current, $previous] = $this->hoursThisWeek($propertyIds);
            $stats[] = [
                'title' => 'Hours this week',
                'value' => $current,
                'suffix' => 'h',
                'icon' => 'clock',
                'href' => '/admin/timesheets',
                'change' => $previous > 0 ? (int) round((($current - $previous) / $previous) * 100) : null,
                'changeLabel' => 'vs all of last week',
            ];
        }

        if ($user->status->isStaff() && $user->can('pto.balances.view_own')) {
            $allotment = PtoYearAllotment::query()->where('person_id', $user->id)
                ->where('status', 'open')->latest('year_start')->first();
            if ($allotment !== null) {
                $stats[] = [
                    'title' => 'PTO available (vacation)',
                    'value' => $allotment->availableFor(PtoBucket::Vacation),
                    'suffix' => 'h',
                    'icon' => 'calendar',
                    'href' => '/admin/pto',
                ];
            }
        }

        if ($user->can('workflows.pto.approve')) {
            $stats[] = [
                'title' => 'PTO to approve',
                'value' => PtoRequest::query()->pending()->count(),
                'icon' => 'checklist',
                'href' => '/admin/pto',
                'tone' => 'warning',
            ];
        }

        if ($user->can('people.applicants.view')) {
            $stats[] = [
                'title' => 'Applications to review',
                'value' => JobApplication::query()->pending()->count(),
                'icon' => 'user-circle',
                'href' => '/admin/applicants',
                'tone' => 'warning',
            ];
        }

        if ($user->can('people.applicants.onboarding_checklist.edit')) {
            // "Onboarding Docs Pending" (people-lifecycle.md): applicants under
            // active review whose checklist still has open, non-waived items.
            $pendingDocs = Person::query()
                ->where('status', PersonStatus::Applicant)
                ->whereHas('jobApplications', fn ($q) => $q->where('status', JobApplicationStatus::Reviewing->value))
                ->get()
                ->reject(fn (Person $applicant): bool => OnboardingChecklist::isComplete($applicant))
                ->count();

            $stats[] = [
                'title' => 'Onboarding docs pending',
                'value' => $pendingDocs,
                'icon' => 'files',
                'href' => '/admin/applicants?status=reviewing',
            ];
        }

        if ($user->hasRole('recruiter')) {
            $this->recruiterWidgets($user, $propertyIds, $stats, $lists, $charts);
        }

        if ($user->hasRole(['office_manager', 'admin'])) {
            $this->officeWidgets($stats, $lists, $charts);
        }

        if ($user->hasRole('payroll')) {
            $this->payrollWidgets($stats, $lists, $charts);
        }

        if ($user->hasRole('hr')) {
            $this->hrWidgets($stats, $lists);
        }

        if ($user->hasRole('super_admin')) {
            $this->superAdminWidgets($stats, $charts);
        }

        if ($user->hasRole('front_desk')) {
            $stats[] = [
                'title' => 'Open supply requests',
                'value' => SupplyRequest::query()->pending()->count(),
                'icon' => 'package',
                'href' => '/admin/requests',
            ];
        }

        return [
            'stats' => $stats,
            'lists' => $lists,
            'charts' => $charts,
            'actions' => $this->quickActions($user),
            'activity' => $user->can('audit.activity_log.view') ? $this->recentActivity() : null,
            ...$this->pulse->build($user, $propertyIds),
        ];
    }

    /**
     * @return array{stats: list<array<string, mixed>>, lists: list<array<string, mixed>>, charts: list<array<string, mixed>>}
     */
    public function forPropertyManager(Person $user): array
    {
        $ids = $this->scopedPropertyIds($user) ?? [];

        $pending = Timesheet::query()->pendingApproval()
            ->whereIn('property_id', $ids)
            ->with('property:id,name', 'payrollPeriod:id,week_start,week_end')
            ->latest('id')->get();

        $stats = [
            [
                'title' => 'Timesheets to approve',
                'value' => $pending->count(),
                'icon' => 'checklist',
                'href' => '/timesheets',
                'tone' => $pending->isNotEmpty() ? 'warning' : 'default',
            ],
            [
                'title' => 'Open staffing requests',
                'value' => MoreStaffRequest::query()
                    ->whereIn('property_id', $ids)
                    ->whereIn('status', [MoreStaffStatus::Submitted->value, MoreStaffStatus::InProgress->value])
                    ->count(),
                'icon' => 'user-circle',
                'href' => '/staffing-requests',
            ],
        ];

        $lists = [[
            'title' => 'Awaiting your approval',
            'rows' => $pending->map(fn (Timesheet $t): array => [
                'label' => $t->property->name,
                'sublabel' => $t->payrollPeriod
                    ? 'Week of '.$t->payrollPeriod->week_start->toDateString()
                    : null,
                'meta' => 'Review',
                'tone' => 'warning',
                'href' => "/timesheets/{$t->id}",
            ])->all(),
            'viewAllHref' => '/timesheets',
            'emptyText' => 'No timesheets awaiting approval.',
        ]];

        $charts = [$this->weeklyHoursChart($ids)];

        return ['stats' => $stats, 'lists' => $lists, 'charts' => $charts];
    }

    /**
     * @param  list<int>|null  $propertyIds
     * @param  list<array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $lists
     * @param  list<array<string, mixed>>  $charts
     */
    private function recruiterWidgets(Person $user, ?array $propertyIds, array &$stats, array &$lists, array &$charts): void
    {
        $ids = $propertyIds ?? [];

        $stats[] = [
            'title' => 'My properties',
            'value' => count($ids),
            'icon' => 'table-column',
            'href' => '/admin/properties',
        ];
        $stats[] = [
            'title' => 'My active contractors',
            'value' => Person::query()->primaryContractorsOf($user->id)
                ->where('status', PersonStatus::ContractorActive->value)->count(),
            'icon' => 'user-circle',
        ];
        $stats[] = [
            'title' => 'My visits this week',
            'value' => FieldVisit::query()->where('person_id', $user->id)
                ->where('check_in_at', '>=', CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY))->count(),
            'icon' => 'map-pin',
            'href' => '/admin/field-visits',
        ];
        $stats[] = [
            'title' => 'Invoices to send',
            'value' => Invoice::query()->awaitingSend()->whereIn('property_id', $ids)->count(),
            'icon' => 'files',
            'href' => '/admin/invoices',
        ];

        $drafts = Timesheet::query()
            ->where('status', TimesheetStatus::Draft->value)
            ->whereIn('property_id', $ids)
            ->with('property:id,name', 'payrollPeriod:id,week_start')
            ->latest('id')->limit(8)->get();

        $lists[] = [
            'title' => 'Draft timesheets to send',
            'rows' => $drafts->map(fn (Timesheet $t): array => [
                'label' => $t->property->name,
                'sublabel' => $t->payrollPeriod ? 'Week of '.$t->payrollPeriod->week_start->toDateString() : null,
                'meta' => 'Open grid',
                'href' => "/admin/properties/{$t->property_id}/grid",
            ])->all(),
            'emptyText' => 'No draft timesheets.',
        ];

        $lists[] = $this->expiringContractsList($ids);
        $charts[] = $this->weeklyHoursChart($ids);
    }

    /**
     * @param  list<array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $lists
     * @param  list<array<string, mixed>>  $charts
     */
    private function officeWidgets(array &$stats, array &$lists, array &$charts): void
    {
        $stats[] = ['title' => 'Properties', 'value' => Property::query()->count(), 'icon' => 'table-column', 'href' => '/admin/properties'];
        $stats[] = ['title' => 'Open supply requests', 'value' => SupplyRequest::query()->pending()->count(), 'icon' => 'package', 'href' => '/admin/requests'];
        $stats[] = [
            'title' => 'Low-stock items',
            'value' => ItemVariant::query()->where('active', true)
                ->where('reorder_threshold', '>', 0)
                ->whereColumn('current_stock', '<=', 'reorder_threshold')->count(),
            'icon' => 'components',
            'href' => '/admin/inventory',
            'tone' => 'warning',
        ];
        $stats[] = [
            'title' => 'Stale open visits',
            'value' => FieldVisit::query()->open()
                ->where('check_in_at', '<', CarbonImmutable::now()->subDay())->count(),
            'icon' => 'map-pin',
            'href' => '/admin/field-visits?filter=open',
            'tone' => 'warning',
        ];

        $imports = ImportBatch::query()->with('property:id,name')->latest('id')->limit(6)->get();
        $lists[] = [
            'title' => 'Recent imports',
            'rows' => $imports->map(fn (ImportBatch $b): array => [
                'label' => $b->property->name,
                'sublabel' => $b->file_name,
                'meta' => $b->status->label(),
                'tone' => $b->status->value === 'applied' ? 'success' : 'default',
                'href' => "/admin/imports/{$b->id}",
            ])->all(),
            'viewAllHref' => '/admin/imports',
            'emptyText' => 'No imports yet.',
        ];

        $lists[] = $this->expiringContractsList(null);
        $charts[] = $this->weeklyRevenueChart();
    }

    /**
     * @param  list<array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $lists
     * @param  list<array<string, mixed>>  $charts
     */
    private function payrollWidgets(array &$stats, array &$lists, array &$charts): void
    {
        $stats[] = ['title' => 'Open periods', 'value' => PayrollPeriod::query()->where('status', PayrollPeriodStatus::Open->value)->count(), 'icon' => 'layout'];
        $stats[] = ['title' => 'Locked periods', 'value' => PayrollPeriod::query()->where('status', PayrollPeriodStatus::Locked->value)->count(), 'icon' => 'layout'];
        $stats[] = ['title' => 'Invoices to send', 'value' => Invoice::query()->awaitingSend()->count(), 'icon' => 'files', 'href' => '/admin/invoices'];
        $stats[] = ['title' => 'Voided invoices', 'value' => Invoice::query()->where('status', InvoiceStatus::Voided->value)->count(), 'icon' => 'files', 'href' => '/admin/invoices', 'tone' => 'danger'];

        $lists[] = $this->expiringContractsList(null);
        $charts[] = $this->weeklyRevenueChart();
    }

    /**
     * @param  list<array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $lists
     */
    private function hrWidgets(array &$stats, array &$lists): void
    {
        $stats[] = [
            'title' => 'Pending info changes',
            'value' => $this->openWorkflowCount(WorkflowType::ChangePersonalInfo),
            'icon' => 'user-circle',
            'href' => '/admin/info-changes',
        ];
        $stats[] = [
            'title' => 'Applicants',
            'value' => Person::query()->where('status', PersonStatus::Applicant->value)->count(),
            'icon' => 'user-circle',
        ];

        $terminations = Workflow::query()
            ->where('type', WorkflowType::Termination->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->with('subject:id,name')
            ->latest('id')->limit(8)->get();

        $lists[] = [
            'title' => 'Terminations in progress',
            'rows' => $terminations->map(fn (Workflow $w): array => [
                'label' => $w->subject instanceof Person ? $w->subject->name : 'Person',
                'meta' => 'In progress',
                'tone' => 'warning',
                'href' => "/admin/terminations/{$w->id}",
            ])->all(),
            'viewAllHref' => '/admin/terminations',
            'emptyText' => 'No terminations in progress.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $stats
     * @param  list<array<string, mixed>>  $charts
     */
    private function superAdminWidgets(array &$stats, array &$charts): void
    {
        $monthStart = CarbonImmutable::now()->startOfMonth();
        $invoicedSince = fn (CarbonImmutable $from, CarbonImmutable $until): int => (int) Invoice::query()
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<', $until->toDateString())
            ->sum('total');

        $thisMonth = $invoicedSince($monthStart, $monthStart->addMonth());
        $lastMonth = $invoicedSince($monthStart->subMonth(), $monthStart);

        $stats[] = ['title' => 'Active work orders', 'value' => WorkOrder::query()->active()->count(), 'icon' => 'files'];
        $stats[] = ['title' => 'People', 'value' => Person::query()->count(), 'icon' => 'user-circle', 'href' => '/admin/people'];
        $stats[] = ['title' => 'Open workflows', 'value' => Workflow::query()->where('status', WorkflowStatus::InProgress->value)->count(), 'icon' => 'sitemap'];
        $stats[] = [
            'title' => 'Invoiced this month',
            'value' => $this->dollars($thisMonth),
            'prefix' => '$',
            'icon' => 'files',
            'change' => $lastMonth > 0 ? (int) round((($thisMonth - $lastMonth) / $lastMonth) * 100) : null,
            'changeLabel' => 'vs last month',
        ];

        // Not revenue-vs-payouts: forBackOffice() already adds it as the
        // headline chart for anyone with reports.financial.view, which
        // super_admin holds. Adding it here rendered it twice.
        $charts[] = $this->weeklyRevenueChart();
    }

    /**
     * @param  list<int>|null  $propertyIds
     * @return array<string, mixed>
     */
    private function expiringContractsList(?array $propertyIds): array
    {
        $contracts = Contract::query()->expiringWithin(30)
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->with('property:id,name')
            ->orderBy('expiration_date')->limit(8)->get();

        return [
            'title' => 'Contracts expiring (30 days)',
            'rows' => $contracts->map(fn (Contract $c): array => [
                'label' => $c->property->name,
                'sublabel' => $c->name,
                'meta' => $c->expiration_date?->toDateString(),
                'tone' => 'danger',
                'href' => "/admin/properties/{$c->property_id}",
            ])->all(),
            'emptyText' => 'No contracts expiring soon.',
        ];
    }

    /**
     * Weekly billable hours across the given properties (last 8 weeks).
     *
     * @param  list<int>  $propertyIds
     * @return array<string, mixed>
     */
    private function weeklyHoursChart(array $propertyIds): array
    {
        [$weeks, $labels] = $this->weekBuckets();

        $rows = TimeSummary::query()
            ->whereIn('property_id', $propertyIds)
            ->whereDate('week_start', '>=', $weeks[0])
            ->get(['week_start', 'regular_minutes', 'overtime_minutes', 'holiday_minutes', 'training_minutes']);

        $byWeek = array_fill_keys($weeks, 0);
        foreach ($rows as $row) {
            $key = $row->week_start->toDateString();
            if (isset($byWeek[$key])) {
                $byWeek[$key] += $row->regular_minutes + $row->overtime_minutes + $row->holiday_minutes + $row->training_minutes;
            }
        }

        return [
            'title' => 'Weekly hours',
            'categories' => $labels,
            'series' => [['name' => 'Hours', 'data' => array_map(fn (int $min): int => (int) round($min / 60), array_values($byWeek))]],
            'type' => 'bar',
            'valueSuffix' => 'h',
        ];
    }

    /**
     * Weekly billed revenue (non-voided invoices, last 8 weeks).
     *
     * @return array<string, mixed>
     */
    private function weeklyRevenueChart(): array
    {
        [$weeks, $labels] = $this->weekBuckets();

        $invoices = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereDate('issue_date', '>=', $weeks[0])
            ->get(['issue_date', 'total']);

        $byWeek = array_fill_keys($weeks, 0);
        foreach ($invoices as $invoice) {
            $key = CarbonImmutable::parse($invoice->issue_date)->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
            if (isset($byWeek[$key])) {
                $byWeek[$key] += (int) $invoice->total;
            }
        }

        return [
            'title' => 'Weekly revenue',
            'categories' => $labels,
            'series' => [['name' => 'Revenue', 'data' => array_map(fn (int $cents): float => $this->dollars($cents), array_values($byWeek))]],
            'type' => 'area',
            'valuePrefix' => '$',
        ];
    }

    /**
     * Monday of each of the last 8 weeks (oldest first) + short labels.
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function weekBuckets(): array
    {
        $start = CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(7);
        $weeks = [];
        $labels = [];
        for ($i = 0; $i < 8; $i++) {
            $week = $start->addWeeks($i);
            $weeks[] = $week->toDateString();
            $labels[] = $week->format('M j');
        }

        return [$weeks, $labels];
    }

    /**
     * Total worked hours in each property's CURRENT week vs the week before
     * (weeks are per-property anchored, ADR-0009 — "this week" = the summary
     * week containing today, "last week" = the one containing today − 7d).
     *
     * @param  list<int>|null  $propertyIds
     * @return array{0: int, 1: int}
     */
    private function hoursThisWeek(?array $propertyIds): array
    {
        $sumFor = function (CarbonImmutable $day) use ($propertyIds): int {
            $minutes = TimeSummary::query()
                ->whereDate('week_start', '<=', $day->toDateString())
                ->whereDate('week_end', '>=', $day->toDateString())
                ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
                ->sum(DB::raw('regular_minutes + overtime_minutes + holiday_minutes + training_minutes'));

            return (int) round(((int) $minutes) / 60);
        };

        $today = CarbonImmutable::now();

        return [$sumFor($today), $sumFor($today->subWeek())];
    }

    /**
     * Latest audit-log entries (only sent to audit.activity_log.view holders).
     *
     * @return list<array<string, mixed>>
     */
    private function recentActivity(): array
    {
        return Activity::query()
            ->with('causer')
            ->latest()
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Activity $a): array => [
                'description' => $a->description,
                'event' => $a->event,
                'causer' => $a->causer instanceof Person ? $a->causer->name : null,
                'when' => $a->created_at?->diffForHumans(),
            ])
            ->all();
    }

    /**
     * Permission-gated shortcut buttons, in priority order (max 6).
     *
     * @return list<array<string, string>>
     */
    private function quickActions(Person $user): array
    {
        $candidates = [
            ['label' => 'New Work Order', 'href' => '/admin/work-orders/create', 'icon' => 'plus', 'permission' => 'work_orders.create'],
            ['label' => 'Upload Hours', 'href' => '/admin/imports/create', 'icon' => 'cloud-upload', 'permission' => 'imports.upload'],
            ['label' => 'Timesheets', 'href' => '/admin/timesheets', 'icon' => 'layout', 'permission' => 'timesheets.view_history'],
            ['label' => 'Invoices', 'href' => '/admin/invoices', 'icon' => 'files', 'permission' => 'invoices.view'],
            ['label' => 'People', 'href' => '/admin/people', 'icon' => 'user-circle', 'permission' => 'people.contractors.view'],
            ['label' => 'Reports', 'href' => '/admin/reports', 'icon' => 'chart-bar', 'permission' => 'reports.operational.view'],
            ['label' => 'Applicants', 'href' => '/admin/applicants', 'icon' => 'user-plus', 'permission' => 'people.applicants.view'],
            ['label' => 'Inventory', 'href' => '/admin/inventory', 'icon' => 'components', 'permission' => 'inventory.items.view'],
            ['label' => 'Request Time Off', 'href' => '/admin/pto', 'icon' => 'calendar', 'permission' => 'pto.balances.view_own'],
        ];

        return collect($candidates)
            ->filter(fn (array $action): bool => $user->can($action['permission']))
            ->map(fn (array $action): array => collect($action)->except('permission')->all())
            ->take(6)
            ->values()
            ->all();
    }

    /**
     * Monthly revenue vs contractor payouts (last 6 months) from the Phase 09
     * financial rollup — the margin picture at a glance.
     *
     * @return array<string, mixed>
     */
    private function revenueVsPayoutsChart(): array
    {
        $start = CarbonImmutable::now()->startOfMonth()->subMonths(5);
        $months = [];
        $labels = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->addMonths($i);
            $months[] = $month->toDateString();
            $labels[] = $month->format('M');
        }

        $rows = ReportMonthlyRevenue::query()
            ->whereDate('month_start', '>=', $months[0])
            ->get(['month_start', 'invoiced_total', 'payout_total']);

        $revenue = array_fill_keys($months, 0);
        $payouts = array_fill_keys($months, 0);
        foreach ($rows as $row) {
            $key = CarbonImmutable::parse($row->month_start)->startOfMonth()->toDateString();
            if (isset($revenue[$key])) {
                $revenue[$key] += (int) $row->invoiced_total;
                $payouts[$key] += (int) $row->payout_total;
            }
        }

        return [
            'title' => 'Revenue vs payouts (6 months)',
            'categories' => $labels,
            'series' => [
                ['name' => 'Revenue', 'data' => array_map($this->dollars(...), array_values($revenue))],
                ['name' => 'Payouts', 'data' => array_map($this->dollars(...), array_values($payouts))],
            ],
            'type' => 'bar',
            'valuePrefix' => '$',
        ];
    }

    private function openWorkflowCount(WorkflowType $type): int
    {
        return Workflow::query()
            ->where('type', $type->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->count();
    }

    /**
     * Property ids the user is scoped to, or null when they see everything.
     *
     * @return list<int>|null
     */
    private function scopedPropertyIds(Person $user): ?array
    {
        if ($user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES)) {
            return null;
        }

        return $user->assignedProperties()->pluck('properties.id')->map(fn ($id): int => (int) $id)->all();
    }

    private function dollars(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
