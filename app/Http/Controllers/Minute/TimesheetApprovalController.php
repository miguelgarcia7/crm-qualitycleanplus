<?php

namespace App\Http\Controllers\Minute;

use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\Billing\Actions\ApproveTimesheet;
use App\Domain\Billing\Actions\DeclineTimesheet;
use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Billing\Support\TimesheetWeekTotals;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Policies\PropertyPolicy;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\Time\Support\WeekWorkOrders;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Http\Controllers\Controller;
use App\Support\CompanyTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Property-manager timesheets on the QC Minute surface: every week the
 * recruiter has sent them, the week in progress, and Approve / Decline.
 *
 * Bill side only. A property manager never sees what a contractor is paid, or
 * their rates (workflows.md, pay_increase).
 */
class TimesheetApprovalController extends Controller
{
    /** Status filters on the list, as the property manager reads them. */
    private const FILTERS = [
        'all' => null,
        'waiting' => [TimesheetStatus::PendingApproval],
        'declined' => [TimesheetStatus::Declined],
        // Approval creates the invoice in the same transaction, so "approved"
        // is only ever a moment on the way to invoiced.
        'invoiced' => [TimesheetStatus::Approved, TimesheetStatus::Invoiced, TimesheetStatus::InvoiceSent],
    ];

    /** Drafts are still the recruiter's; everything after "sent" is the PM's history. */
    private const SENT = [
        TimesheetStatus::PendingApproval,
        TimesheetStatus::Declined,
        TimesheetStatus::Approved,
        TimesheetStatus::Invoiced,
        TimesheetStatus::InvoiceSent,
        TimesheetStatus::Voided,
    ];

    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Timesheet::class);

        /** @var Person $user */
        $user = $request->user();
        $properties = $this->properties($user);

        $status = $request->string('status')->value();
        $status = array_key_exists($status, self::FILTERS) ? $status : 'all';
        $propertyId = $request->integer('property') ?: null;
        $propertyId = $properties->contains('id', $propertyId) ? $propertyId : null;
        $shown = $propertyId === null ? $properties : $properties->where('id', $propertyId)->values();

        $base = Timesheet::query()
            ->whereIn('timesheets.status', array_map(fn (TimesheetStatus $s): string => $s->value, self::SENT))
            ->whereIn('timesheets.property_id', $shown->pluck('id')->all());

        $page = (clone $base)
            ->when(self::FILTERS[$status] !== null, fn (Builder $q) => $q->whereIn('timesheets.status', array_map(fn (TimesheetStatus $s): string => $s->value, self::FILTERS[$status])))
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->select('timesheets.*')
            ->with($this->listRelations())
            ->orderByDesc('payroll_periods.week_start')
            ->orderByDesc('timesheets.id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // Oldest first: the week that has waited longest is the one to do.
        $waiting = (clone $base)
            ->where('timesheets.status', TimesheetStatus::PendingApproval->value)
            ->with($this->listRelations())
            ->orderBy('sent_for_approval_at')
            ->get();

        $totals = TimesheetWeekTotals::forPeriods(
            collect($page->items())->concat($waiting)->pluck('payroll_period_id'),
        );
        $toRow = fn (Timesheet $t): array => $this->toRow($t, $totals->get($t->payroll_period_id), $user);

        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return Inertia::render('minute/timesheets/index', [
            'waiting' => $waiting->map($toRow)->all(),
            'timesheets' => collect($page->items())->map($toRow)->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'counts' => collect(self::FILTERS)->map(fn (?array $statuses): int => $statuses === null
                ? (int) $byStatus->sum()
                : (int) collect($statuses)->sum(fn (TimesheetStatus $s): int => (int) ($byStatus[$s->value] ?? 0))),
            'filters' => ['status' => $status, 'property' => $propertyId],
            'properties' => $properties->map(fn (Property $p): array => ['id' => $p->id, 'name' => $p->name])->all(),
            'this_week' => $shown->map(fn (Property $p): array => $this->weekSoFar($p))->all(),
        ]);
    }

    public function show(Request $request, Timesheet $timesheet): Response
    {
        $this->authorize('view', $timesheet);

        $timesheet->load('property', 'payrollPeriod', 'invoice:id,invoice_number', 'sentForApprovalBy:id,name', 'approvedBy:id,name', 'declinedBy:id,name');

        /** @var Person $user */
        $user = $request->user();

        return $this->weekPage($timesheet->property, $timesheet->payrollPeriod->week_start, $timesheet->payrollPeriod, $timesheet, $user);
    }

    /** The week in progress at one of the PM's properties, live and read-only (ADR-0007). */
    public function thisWeek(Request $request): Response
    {
        $this->authorize('viewAny', Timesheet::class);

        /** @var Person $user */
        $user = $request->user();
        $properties = $this->properties($user);
        $property = $properties->firstWhere('id', $request->integer('property')) ?? $properties->first();
        abort_if($property === null, 404);

        $weekStart = $property->weekStartFor(CarbonImmutable::now($property->timezone));
        $period = $this->periodFor($property, $weekStart);
        $timesheet = $period?->timesheet?->load('invoice:id,invoice_number', 'sentForApprovalBy:id,name', 'approvedBy:id,name', 'declinedBy:id,name');

        return $this->weekPage($property, $weekStart, $period, $timesheet, $user);
    }

    public function approve(Request $request, Timesheet $timesheet, ApproveTimesheet $action): RedirectResponse
    {
        $this->authorize('approve', $timesheet);

        $user = $request->user();
        abort_unless($user instanceof Person, 403);
        $action->handle($timesheet, $user);

        // Back to the week, which now shows who approved it and links its invoice.
        return to_route('qcminute.timesheets.show', $timesheet);
    }

    public function decline(Request $request, Timesheet $timesheet, DeclineTimesheet $action): RedirectResponse
    {
        $this->authorize('decline', $timesheet);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        abort_unless($user instanceof Person, 403);
        $action->handle($timesheet, $user, $validated['reason'], $validated['category'] ?? null);

        return to_route('qcminute.timesheets.show', $timesheet);
    }

    /**
     * The properties this person can see timesheets for.
     *
     * @return Collection<int, Property>
     */
    private function properties(Person $user): Collection
    {
        return Property::query()
            ->when(! $user->hasAnyRole(PropertyPolicy::GLOBAL_ROLES), fn (Builder $q) => $q->assignedTo($user))
            ->orderBy('name')
            ->get();
    }

    /** @return list<string> */
    private function listRelations(): array
    {
        return [
            'property:id,name,timezone',
            'payrollPeriod:id,week_start,week_end',
            'invoice:id,invoice_number,subtotal',
            'sentForApprovalBy:id,name',
            'approvedBy:id,name',
            'declinedBy:id,name',
        ];
    }

    private function periodFor(Property $property, CarbonImmutable $weekStart): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->whereDate('week_start', $weekStart->toDateString())
            ->first();
    }

    /**
     * @param  array{people: int, total_minutes: int, overtime_minutes: int, holiday_minutes: int, amount: int}|null  $totals
     * @return array<string, mixed>
     */
    private function toRow(Timesheet $timesheet, ?array $totals, Person $user): array
    {
        $period = $timesheet->payrollPeriod;
        $waiting = $timesheet->status === TimesheetStatus::PendingApproval && $timesheet->sent_for_approval_at !== null;

        return [
            'id' => $timesheet->id,
            'property' => $timesheet->property?->name,
            'week_start' => $period->week_start->toDateString(),
            'week_end' => $period->week_end->toDateString(),
            'status' => $timesheet->status->value,
            'sent_at' => CompanyTime::of($timesheet->sent_for_approval_at)?->toDateString(),
            'sent_by' => $timesheet->sentForApprovalBy?->name,
            'waiting_days' => $waiting ? (int) $timesheet->sent_for_approval_at->diffInDays(now(), absolute: true) : null,
            'approved_at' => CompanyTime::of($timesheet->approved_at)?->toDateString(),
            'approved_by' => $this->who($timesheet->approvedBy, $user),
            'declined_at' => $timesheet->status === TimesheetStatus::Declined ? CompanyTime::of($timesheet->declined_at)?->toDateString() : null,
            'declined_by' => $timesheet->status === TimesheetStatus::Declined ? $this->who($timesheet->declinedBy, $user) : null,
            'decline_reason' => $timesheet->status === TimesheetStatus::Declined ? $timesheet->decline_reason : null,
            'people' => $totals['people'] ?? 0,
            'total_minutes' => $totals['total_minutes'] ?? 0,
            'overtime_minutes' => $totals['overtime_minutes'] ?? 0,
            'holiday_minutes' => $totals['holiday_minutes'] ?? 0,
            // Once invoiced, the frozen invoice is the truth (ADR-0006).
            'amount' => $timesheet->invoice->subtotal ?? $totals['amount'] ?? 0,
            'invoice_id' => $timesheet->invoice_id,
            'invoice_number' => $timesheet->invoice?->invoice_number,
        ];
    }

    /** "you" when the viewer did it, so the list reads "You approved Oct 5". */
    private function who(?Person $person, Person $user): ?string
    {
        if ($person === null) {
            return null;
        }

        return $person->id === $user->id ? 'you' : $person->name;
    }

    /** @return array<string, mixed> */
    private function weekSoFar(Property $property): array
    {
        $weekStart = $property->weekStartFor(CarbonImmutable::now($property->timezone));
        $period = $this->periodFor($property, $weekStart);

        return [
            'property_id' => $property->id,
            'property' => $property->name,
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekStart->addDays(6)->toDateString(),
            'total_minutes' => $period === null ? 0 : (int) TimeSummary::query()->where('payroll_period_id', $period->id)
                ->selectRaw('SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as minutes')
                ->value('minutes'),
            'on_the_clock' => $period === null ? 0 : TimeEntry::query()->where('payroll_period_id', $period->id)
                ->whereNull('end_at_utc')->distinct()->count('work_order_id'),
        ];
    }

    private function weekPage(Property $property, CarbonImmutable $weekStart, ?PayrollPeriod $period, ?Timesheet $timesheet, Person $user): Response
    {
        $tz = $property->timezone;
        $today = CarbonImmutable::now($tz)->toDateString();

        return Inertia::render('minute/timesheets/show', [
            'timesheet' => $timesheet === null ? null : [
                'id' => $timesheet->id,
                'status' => $timesheet->status->value,
                'sent_at' => CompanyTime::of($timesheet->sent_for_approval_at)?->toDateString(),
                'sent_by' => $timesheet->sentForApprovalBy?->name,
                'approved_at' => CompanyTime::of($timesheet->approved_at)?->toDateString(),
                'approved_by' => $this->who($timesheet->approvedBy, $user),
                'declined_at' => CompanyTime::of($timesheet->declined_at)?->toDateString(),
                'declined_by' => $this->who($timesheet->declinedBy, $user),
                'decline_reason' => $timesheet->decline_reason,
                'decline_category' => $timesheet->decline_category,
                'invoice' => $timesheet->invoice === null ? null : ['id' => $timesheet->invoice->id, 'number' => $timesheet->invoice->invoice_number],
            ],
            'property' => ['id' => $property->id, 'name' => $property->name, 'timezone' => $tz],
            'week' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekStart->addDays(6)->toDateString(),
                'days' => collect(range(0, 6))->map(fn (int $i): string => $weekStart->addDays($i)->toDateString()),
                'is_current' => $weekStart->toDateString() <= $today && $today <= $weekStart->addDays(6)->toDateString(),
            ],
            'rows' => WeekWorkOrders::for($property, $weekStart, $period)->map(fn (WorkOrder $wo): array => [
                'work_order_id' => $wo->id,
                'person_id' => $wo->person_id,
                'contractor' => $wo->person?->name,
                'position' => $wo->position?->name,
            ]),
            'entries' => $period === null ? [] : TimeEntry::query()
                ->where('payroll_period_id', $period->id)
                ->get()
                ->map(fn (TimeEntry $e): array => [
                    'id' => $e->id,
                    'work_order_id' => $e->work_order_id,
                    'date' => $e->start_at_utc?->copy()->setTimezone($tz)->toDateString(),
                    'start_time' => $e->start_at_utc?->copy()->setTimezone($tz)->format('H:i'),
                    'end_time' => $e->end_at_utc?->copy()->setTimezone($tz)->format('H:i'),
                    'duration_minutes' => $e->duration_minutes,
                ])
                ->values(),
            'summaries' => $period === null ? (object) [] : TimeSummary::query()
                ->where('payroll_period_id', $period->id)
                ->get()
                ->keyBy('work_order_id')
                ->map(fn (TimeSummary $s): array => [
                    'regular_minutes' => $s->regular_minutes,
                    'overtime_minutes' => $s->overtime_minutes,
                    'holiday_minutes' => $s->holiday_minutes,
                    'training_minutes' => $s->training_minutes,
                    'total_bill' => $s->total_bill,
                ]),
            // Only what the property is billed for; deductions are payroll's business.
            'adjustments' => $period === null ? [] : TimeEntryAdjustment::query()
                ->where('payroll_period_id', $period->id)
                ->where('is_billable', true)
                ->with('adjustmentItem:id,name')
                ->get()
                ->map(fn (TimeEntryAdjustment $a): array => [
                    'id' => $a->id,
                    'person_id' => $a->person_id,
                    'work_order_id' => $a->work_order_id,
                    'label' => $a->adjustmentItem->name ?? $a->notes ?? 'Adjustment',
                    'value' => $a->value,
                ])
                ->values(),
            'can' => ['decide' => $timesheet !== null && $timesheet->status->canDecide() && $user->can('approve', $timesheet)],
        ]);
    }
}
