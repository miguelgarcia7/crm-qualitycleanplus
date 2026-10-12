<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use App\Domain\WorkOrders\Enums\MoreStaffStatus;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\MoreStaffRequest;
use App\Domain\WorkOrders\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The admin's Today view (docs/50-ui/dashboard-mockups/Admin-Today): where this
 * week's hours are on the way to an invoice and who to chase, every request
 * waiting on a decision, what expires or starts soon, and property setup gaps.
 *
 * Each list is capped at LIST_LIMIT rows with a total for "View all N". Every
 * number appears once on the page.
 */
class AdminToday
{
    private const LIST_LIMIT = 5;

    /** A timesheet with the property manager longer than this is overdue. */
    private const APPROVAL_SLA_DAYS = 3;

    private const EXPIRING_DAYS = 30;

    private const COMING_UP_DAYS = 14;

    public function __construct(private readonly PropertyAttention $attention) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Person $user): array
    {
        $now = CarbonImmutable::now(AutomationCatalog::DISPLAY_TIMEZONE);

        return [
            'context' => $this->context($user, $now),
            'actions' => $this->actions($user),
            'alerts' => $this->alerts($user),
            'pipeline' => $user->can('timesheets.view_history') ? $this->pipeline($now) : null,
            'decisions' => $this->decisions($user, $now),
            'expiring' => $user->can('bible.contracts.view') ? $this->expiring($now) : null,
            'comingUp' => $this->comingUp($user, $now),
            'setupGaps' => $this->setupGaps($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Person $user, CarbonImmutable $now): array
    {
        $greeting = match (true) {
            $now->hour < 12 => 'Good morning',
            $now->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY);

        return [
            'greeting' => $greeting.', '.strtok((string) $user->name, ' '),
            'date' => $now->format('l, M j'),
            'week' => 'Week of '.$this->range($weekStart, $weekStart->addDays(6)),
            'properties' => Property::query()->count(),
        ];
    }

    /**
     * The one or two things an admin starts from the dashboard.
     *
     * @return list<array<string, string>>
     */
    private function actions(Person $user): array
    {
        return collect([
            ['label' => 'Upload hours', 'href' => '/admin/imports/create', 'icon' => 'upload', 'permission' => 'imports.upload', 'primary' => false],
            ['label' => 'New work order', 'href' => '/admin/work-orders/create', 'icon' => 'plus', 'permission' => 'work_orders.create', 'primary' => true],
        ])
            ->filter(fn (array $action): bool => $user->can($action['permission']))
            ->map(fn (array $action): array => collect($action)->except('permission')->all())
            ->values()
            ->all();
    }

    /**
     * Only when something is broken.
     *
     * @return list<array<string, string>>
     */
    private function alerts(Person $user): array
    {
        $mailer = (string) config('mail.default');

        if (! in_array($mailer, ['log', 'array'], true) || ! $user->can('invoices.view')) {
            return [];
        }

        return [[
            'key' => 'mail-undelivered',
            'title' => "Invoice email isn't being delivered",
            'body' => "The mailer is set to {$mailer}, so invoices marked sent never reach a client.",
            'actionLabel' => 'View invoices',
            'actionHref' => '/admin/invoices',
        ]];
    }

    /**
     * Hours on the way to invoices, stage by stage, plus the timesheets
     * waiting on a property manager (oldest first) to chase.
     *
     * @return array<string, mixed>
     */
    private function pipeline(CarbonImmutable $now): array
    {
        $today = $now->toDateString();
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY);

        $clockedMinutes = (int) TimeSummary::query()
            ->whereDate('week_start', '<=', $today)
            ->whereDate('week_end', '>=', $today)
            ->sum(DB::raw('regular_minutes + overtime_minutes + holiday_minutes + training_minutes'));

        $openPunches = TimeEntry::query()->whereNotNull('start_at_utc')->whereNull('end_at_utc')->count();

        // Last week = each property's pay week that ended in the past seven days
        // (pay weeks are anchored per property, ADR-0009).
        $lastWeekFrom = $now->subDays(7)->toDateString();
        $lastWeekTo = $now->subDay()->toDateString();

        $workedLastWeek = TimeSummary::query()
            ->whereDate('week_end', '>=', $lastWeekFrom)
            ->whereDate('week_end', '<=', $lastWeekTo)
            ->whereRaw('regular_minutes + overtime_minutes + holiday_minutes + training_minutes > 0')
            ->distinct()
            ->pluck('property_id')
            ->all();

        $submitted = $workedLastWeek === [] ? 0 : Timesheet::query()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->whereIn('timesheets.property_id', $workedLastWeek)
            ->whereDate('payroll_periods.week_end', '>=', $lastWeekFrom)
            ->whereDate('payroll_periods.week_end', '<=', $lastWeekTo)
            ->whereNotIn('timesheets.status', [TimesheetStatus::Draft->value, TimesheetStatus::Declined->value])
            ->distinct()
            ->count('timesheets.property_id');

        $notSent = count($workedLastWeek) - $submitted;

        $pending = Timesheet::query()->pendingApproval();
        $pendingCount = $pending->clone()->count();
        $overdue = $pending->clone()->where('sent_for_approval_at', '<', $now->subDays(self::APPROVAL_SLA_DAYS))->count();

        $ready = Invoice::query()->awaitingSend()->whereNull('notification_sent_at');
        $readyCount = $ready->clone()->count();
        $readyCents = (int) $ready->clone()->sum('total');

        $sent = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->where('notification_sent_at', '>=', $weekStart->utc())
            ->count();

        return [
            'stages' => [
                [
                    'key' => 'clocked',
                    'label' => 'Clocked',
                    'value' => number_format((int) round($clockedMinutes / 60)).' h',
                    'note' => $openPunches > 0 ? $this->plural($openPunches, 'punch', 'punches').' still open' : 'All punches closed',
                    'href' => '/admin/timesheets',
                    'stuck' => false,
                ],
                [
                    'key' => 'submitted',
                    'label' => 'Submitted',
                    'value' => $workedLastWeek === [] ? '—' : $submitted.' of '.count($workedLastWeek),
                    'note' => match (true) {
                        $workedLastWeek === [] => 'No hours last week',
                        $notSent > 0 => $this->plural($notSent, 'property', 'properties').' not sent yet',
                        default => 'Every property sent last week',
                    },
                    'href' => '/admin/timesheets?tab=draft',
                    'stuck' => false,
                ],
                [
                    'key' => 'awaiting',
                    'label' => 'Awaiting PM approval',
                    'value' => (string) $pendingCount,
                    'note' => match (true) {
                        $overdue > 0 => $overdue.' past '.self::APPROVAL_SLA_DAYS.' days · list below',
                        $pendingCount > 0 => 'All within '.self::APPROVAL_SLA_DAYS.' days',
                        default => 'Nothing waiting',
                    },
                    'href' => '/admin/timesheets?tab=pending',
                    'stuck' => $overdue > 0,
                ],
                [
                    'key' => 'ready',
                    'label' => 'Ready to send',
                    'value' => (string) $readyCount,
                    'note' => $readyCount > 0 ? '$'.number_format($readyCents / 100, 2).' waiting' : 'Nothing to send',
                    'href' => '/admin/invoices?status='.InvoiceStatus::Invoiced->value,
                    'stuck' => false,
                ],
                [
                    'key' => 'sent',
                    'label' => 'Sent',
                    'value' => (string) $sent,
                    'note' => 'this week',
                    'href' => '/admin/invoices?status='.InvoiceStatus::InvoiceSent->value,
                    'stuck' => false,
                ],
            ],
            'chase' => $this->chase($now),
            'chaseTotal' => $pendingCount,
        ];
    }

    /**
     * Timesheets with a property manager, oldest first.
     *
     * @return list<array<string, mixed>>
     */
    private function chase(CarbonImmutable $now): array
    {
        $sheets = Timesheet::query()
            ->pendingApproval()
            ->with(['property:id,name', 'payrollPeriod:id,week_start,week_end', 'sentForApprovalBy:id,name'])
            ->orderBy('sent_for_approval_at')
            ->limit(self::LIST_LIMIT)
            ->get();

        if ($sheets->isEmpty()) {
            return [];
        }

        $bills = TimeSummary::query()
            ->whereIn('payroll_period_id', $sheets->pluck('payroll_period_id'))
            ->groupBy('payroll_period_id')
            ->selectRaw('payroll_period_id, SUM(total_bill) as bill')
            ->pluck('bill', 'payroll_period_id');

        $managers = PropertyAssignment::query()
            ->whereIn('property_id', $sheets->pluck('property_id'))
            ->where('role', PropertyAssignmentRole::PropertyManager->value)
            ->with('person:id,name,email')
            ->orderBy('id')
            ->get()
            ->groupBy('property_id');

        return $sheets->map(function (Timesheet $sheet) use ($bills, $managers, $now): array {
            $period = $sheet->payrollPeriod;
            $manager = $managers->get($sheet->property_id)?->first()?->person;
            $days = $sheet->sent_for_approval_at === null ? 0 : (int) $sheet->sent_for_approval_at->diffInDays($now);

            return [
                'id' => $sheet->id,
                'property' => $sheet->property?->name,
                'week' => $period !== null ? $this->range($period->week_start, $period->week_end) : null,
                'manager' => $manager?->name,
                'managerEmail' => $manager?->email,
                'recruiter' => $sheet->sentForApprovalBy?->name,
                'billable' => round(((int) ($bills[$sheet->payroll_period_id] ?? 0)) / 100, 2),
                'waitingDays' => $days,
                'overdue' => $days > self::APPROVAL_SLA_DAYS,
                'href' => $period !== null
                    ? "/admin/properties/{$sheet->property_id}/grid?week={$period->week_start->toDateString()}"
                    : "/admin/properties/{$sheet->property_id}",
            ];
        })->all();
    }

    /**
     * Every request waiting on a decision, one list across types, oldest
     * first. Applications collapse into one row: they are reviewed as a queue.
     *
     * @return array<string, mixed>
     */
    private function decisions(Person $user, CarbonImmutable $now): array
    {
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = collect();
        $totals = [];

        if ($user->can('workflows.more_staff.fulfill')) {
            $requests = MoreStaffRequest::query()
                ->whereIn('status', [MoreStaffStatus::Submitted->value, MoreStaffStatus::InProgress->value])
                ->with(['property:id,name', 'position:id,name', 'initiatedBy:id,name'])
                ->get();

            $totals[] = ['label' => 'Staffing requests', 'count' => $requests->count(), 'href' => '/admin/staffing-requests'];

            foreach ($requests as $request) {
                $rows->push([
                    'key' => "staffing-{$request->id}",
                    'type' => 'Staffing',
                    'title' => "{$request->property?->name} wants {$request->remaining()} more ".$request->position->name,
                    'detail' => collect([
                        $request->initiatedBy?->name !== null ? 'Requested by '.$request->initiatedBy->name : null,
                        "{$request->quantity_fulfilled} of {$request->quantity_requested} placed",
                        'needed by '.$request->by_date->format('M j'),
                    ])->filter()->implode(' · '),
                    'since' => $request->created_at?->toImmutable(),
                    'action' => ['label' => 'Place', 'href' => '/admin/staffing-requests'],
                ]);
            }
        }

        if ($user->can('workflows.pay_increase.approve')) {
            $increases = $this->openWorkflows(WorkflowType::PayIncrease);
            $workOrders = WorkOrder::query()
                ->whereIn('id', $increases->map(fn (Workflow $w) => $w->data['work_order_id'] ?? null)->filter())
                ->with(['person:id,name', 'property:id,name', 'position:id,name'])
                ->get()
                ->keyBy('id');

            $totals[] = ['label' => 'Pay increases', 'count' => $increases->count(), 'href' => '/admin/pay-increases'];

            foreach ($increases as $workflow) {
                $workOrder = $workOrders->get($workflow->data['work_order_id'] ?? 0);
                $cents = (int) ($workflow->data['pm_requested_increase_cents'] ?? 0);

                $rows->push([
                    'key' => "pay-{$workflow->id}",
                    'type' => 'Pay increase',
                    'title' => $cents > 0
                        ? "{$workOrder?->property?->name} offers +$".number_format($cents / 100, 2)."/h for {$workOrder?->person?->name}"
                        : "Pay increase for {$workOrder?->person?->name}",
                    'detail' => collect([
                        $workOrder?->position?->name,
                        $workOrder?->property?->name !== null && $cents === 0 ? $workOrder->property->name : null,
                        $workflow->initiator?->name !== null ? 'requested by '.$workflow->initiator->name : null,
                    ])->filter()->implode(' · '),
                    'since' => $workflow->created_at?->toImmutable(),
                    'action' => ['label' => 'Review', 'href' => '/admin/pay-increases'],
                ]);
            }
        }

        if ($user->can('workflows.termination.initiate') || $user->can('termination_records.view')) {
            $terminations = $this->openWorkflows(WorkflowType::Termination, withSteps: true);

            $totals[] = ['label' => 'Terminations', 'count' => $terminations->count(), 'href' => '/admin/terminations'];

            foreach ($terminations as $workflow) {
                $step = $workflow->steps->firstWhere('step_index', $workflow->current_step_index);

                $rows->push([
                    'key' => "termination-{$workflow->id}",
                    'type' => 'Termination',
                    'title' => $workflow->subject instanceof Person ? $workflow->subject->name : 'Termination',
                    'detail' => $step !== null
                        ? 'Next: '.lcfirst($step->name).($step->assigned_role !== null ? ' · '.str_replace('_', ' ', $step->assigned_role) : '')
                        : 'In progress',
                    'since' => $workflow->created_at?->toImmutable(),
                    'action' => ['label' => 'Open', 'href' => "/admin/terminations/{$workflow->id}"],
                ]);
            }
        }

        if ($user->can('workflows.pto.approve')) {
            $requests = PtoRequest::query()->pending()->with('person:id,name')->get();

            $totals[] = ['label' => 'Time off', 'count' => $requests->count(), 'href' => '/admin/pto'];

            foreach ($requests as $request) {
                $rows->push([
                    'key' => "pto-{$request->id}",
                    'type' => 'Time off',
                    'title' => "{$request->person?->name} · ".$this->range($request->start_date, $request->end_date),
                    'detail' => rtrim(rtrim((string) $request->hours, '0'), '.').' h '.strtolower($request->bucket->label()),
                    'since' => ($request->submitted_at ?? $request->created_at)?->toImmutable(),
                    'action' => ['label' => 'Review', 'href' => '/admin/pto'],
                ]);
            }
        }

        if ($user->can('workflows.change_personal_info.verify')) {
            $changes = $this->openWorkflows(WorkflowType::ChangePersonalInfo);

            $totals[] = ['label' => 'Info changes', 'count' => $changes->count(), 'href' => '/admin/info-changes'];

            foreach ($changes as $workflow) {
                $rows->push([
                    'key' => "info-{$workflow->id}",
                    'type' => 'Info change',
                    'title' => ($workflow->subject instanceof Person ? $workflow->subject->name : 'Someone').' updated their details',
                    'detail' => 'Verify before it replaces what is on file',
                    'since' => $workflow->created_at?->toImmutable(),
                    'action' => ['label' => 'Verify', 'href' => '/admin/info-changes'],
                ]);
            }
        }

        if ($user->can('people.applicants.view')) {
            $applications = JobApplication::query()->pending();
            $count = $applications->clone()->count();

            $totals[] = ['label' => 'Applications', 'count' => $count, 'href' => '/admin/applicants'];

            if ($count > 0) {
                $oldest = CarbonImmutable::parse((string) $applications->clone()->min('submitted_at'));

                $rows->push([
                    'key' => 'applications',
                    'type' => 'Applications',
                    'title' => $this->plural($count, 'application', 'applications').' to review',
                    'detail' => 'Oldest sent '.$oldest->format('M j'),
                    'since' => $oldest,
                    'represents' => $count,
                    'action' => ['label' => 'Review', 'href' => '/admin/applicants'],
                ]);
            }
        }

        $sorted = $rows->sortBy(fn (array $row) => $row['since']?->getTimestamp() ?? PHP_INT_MAX)->values();
        $shown = $sorted->take(self::LIST_LIMIT);
        $total = (int) collect($totals)->sum('count');

        return [
            'rows' => $shown->map(fn (array $row): array => [
                ...collect($row)->except(['since', 'represents'])->all(),
                'age' => $row['since'] !== null ? $this->age($row['since'], $now) : null,
            ])->all(),
            'total' => $total,
            // Requests not on screen; the grouped applications row stands for all of them.
            'hidden' => $total - (int) $shown->sum(fn (array $row): int => $row['represents'] ?? 1),
            'byType' => collect($totals)->where('count', '>', 0)->values()->all(),
        ];
    }

    /**
     * Contracts ending in the next 30 days. Certifications will join this
     * list once they exist.
     *
     * @return array<string, mixed>
     */
    private function expiring(CarbonImmutable $now): array
    {
        $contracts = Contract::query()
            ->expiringWithin(self::EXPIRING_DAYS)
            ->with('property:id,name')
            ->orderBy('expiration_date');

        return [
            'rows' => $contracts->clone()->limit(self::LIST_LIMIT)->get()->map(function (Contract $contract) use ($now): array {
                $ends = CarbonImmutable::parse((string) $contract->expiration_date?->toDateString());
                $days = (int) CarbonImmutable::parse($now->toDateString())->diffInDays($ends, true);

                return [
                    'id' => $contract->id,
                    'title' => "{$contract->property?->name} · {$contract->name}",
                    'detail' => 'Ends '.$contract->expiration_date?->format('M j'),
                    'days' => $days,
                    'tone' => $days <= 14 ? 'danger' : ($days <= 21 ? 'warning' : 'gray'),
                    'href' => "/admin/properties/{$contract->property_id}#settings/contracts",
                ];
            })->all(),
            'total' => $contracts->clone()->count(),
        ];
    }

    /**
     * Placements starting or ending and staff away, next two weeks, by day.
     *
     * @return array<string, mixed>
     */
    private function comingUp(Person $user, CarbonImmutable $now): array
    {
        $from = $now->startOfDay();
        $to = $from->addDays(self::COMING_UP_DAYS);
        $events = collect();

        if ($user->can('work_orders.view')) {
            $starting = WorkOrder::query()
                ->where('status', WorkOrderStatus::Active->value)
                ->whereDate('start_date', '>', $from->toDateString())
                ->whereDate('start_date', '<=', $to->toDateString())
                ->with('property:id,name')
                ->get()
                ->groupBy(fn (WorkOrder $wo): string => $wo->start_date->toDateString());

            foreach ($starting as $date => $group) {
                $events->push([
                    'date' => $date,
                    'title' => $this->plural($group->count(), 'placement', 'placements').' '.($group->count() === 1 ? 'starts' : 'start'),
                    'detail' => $this->names($group->map(fn (WorkOrder $wo) => $wo->property?->name)->all()),
                    'link' => ['label' => 'Work orders', 'href' => '/admin/work-orders'],
                ]);
            }

            $ending = WorkOrder::query()
                ->where('status', WorkOrderStatus::Active->value)
                ->whereNotNull('end_date')
                ->whereDate('end_date', '>=', $from->toDateString())
                ->whereDate('end_date', '<=', $to->toDateString())
                ->with('property:id,name')
                ->get()
                ->groupBy(fn (WorkOrder $wo): string => (string) $wo->end_date?->toDateString());

            foreach ($ending as $date => $group) {
                $events->push([
                    'date' => $date,
                    'title' => $this->plural($group->count(), 'placement', 'placements').' '.($group->count() === 1 ? 'ends' : 'end'),
                    'detail' => $this->names($group->map(fn (WorkOrder $wo) => $wo->property?->name)->all()),
                    'link' => ['label' => 'Work orders', 'href' => '/admin/work-orders'],
                ]);
            }
        }

        if ($user->can('workflows.pto.approve')) {
            PtoRequest::query()
                ->where('status', PtoRequestStatus::Approved->value)
                ->whereDate('start_date', '<=', $to->toDateString())
                ->whereDate('end_date', '>=', $from->toDateString())
                ->with('person:id,name')
                ->orderBy('start_date')
                ->get()
                ->each(function (PtoRequest $request) use ($events, $from): void {
                    $events->push([
                        'date' => max($request->start_date->toDateString(), $from->toDateString()),
                        'title' => "{$request->person?->name} away",
                        'detail' => 'through '.$request->end_date->format('M j'),
                        'link' => ['label' => 'Time off', 'href' => '/admin/pto'],
                    ]);
                });
        }

        $sorted = $events->sortBy('date')->values();

        return [
            'rows' => $sorted->take(6)->map(fn (array $event): array => [
                ...$event,
                'day' => CarbonImmutable::parse($event['date'])->format('M j'),
            ])->all(),
            'total' => $sorted->count(),
        ];
    }

    /**
     * Properties missing part of their setup, most gaps first. Stalled weeks
     * live in the pipeline and expiring contracts in their own card, so both
     * are left out here.
     *
     * @return array<string, mixed>
     */
    private function setupGaps(Person $user): array
    {
        $properties = Property::query()->orderBy('name')->get();
        $items = $this->attention->forMany($properties, $user);

        $gaps = $properties
            ->map(function (Property $property) use ($items): array {
                $list = array_values(array_filter(
                    $items[$property->id] ?? [],
                    fn (array $item): bool => ! preg_match('/^(timesheet|contract)-/', (string) $item['key']),
                ));

                return [
                    'id' => $property->id,
                    'name' => $property->name,
                    'count' => count($list),
                    'top' => $list[0]['title'] ?? null,
                    'href' => "/admin/properties/{$property->id}",
                ];
            })
            ->filter(fn (array $row): bool => $row['count'] > 0)
            ->sortByDesc('count')
            ->values();

        return [
            'rows' => $gaps->take(self::LIST_LIMIT)->all(),
            'total' => $gaps->count(),
        ];
    }

    /**
     * @return Collection<int, Workflow>
     */
    private function openWorkflows(WorkflowType $type, bool $withSteps = false): Collection
    {
        return Workflow::query()
            ->where('type', $type->value)
            ->where('status', WorkflowStatus::InProgress->value)
            ->with(array_filter(['initiator:id,name', 'subject', $withSteps ? 'steps' : null]))
            ->get();
    }

    /**
     * @param  array<int, string|null>  $names
     */
    private function names(array $names): string
    {
        $unique = collect($names)->filter()->unique()->values();

        return $unique->take(2)->implode(', ').($unique->count() > 2 ? ' +'.($unique->count() - 2).' more' : '');
    }

    private function range(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('M j').' – '.($start->isSameMonth($end) ? $end->format('j') : $end->format('M j'));
    }

    /** "today", "1 day", "12 days". */
    private function age(CarbonImmutable $since, CarbonImmutable $now): string
    {
        $days = (int) $since->diffInDays($now);

        return $days < 1 ? 'today' : $this->plural($days, 'day', 'days');
    }

    private function plural(int $count, string $one, string $many): string
    {
        return number_format($count).' '.($count === 1 ? $one : $many);
    }
}
