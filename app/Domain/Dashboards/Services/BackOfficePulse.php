<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\WorkflowStep;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The back-office landing panels: the headline figures, the hour-to-invoice
 * pipeline, and the queues that need a person to act. Read-only and gated by
 * the viewer's permissions — a recruiter never sees company-wide money.
 *
 * Kept separate from {@see DashboardMetrics} (which assembles the role-specific
 * widget lists) so each file stays readable; the controller gets both.
 */
class BackOfficePulse
{
    /** A timesheet waiting longer than this reads as overdue. */
    private const APPROVAL_SLA_DAYS = 3;

    /**
     * @param  list<int>|null  $propertyIds  null means "every property"
     * @return array<string, mixed>
     */
    public function build(Person $user, ?array $propertyIds): array
    {
        return [
            'context' => $this->context($propertyIds),
            'alerts' => $this->alerts($user),
            'headline' => $this->headline($user, $propertyIds),
            'pipeline' => $user->can('timesheets.view_history') ? $this->pipeline($user, $propertyIds) : null,
            'tasks' => $this->tasks($user),
            'approvals' => $user->can('timesheets.view_history') ? $this->approvals($propertyIds) : null,
            'onTheClock' => $user->can('timesheets.view_live') ? $this->onTheClock($propertyIds) : null,
            'decisions' => $this->decisions($user, $propertyIds),
        ];
    }

    /**
     * @param  list<int>|null  $propertyIds
     * @return array<string, mixed>
     */
    private function context(?array $propertyIds): array
    {
        $now = CarbonImmutable::now();
        $start = $now->startOfWeek(CarbonImmutable::MONDAY);

        return [
            'week_label' => 'Week of '.$start->format('M j').' – '.$start->addDays(6)->format('j'),
            'property_count' => $propertyIds === null
                ? Property::query()->count()
                : count($propertyIds),
            'scoped' => $propertyIds !== null,
        ];
    }

    /**
     * Conditions that need someone's attention before go-live. Only surfaced to
     * people who could actually act on them.
     *
     * @return list<array<string, mixed>>
     */
    private function alerts(Person $user): array
    {
        $alerts = [];

        // Invoices record a recipient and flip to "sent", but a log/array mailer
        // means nothing left the building. Silent data loss, so say it loudly.
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true) && $user->can('invoices.view')) {
            $affected = Invoice::query()->whereNotNull('notification_sent_at')->count();

            $alerts[] = [
                'key' => 'mail-undelivered',
                'icon' => 'mail-off',
                'title' => 'Outbound email is not being delivered',
                'body' => "The mailer is still set to {$mailer}. Invoices are marked sent and recipients recorded, but nothing reaches the client.",
                'meta' => $affected > 0 ? $affected.' '.($affected === 1 ? 'invoice' : 'invoices').' affected' : null,
                'badge' => 'Blocks launch',
                'actionLabel' => 'View invoices',
                'actionHref' => '/admin/invoices',
            ];
        }

        return $alerts;
    }

    /**
     * @param  list<int>|null  $propertyIds
     * @return list<array<string, mixed>>
     */
    private function headline(Person $user, ?array $propertyIds): array
    {
        $cards = [];

        if ($user->can('timesheets.view_history')) {
            [$hours, $previousHours] = $this->weekHours($propertyIds);

            $cards[] = [
                'title' => 'Billable hours',
                'value' => $hours,
                'icon' => 'clock',
                'tone' => 'default',
                'change' => $previousHours > 0 ? round((($hours - $previousHours) / $previousHours) * 100, 1) : null,
                'changeLabel' => 'vs same point last week',
                'href' => '/admin/timesheets',
            ];
        }

        if ($user->can('reports.financial.view')) {
            [$thisMonth, $lastMonth] = $this->monthRevenue();

            $cards[] = [
                'title' => 'Gross income MTD',
                'value' => $this->dollars($thisMonth),
                'prefix' => '$',
                'icon' => 'cash',
                'tone' => 'success',
                'change' => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : null,
                'changeLabel' => 'vs '.CarbonImmutable::now()->subMonth()->format('M'),
                'href' => '/admin/reports/revenue',
            ];
        }

        if ($user->can('timesheets.view_history')) {
            $pending = $this->pendingApprovalQuery($propertyIds)->count();
            $overdue = $this->pendingApprovalQuery($propertyIds)
                ->where('sent_for_approval_at', '<', CarbonImmutable::now()->subDays(self::APPROVAL_SLA_DAYS))
                ->count();

            $cards[] = [
                'title' => 'Awaiting PM approval',
                'value' => $pending,
                'icon' => 'user-check',
                'tone' => $overdue > 0 ? 'warning' : 'default',
                'sublabel' => $overdue > 0 ? $overdue.' over '.self::APPROVAL_SLA_DAYS.' days' : null,
                'href' => '/admin/timesheets',
            ];
        }

        if ($user->can('invoices.view')) {
            [$count, $cents] = $this->readyToSend($propertyIds);

            $cards[] = [
                'title' => 'Ready to send',
                'value' => $this->dollars($cents),
                'prefix' => '$',
                'icon' => 'file-invoice',
                'tone' => 'default',
                'sublabel' => $count.' '.($count === 1 ? 'invoice' : 'invoices').' frozen, not sent',
                'href' => '/admin/invoices',
            ];
        }

        return $cards;
    }

    /**
     * The hour-to-invoice chain, stage by stage. Every stage needs a person to
     * press a button — this shows where the week is stuck.
     *
     * @param  list<int>|null  $propertyIds
     * @return array<string, mixed>
     */
    private function pipeline(Person $user, ?array $propertyIds): array
    {
        $now = CarbonImmutable::now();
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY);

        $clockedMinutes = (int) TimeSummary::query()
            ->whereDate('week_start', '<=', $now->toDateString())
            ->whereDate('week_end', '>=', $now->toDateString())
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->sum(DB::raw('regular_minutes + overtime_minutes + holiday_minutes + training_minutes'));

        $openEntries = TimeEntry::query()
            ->whereNotNull('start_at_utc')
            ->whereNull('end_at_utc')
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->count();

        $submitted = Timesheet::query()
            ->whereNotNull('sent_for_approval_at')
            ->where('sent_for_approval_at', '>=', $weekStart)
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->count();

        $submitters = Timesheet::query()
            ->whereNotNull('sent_for_approval_at')
            ->where('sent_for_approval_at', '>=', $weekStart)
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->distinct()
            ->count('sent_for_approval_by');

        $pending = $this->pendingApprovalQuery($propertyIds)->count();
        $overdue = $this->pendingApprovalQuery($propertyIds)
            ->where('sent_for_approval_at', '<', $now->subDays(self::APPROVAL_SLA_DAYS))
            ->count();
        $declined = Timesheet::query()
            ->where('status', TimesheetStatus::Declined->value)
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->count();

        [$readyCount, $readyCents] = $this->readyToSend($propertyIds);

        $markedSent = Invoice::query()
            ->whereNotNull('notification_sent_at')
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->count();

        // Nothing actually leaves while the mailer is a log/array driver.
        $delivered = in_array((string) config('mail.default'), ['log', 'array'], true) ? 0 : $markedSent;

        $stages = [
            [
                'key' => 'clocked',
                'label' => 'Clocked hours',
                'icon' => 'qrcode',
                'value' => (int) round($clockedMinutes / 60),
                'note' => $openEntries > 0 ? $openEntries.' still on the clock' : 'All punches closed',
                'tone' => 'default',
                'href' => '/admin/timesheets',
            ],
            [
                'key' => 'submitted',
                'label' => 'Submitted',
                'icon' => 'send',
                'value' => $submitted,
                'note' => $submitters > 0 ? 'by '.$submitters.' '.($submitters === 1 ? 'recruiter' : 'recruiters') : 'None sent yet',
                'tone' => 'default',
                'href' => '/admin/timesheets',
            ],
            [
                'key' => 'awaiting',
                'label' => 'Awaiting PM',
                'icon' => 'user-check',
                'value' => $pending,
                'note' => trim(($overdue > 0 ? $overdue.' over '.self::APPROVAL_SLA_DAYS.' days' : 'All within SLA').($declined > 0 ? ' · '.$declined.' declined' : '')),
                'tone' => $overdue > 0 ? 'warning' : 'default',
                'href' => '/admin/timesheets',
            ],
            [
                'key' => 'ready',
                'label' => 'Ready to send',
                'icon' => 'file-invoice',
                'value' => $readyCount,
                'note' => '$'.number_format($this->dollars($readyCents), 2).' frozen at generation',
                'tone' => 'default',
                'href' => '/admin/invoices',
            ],
            [
                'key' => 'sent',
                'label' => 'Marked sent',
                'icon' => 'mail',
                'value' => $markedSent,
                'note' => $delivered.' actually delivered',
                'tone' => $markedSent > 0 && $delivered === 0 ? 'danger' : 'default',
                'href' => '/admin/invoices',
            ],
        ];

        // The bar shows the funnel narrowing, so it is scaled across the stages
        // that count the same thing (timesheets/invoices). Clocked hours is the
        // source of the funnel in a different unit — it gets a full bar rather
        // than a meaningless ratio against a count.
        $peak = max(1, $submitted, $pending, $readyCount, $markedSent);

        return [
            'title' => 'Hour-to-invoice pipeline',
            'subtitle' => $this->context($propertyIds)['week_label'],
            'note' => 'Every stage needs a person to press the button',
            'stages' => array_map(
                fn (array $stage): array => [
                    ...$stage,
                    'unit' => $stage['key'] === 'clocked' ? 'hours' : 'count',
                    'fill' => $stage['key'] === 'clocked' ? 1.0 : round(((int) $stage['value']) / $peak, 4),
                ],
                $stages,
            ),
        ];
    }

    /**
     * The signed-in person's open workflow steps — the My Tasks inbox, inline.
     *
     * @return array<string, mixed>
     */
    private function tasks(Person $user): array
    {
        $now = CarbonImmutable::now();

        $steps = WorkflowStep::query()
            ->openForPerson($user)
            ->with(['workflow.initiator:id,name'])
            ->latest('id')
            ->limit(6)
            ->get();

        $total = WorkflowStep::query()->openForPerson($user)->count();

        return [
            'title' => 'My tasks',
            'total' => $total,
            'rows' => $steps->map(function (WorkflowStep $step) use ($now): array {
                $type = WorkflowType::tryFrom((string) $step->workflow->type);

                return [
                    'id' => $step->id,
                    'label' => ($type?->label() ?? 'Task').' — '.$step->name,
                    'sublabel' => $step->workflow->initiator?->name !== null
                        ? 'Raised by '.$step->workflow->initiator->name
                        : null,
                    'icon' => $this->workflowIcon($type),
                    'age' => $step->created_at !== null ? $this->shortAge($step->created_at->toImmutable(), $now) : null,
                ];
            })->all(),
            'viewAllHref' => '/admin/tasks',
            'emptyText' => 'Nothing is waiting on you.',
        ];
    }

    /**
     * Timesheets sitting with a property manager, oldest first.
     *
     * @param  list<int>|null  $propertyIds
     * @return array<string, mixed>
     */
    private function approvals(?array $propertyIds): array
    {
        $now = CarbonImmutable::now();

        $timesheets = $this->pendingApprovalQuery($propertyIds)
            ->with(['property:id,name', 'payrollPeriod:id,week_start,week_end', 'sentForApprovalBy:id,name'])
            ->orderBy('sent_for_approval_at')
            ->limit(6)
            ->get();

        $periodIds = $timesheets->pluck('payroll_period_id')->all();

        /** @var array<int, array{minutes: int, bill: int}> $totals */
        $totals = [];
        if ($periodIds !== []) {
            $rows = DB::table('time_summaries')
                ->whereIn('payroll_period_id', $periodIds)
                ->selectRaw('payroll_period_id, SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as minutes, SUM(total_bill) as bill')
                ->groupBy('payroll_period_id')
                ->get();

            foreach ($rows as $row) {
                $totals[(int) $row->payroll_period_id] = [
                    'minutes' => (int) $row->minutes,
                    'bill' => (int) $row->bill,
                ];
            }
        }

        return [
            'title' => 'Timesheets awaiting approval',
            'total' => $this->pendingApprovalQuery($propertyIds)->count(),
            'rows' => $timesheets->map(function (Timesheet $sheet) use ($totals, $now): array {
                $totals_ = $totals[(int) $sheet->payroll_period_id] ?? ['minutes' => 0, 'bill' => 0];
                $waitingSince = $sheet->sent_for_approval_at?->toImmutable();
                $overdue = $waitingSince !== null
                    && $waitingSince->lessThan($now->subDays(self::APPROVAL_SLA_DAYS));

                return [
                    'id' => $sheet->id,
                    'property' => $sheet->property?->name,
                    'week' => $sheet->payrollPeriod?->week_start->format('M j'),
                    'recruiter' => $sheet->sentForApprovalBy?->name,
                    'hours' => round($totals_['minutes'] / 60, 1),
                    'billable' => $this->dollars($totals_['bill']),
                    'waiting' => $waitingSince !== null ? $this->shortAge($waitingSince, $now) : null,
                    'overdue' => $overdue,
                    'href' => "/admin/timesheets/{$sheet->id}",
                ];
            })->all(),
            'viewAllHref' => '/admin/timesheets',
            'emptyText' => 'Nothing is waiting on a property manager.',
        ];
    }

    /**
     * Who is on the clock right now, gathered by property, plus today's GPS
     * flags. Note that blocked clock-ins are not counted here — a blocked
     * attempt never becomes a time entry, so there is nothing to count.
     *
     * @param  list<int>|null  $propertyIds
     * @return array<string, mixed>
     */
    private function onTheClock(?array $propertyIds): array
    {
        $open = TimeEntry::query()
            ->whereNotNull('start_at_utc')
            ->whereNull('end_at_utc')
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->with('property:id,name')
            ->get(['id', 'property_id']);

        $byProperty = $open
            ->groupBy(fn (TimeEntry $entry): string => $entry->property->name)
            ->map(fn ($group): int => $group->count())
            ->sortDesc();

        $peak = max(1, $byProperty->max() ?? 1);

        $flagged = TimeEntry::query()
            ->whereDate('start_at_utc', CarbonImmutable::now()->toDateString())
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
            ->where(fn (Builder $q) => $q->whereNotNull('clock_in_gps_flag_reason')->orWhereNotNull('clock_out_gps_flag_reason'))
            ->count();

        return [
            'title' => 'On the clock now',
            'total' => $open->count(),
            'rows' => $byProperty->map(fn (int $count, string $name): array => [
                'property' => $name,
                'count' => $count,
                'fill' => round($count / $peak, 4),
            ])->values()->all(),
            'flaggedToday' => $flagged,
            'viewAllHref' => '/admin/timesheets',
            'emptyText' => 'Nobody is clocked in right now.',
        ];
    }

    /**
     * Things with a clock on them that are not part of the billing chain.
     *
     * @param  list<int>|null  $propertyIds
     * @return array<string, mixed>|null
     */
    private function decisions(Person $user, ?array $propertyIds): ?array
    {
        $rows = [];

        if ($user->can('bible.contracts.view')) {
            $expiring = Contract::query()->expiringWithin(14)
                ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
                ->with('property:id,name')
                ->orderBy('expiration_date')
                ->get();

            if ($expiring->isNotEmpty()) {
                $rows[] = [
                    'icon' => 'file-text',
                    'tone' => 'danger',
                    'label' => $expiring->count().' '.($expiring->count() === 1 ? 'contract expires' : 'contracts expire').' in 14 days',
                    'sublabel' => $expiring->take(2)->map(fn (Contract $c): string => (string) $c->property?->name)->implode(' · '),
                    'href' => '/admin/properties',
                ];
            }
        }

        if ($user->can('people.applicants.view')) {
            $inReview = JobApplication::query()->pending()->count();

            if ($inReview > 0) {
                $rows[] = [
                    'icon' => 'user-plus',
                    'tone' => 'default',
                    'label' => $inReview.' '.($inReview === 1 ? 'application' : 'applications').' in review',
                    'sublabel' => 'Waiting on a recruiter or HR',
                    'href' => '/admin/applicants',
                ];
            }
        }

        if ($user->can('workflows.pto.approve')) {
            $pto = PtoRequest::query()->pending()->count();

            if ($pto > 0) {
                $rows[] = [
                    'icon' => 'sun-high',
                    'tone' => 'default',
                    'label' => $pto.' PTO '.($pto === 1 ? 'request' : 'requests').' pending',
                    'sublabel' => 'Hours already deducted at submission',
                    'href' => '/admin/pto',
                ];
            }
        }

        if ($user->can('field_visits.view_all')) {
            $open = FieldVisit::query()
                ->open()
                ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
                ->count();

            if ($open > 0) {
                $rows[] = [
                    'icon' => 'map-pin',
                    'tone' => 'warning',
                    'label' => $open.' field '.($open === 1 ? 'visit' : 'visits').' left open',
                    'sublabel' => 'Recruiters never checked out',
                    'href' => '/admin/field-visits',
                ];
            }
        }

        if ($rows === []) {
            return null;
        }

        return ['title' => 'Needs a decision soon', 'rows' => $rows];
    }

    // ------------------------------------------------------------------
    // shared queries
    // ------------------------------------------------------------------

    /**
     * @param  list<int>|null  $propertyIds
     * @return Builder<Timesheet>
     */
    private function pendingApprovalQuery(?array $propertyIds): Builder
    {
        return Timesheet::query()
            ->where('status', TimesheetStatus::PendingApproval->value)
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds));
    }

    /**
     * Invoices that exist and are frozen but nobody has sent yet. Approval
     * generates the invoice immediately ({@see ApproveTimesheet}), so "approved
     * but not billed" is never a real resting state — this is.
     *
     * @param  list<int>|null  $propertyIds
     * @return array{0: int, 1: int}
     */
    private function readyToSend(?array $propertyIds): array
    {
        $invoices = Invoice::query()
            ->where('status', InvoiceStatus::Invoiced->value)
            ->whereNull('notification_sent_at')
            ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds));

        return [(int) $invoices->clone()->count(), (int) $invoices->clone()->sum('total')];
    }

    /**
     * Billable hours week-to-date, against the SAME point in last week rather
     * than all of it — comparing a partial week to a full one reads as a crash
     * every Monday. Training time is excluded (paid, never billed).
     *
     * @param  list<int>|null  $propertyIds
     * @return array{0: int, 1: int}
     */
    private function weekHours(?array $propertyIds): array
    {
        $now = CarbonImmutable::now();
        $weekStart = $now->startOfWeek(CarbonImmutable::MONDAY);
        $elapsed = (int) $weekStart->diffInSeconds($now);

        $sumBetween = function (CarbonImmutable $from, CarbonImmutable $to) use ($propertyIds): int {
            $minutes = TimeEntry::query()
                ->where('entry_type', 'work')
                ->whereNotNull('start_at_utc')
                ->whereBetween('start_at_utc', [$from, $to])
                ->when($propertyIds !== null, fn (Builder $q) => $q->whereIn('property_id', $propertyIds))
                ->sum('duration_minutes');

            return (int) round(((int) $minutes) / 60);
        };

        $lastWeekStart = $weekStart->subWeek();

        return [
            $sumBetween($weekStart, $now),
            $sumBetween($lastWeekStart, $lastWeekStart->addSeconds($elapsed)),
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function monthRevenue(): array
    {
        $sumFor = fn (CarbonImmutable $month): int => (int) Invoice::query()
            ->where('status', '!=', InvoiceStatus::Voided->value)
            ->whereDate('issue_date', '>=', $month->startOfMonth()->toDateString())
            ->whereDate('issue_date', '<=', $month->endOfMonth()->toDateString())
            ->sum('total');

        $now = CarbonImmutable::now();

        return [$sumFor($now), $sumFor($now->subMonth())];
    }

    private function workflowIcon(?WorkflowType $type): string
    {
        return match ($type) {
            WorkflowType::SupplyRequest => 'package',
            WorkflowType::PayIncrease => 'trending-up',
            WorkflowType::Termination => 'user-x',
            WorkflowType::MoreStaff => 'users-plus',
            WorkflowType::Transfer, WorkflowType::TemporaryAssignment => 'arrows-exchange',
            WorkflowType::ChangePersonalInfo => 'user-edit',
            default => 'checklist',
        };
    }

    /** "4h", "2d", "3w" — compact enough for a right-aligned column. */
    private function shortAge(CarbonImmutable $since, CarbonImmutable $now): string
    {
        $minutes = (int) $since->diffInMinutes($now);

        if ($minutes < 60) {
            return max(1, $minutes).'m';
        }

        if ($minutes < 1440) {
            return (int) floor($minutes / 60).'h';
        }

        if ($minutes < 10080) {
            return (int) floor($minutes / 1440).'d';
        }

        return (int) floor($minutes / 10080).'w';
    }

    private function dollars(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
