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
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Person::class);

        /** @var Person $user */
        $user = $request->user();

        return Inertia::render('admin/people/index', [
            'contractors' => $user->can('people.contractors.view') ? $this->contractorRows($user) : null,
            'staff' => $user->can('people.staff.view') ? $this->staffRows() : null,
            'canInvite' => $user->can('admin.users.create'),
        ]);
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
     * Everyone on the contractor side of the lifecycle (active through
     * terminated) — recruiters scoped to their own (ADR-0019).
     *
     * @return list<array<string, mixed>>
     */
    private function contractorRows(Person $user): array
    {
        $query = Person::query()
            ->whereIn('status', [
                PersonStatus::ContractorActive->value,
                PersonStatus::ContractorInactive->value,
                PersonStatus::PendingTermination->value,
                PersonStatus::Terminated->value,
            ])
            ->with(['primaryRecruiter:id,name', 'workOrders.property:id,name', 'workOrders.position:id,name'])
            ->orderBy('name');

        if (! $user->hasAnyRole(PersonPolicy::GLOBAL_CONTRACTOR_VIEWERS)) {
            $query->where('primary_recruiter_id', $user->id);
        }

        return $query->get()
            ->map(function (Person $p): array {
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
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffRows(): array
    {
        return Person::query()
            ->whereIn('status', [PersonStatus::StaffActive->value, PersonStatus::StaffInactive->value])
            ->with('roles:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Person $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'email' => $p->email,
                'phone' => $p->phone,
                'status' => $p->status->value,
                'status_label' => Str::headline($p->status->value),
                'avatar' => $p->avatarUrl(),
                'hire_date' => $p->hire_date?->format('M j, Y'),
                'roles' => $p->getRoleNames()->map(fn (string $r): string => Str::headline($r))->values()->all(),
            ])
            ->all();
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
