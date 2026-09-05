<?php

namespace App\Http\Controllers;

use App\Domain\Adjustments\Enums\ChargeEntryStatus;
use App\Domain\Adjustments\Enums\ChargeScheduleStatus;
use App\Domain\Adjustments\Models\ContractorChargeSchedule;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\People\Policies\PersonPolicy;
use App\Domain\Pto\Enums\PtoAllotmentStatus;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Models\PtoYearAllotment;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * People directory + person profiles (Phase 09b). Contractors and staff are
 * separately gated tabs; recruiters only see their own contractors
 * (PersonPolicy, ADR-0019). Applicants live in the Applicants section instead.
 */
class PeopleController extends Controller
{
    /** Statuses on the contractor side of the lifecycle (ADR-0019). */
    private const CONTRACTOR_STATUSES = [
        PersonStatus::ContractorActive->value,
        PersonStatus::ContractorInactive->value,
        PersonStatus::PendingTermination->value,
        PersonStatus::Terminated->value,
    ];

    private const STAFF_STATUSES = [
        PersonStatus::StaffActive->value,
        PersonStatus::StaffInactive->value,
    ];

    /**
     * Sortable column => SQL expression, per tab. A whitelist, so a hand-edited
     * query string cannot order by an arbitrary column.
     *
     * Properties and roles are deliberately absent: both are lists assembled
     * from related rows, and ordering by a comma-joined string is meaningless.
     *
     * @var array<string, array<string, string>>
     */
    private const SORTS = [
        'contractors' => [
            'name' => 'people.name',
            'phone' => 'people.phone',
            'recruiter' => 'recruiters.name',
            'status' => 'people.status',
        ],
        'staff' => [
            'name' => 'people.name',
            'phone' => 'people.phone',
            'hire_date' => 'people.hire_date',
            'status' => 'people.status',
        ],
    ];

    private const PER_PAGE = [10, 25, 50];

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Person::class);

        /** @var Person $user */
        $user = $request->user();

        $canContractors = $user->can('people.contractors.view');
        $canStaff = $user->can('people.staff.view');

        $filters = $this->filters($request, $canContractors, $canStaff);
        $onContractors = $filters['tab'] === 'contractors';

        // Only the tab on screen is fetched. Both lists used to load on every
        // visit, including the one nobody was looking at.
        $page = $onContractors
            ? $this->contractorQuery($filters, $user)->paginate($filters['per_page'])->withQueryString()
            : $this->staffQuery($filters)->paginate($filters['per_page'])->withQueryString();

        $rows = collect($page->items());

        return Inertia::render('admin/people/index', [
            'people' => $onContractors
                ? $rows->map($this->contractorRow(...))->all()
                : $rows->map($this->staffRow(...))->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
            'filters' => $filters,
            // Counts cover both tabs regardless of which one is showing, so the
            // header does not lie about the tab you are not on.
            'counts' => [
                'contractors' => $canContractors ? $this->contractorQuery($filters, $user, applyStatus: false)->count() : null,
                'staff' => $canStaff ? $this->staffQuery($filters, applyStatus: false)->count() : null,
            ],
            'statuses' => array_map(
                fn (string $status): array => ['value' => $status, 'label' => Str::headline($status)],
                $onContractors ? self::CONTRACTOR_STATUSES : self::STAFF_STATUSES,
            ),
            'can' => [
                'contractors' => $canContractors,
                'staff' => $canStaff,
                'invite' => $user->can('admin.users.create'),
            ],
        ]);
    }

    /**
     * The query string, normalised. The tab decides which sort whitelist
     * applies, so a sort valid on one tab cannot leak onto the other.
     *
     * @return array{tab: string, search: string, status: string, sort: string, direction: string, per_page: int}
     */
    private function filters(Request $request, bool $canContractors, bool $canStaff): array
    {
        // The tab is a permission boundary, not just a view: asking for a tab
        // you may not see falls back to one you may, rather than serving it.
        $requested = (string) $request->string('tab', 'contractors');
        $tab = match (true) {
            $requested === 'staff' && $canStaff => 'staff',
            $canContractors => 'contractors',
            $canStaff => 'staff',
            default => 'contractors', // viewAny already blocked anyone with neither
        };

        $allowedStatuses = $tab === 'contractors' ? self::CONTRACTOR_STATUSES : self::STAFF_STATUSES;
        $status = (string) $request->string('status');
        $sort = (string) $request->string('sort', 'name');
        $perPage = $request->integer('per_page', 25);

        return [
            'tab' => $tab,
            'search' => trim((string) $request->string('search')),
            'status' => in_array($status, $allowedStatuses, true) ? $status : '',
            'sort' => array_key_exists($sort, self::SORTS[$tab]) ? $sort : 'name',
            'direction' => $request->string('direction')->lower()->toString() === 'desc' ? 'desc' : 'asc',
            'per_page' => in_array($perPage, self::PER_PAGE, true) ? $perPage : 25,
        ];
    }

    /**
     * Everyone on the contractor side of the lifecycle — recruiters scoped to
     * their own (ADR-0019).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Person>
     */
    private function contractorQuery(array $filters, Person $user, bool $applyStatus = true): Builder
    {
        return Person::query()
            ->leftJoin('people as recruiters', 'recruiters.id', '=', 'people.primary_recruiter_id')
            ->whereIn('people.status', self::CONTRACTOR_STATUSES)
            ->when(
                ! $user->hasAnyRole(PersonPolicy::GLOBAL_CONTRACTOR_VIEWERS),
                fn (Builder $q) => $q->where('people.primary_recruiter_id', $user->id),
            )
            ->when($applyStatus && $filters['status'] !== '', fn (Builder $q) => $q->where('people.status', $filters['status']))
            ->when($filters['search'] !== '', fn (Builder $q) => $this->applySearch($q, $filters['search']))
            ->select('people.*')
            ->with(['primaryRecruiter:id,name', 'workOrders.property:id,name', 'workOrders.position:id,name'])
            ->orderBy(self::SORTS['contractors'][$filters['sort']] ?? 'people.name', $filters['direction'])
            ->orderBy('people.id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Person>
     */
    private function staffQuery(array $filters, bool $applyStatus = true): Builder
    {
        return Person::query()
            ->whereIn('people.status', self::STAFF_STATUSES)
            ->when($applyStatus && $filters['status'] !== '', fn (Builder $q) => $q->where('people.status', $filters['status']))
            ->when($filters['search'] !== '', fn (Builder $q) => $this->applySearch($q, $filters['search']))
            ->select('people.*')
            ->with('roles:id,name')
            ->orderBy(self::SORTS['staff'][$filters['sort']] ?? 'people.name', $filters['direction'])
            ->orderBy('people.id');
    }

    /**
     * @param  Builder<Person>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $like = '%'.$search.'%';

        $query->where(fn (Builder $w) => $w
            ->where('people.name', 'like', $like)
            ->orWhere('people.email', 'like', $like)
            ->orWhere('people.phone', 'like', $like));
    }

    /**
     * @return array<string, mixed>
     */
    private function contractorRow(Person $p): array
    {
        $active = $p->workOrders->filter(fn (WorkOrder $wo): bool => $wo->status === WorkOrderStatus::Active);

        return [
            'id' => $p->id,
            'name' => $p->name,
            'email' => $p->email,
            'phone' => $p->phone,
            'status' => $p->status->value,
            'status_label' => Str::headline($p->status->value),
            'avatar' => $p->avatarUrl(),
            'recruiter' => $p->primaryRecruiter?->name,
            'properties' => $active->map(fn (WorkOrder $wo): ?string => $wo->property?->name)->filter()->unique()->values()->all(),
            'positions' => $active->map(fn (WorkOrder $wo): ?string => $wo->position?->name)->filter()->unique()->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffRow(Person $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'email' => $p->email,
            'phone' => $p->phone,
            'status' => $p->status->value,
            'status_label' => Str::headline($p->status->value),
            'avatar' => $p->avatarUrl(),
            'hire_date' => $p->hire_date?->format('M j, Y'),
            'roles' => $p->getRoleNames()->map(fn (string $r): string => Str::headline($r))->values()->all(),
        ];
    }

    public function show(Request $request, Person $person): Response
    {
        $this->authorize('view', $person);

        /** @var Person $user */
        $user = $request->user();

        // Work orders, hours and adjustments are contractor-side concepts (a WO
        // places a contractor; hours/adjustments hang off their time entries).
        // Staff records — recruiters, OM, HR, etc. — never have them, so those
        // tabs are hidden for staff. PTO is the inverse: staff-only accrual.
        $isStaff = $person->status->isStaff();
        $canRates = $user->can('bible.rates.view');
        $canHours = ! $isStaff && $user->can('timesheets.view_history');
        $canAdjustments = ! $isStaff && $user->can('time_entries.add_adjustment');
        $canPto = $isStaff && $user->can('pto.balances.view_all');
        $canHistory = $user->can('audit.activity_log.view');

        return Inertia::render('admin/people/show', [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'email' => $person->email,
                'phone' => $person->phone,
                'status' => $person->status->value,
                'status_label' => Str::headline($person->status->value),
                'is_active' => $person->status->isActive(),
                'is_staff' => $person->status->isStaff(),
                'avatar' => $person->avatarUrl(),
                'city_state' => implode(', ', array_filter([$person->city, $person->state])),
                'hire_date' => $person->hire_date?->format('M j, Y'),
                'contractor_since' => $person->converted_to_contractor_at?->format('M j, Y'),
                'terminated_at' => $person->terminated_at?->format('M j, Y'),
                'recruiter' => $person->primaryRecruiter?->name,
                'roles' => $person->getRoleNames()->map(fn (string $r): string => Str::headline($r))->values()->all(),
            ],
            'workOrders' => $isStaff ? null : $this->workOrderRows($person, $canRates),
            'hours' => $canHours ? $this->hoursRows($person) : null,
            'adjustments' => $canAdjustments ? $this->adjustmentRows($person) : null,
            'charges' => $canAdjustments ? $this->chargeRows($person) : null,
            'pto' => $canPto ? $this->ptoPayload($person) : null,
            'history' => $canHistory ? $this->historyRows($person) : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function workOrderRows(Person $person, bool $withRates): array
    {
        return $person->workOrders()
            ->with(['property:id,name', 'position:id,name'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (WorkOrder $wo): array => [
                'id' => $wo->id,
                'property' => $wo->property?->name,
                'property_id' => $wo->property_id,
                'position' => $wo->position?->name,
                'status' => $wo->status->value,
                'start_date' => $wo->start_date->format('M j, Y'),
                'end_date' => $wo->end_date?->format('M j, Y'),
                'pay_rate' => $withRates ? $wo->pay_rate : null,
                'bill_rate' => $withRates ? $wo->bill_rate : null,
            ])
            ->all();
    }

    /**
     * Last 12 materialized weekly summaries (hours only — money stays in Reports).
     *
     * @return list<array<string, mixed>>
     */
    private function hoursRows(Person $person): array
    {
        return TimeSummary::query()
            ->where('person_id', $person->id)
            ->with(['property:id,name', 'workOrder.position:id,name'])
            ->orderByDesc('week_start')
            ->limit(12)
            ->get()
            ->map(fn (TimeSummary $s): array => [
                'id' => $s->id,
                'week_start' => $s->week_start->format('M j'),
                'week_end' => $s->week_end->format('M j, Y'),
                'property' => $s->property?->name,
                'position' => $s->workOrder?->position?->name,
                'regular_minutes' => $s->regular_minutes,
                'overtime_minutes' => $s->overtime_minutes,
                'other_minutes' => $s->holiday_minutes + $s->training_minutes,
            ])
            ->all();
    }

    /**
     * Deductions spread over pay periods — the hiring fee and any uniform
     * charges.
     *
     * @return list<array<string, mixed>>
     */
    private function chargeRows(Person $person): array
    {
        return ContractorChargeSchedule::query()
            ->where('person_id', $person->id)
            ->withCount(['entries as applied_count' => fn ($q) => $q->where('status', ChargeEntryStatus::Applied->value)])
            ->withSum(['entries as collected_amount' => fn ($q) => $q->where('status', ChargeEntryStatus::Applied->value)], 'amount')
            ->latest('id')
            ->get()
            ->map(fn (ContractorChargeSchedule $s): array => [
                'id' => $s->id,
                'reason' => $s->reason->value,
                'reason_label' => $s->reason->label(),
                'total_amount' => $s->total_amount,
                'amount_per_payment' => $s->amount_per_payment,
                'num_payments' => $s->num_payments,
                'applied_count' => (int) $s->applied_count,
                // Actually taken out of pay so far. The rest is either
                // scheduled against a future period or not yet allocated to
                // one, which is why this is not total minus scheduled.
                'collected_amount' => (int) ($s->collected_amount ?? 0),
                'status' => $s->status->value,
                'can_edit' => $s->status === ChargeScheduleStatus::Active,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function adjustmentRows(Person $person): array
    {
        return TimeEntryAdjustment::query()
            ->where('person_id', $person->id)
            ->with('adjustmentItem:id,name')
            ->latest()
            ->limit(25)
            ->get()
            ->map(fn (TimeEntryAdjustment $a): array => [
                'id' => $a->id,
                'item' => $a->adjustmentItem?->name,
                'type' => $a->type->value,
                'type_label' => $a->type->label(),
                'value' => $a->value,
                'is_billable' => (bool) $a->is_billable,
                'notes' => $a->notes,
                'created_at' => $a->created_at?->format('M j, Y'),
            ])
            ->all();
    }

    /**
     * Current open PTO allotment per bucket. Read-only: shows the open year if
     * one exists (the daily job / PTO page materialize them) — never creates one.
     *
     * @return array<string, mixed>|null
     */
    private function ptoPayload(Person $person): ?array
    {
        $allotment = PtoYearAllotment::query()
            ->where('person_id', $person->id)
            ->where('status', PtoAllotmentStatus::Open->value)
            ->orderByDesc('year_start')
            ->first();

        if ($allotment === null) {
            return null;
        }

        return [
            'year_start' => $allotment->year_start->format('M j, Y'),
            'year_end' => $allotment->year_end->format('M j, Y'),
            'buckets' => array_map(fn (PtoBucket $bucket): array => [
                'bucket' => $bucket->value,
                'label' => Str::headline($bucket->value),
                'allotted' => $allotment->allotmentFor($bucket),
                'used' => $allotment->reservedFor($bucket) + $allotment->pendingFor($bucket),
                'available' => $allotment->availableFor($bucket),
            ], PtoBucket::cases()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historyRows(Person $person): array
    {
        return Activity::query()
            ->where('subject_type', $person->getMorphClass())
            ->where('subject_id', $person->getKey())
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Activity $a): array => [
                'id' => $a->id,
                'description' => $a->description,
                'event' => $a->event,
                'causer' => $a->causer instanceof Person ? $a->causer->name : null,
                'created_at' => $a->created_at?->toDayDateTimeString(),
            ])
            ->all();
    }
}
