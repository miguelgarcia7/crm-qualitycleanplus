<?php

namespace App\Domain\People\Support;

use App\Domain\Inventory\Enums\SupplyRequestStatus;
use App\Domain\Inventory\Models\SupplyRequest;
use App\Domain\People\Models\Person;
use App\Domain\Pto\Actions\EnsurePtoYear;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Enums\PtoTier;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Services\PtoTenure;
use App\Domain\SystemReference\Support\AutomationCatalog;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use Carbon\CarbonImmutable;

/**
 * What a staff member looks after for themselves, shown on My Profile: their
 * time off (balances, days off coming up, when the next tier starts) and the
 * requests they're waiting on. Every W-2 role has a profile, so this serves the
 * plain `w2_employee` and the office roles alike.
 */
class StaffSelfService
{
    private const LIMIT = 4;

    public function __construct(
        private readonly EnsurePtoYear $ensureYear,
        private readonly PtoTenure $tenure,
    ) {}

    /** @return array<string, mixed> */
    public function for(Person $person): array
    {
        return [
            'time_off' => $this->timeOff($person),
            'requests' => $this->requests($person),
        ];
    }

    /** @return array<string, mixed>|null */
    private function timeOff(Person $person): ?array
    {
        // Only staff with a hire date accrue time off (ADR-0016).
        if (! $person->can('pto.balances.view_own') || ! $person->status->isStaff() || $person->hire_date === null) {
            return null;
        }

        $today = CarbonImmutable::now(AutomationCatalog::DISPLAY_TIMEZONE)->startOfDay();
        $allotment = $this->ensureYear->handle($person);

        return [
            'balances' => collect(PtoBucket::cases())->map(fn (PtoBucket $b): array => [
                'label' => $b->label(),
                'available' => $allotment->availableFor($b),
                'pending' => $allotment->pendingFor($b),
                'allotted' => $allotment->allotmentFor($b),
            ])->all(),
            'upcoming' => PtoRequest::query()
                ->where('person_id', $person->id)
                ->whereIn('status', [PtoRequestStatus::Pending->value, PtoRequestStatus::Approved->value])
                ->whereDate('end_date', '>=', $today->toDateString())
                ->orderBy('start_date')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (PtoRequest $r): array => [
                    'id' => $r->id,
                    'bucket' => $r->bucket->label(),
                    'start' => $r->start_date->toDateString(),
                    'end' => $r->end_date->toDateString(),
                    'hours' => (float) $r->hours,
                    'status' => $r->status->value,
                ])->all(),
            'next_tier' => $this->nextTier($person, $today),
            'can_request' => $person->can('workflows.pto.initiate'),
        ];
    }

    /**
     * The next hire-date milestone that raises their time off: 30 days,
     * 6 months, then a year. Null once they're at the top tier.
     *
     * @return array{on: string, label: string, hours: int, top: bool}|null
     */
    private function nextTier(Person $person, CarbonImmutable $today): ?array
    {
        $hire = CarbonImmutable::parse($person->hire_date?->toDateString(), $today->timezone);

        foreach ([['on' => $hire->addDays(30), 'label' => '30 days'], ['on' => $hire->addMonthsNoOverflow(6), 'label' => '6 months'], ['on' => $hire->addYear(), 'label' => '1 year']] as $milestone) {
            if ($milestone['on']->gt($today)) {
                $tier = $this->tenure->tierAsOf($person, $milestone['on']);

                return ['on' => $milestone['on']->toDateString(), 'label' => $milestone['label'], 'hours' => $tier->hoursPerBucket(), 'top' => $tier === PtoTier::Tier3];
            }
        }

        return null;
    }

    /**
     * Requests this person is waiting on: a change to their details with HR,
     * and supplies they asked for that haven't been handed over.
     *
     * @return list<array<string, mixed>>
     */
    private function requests(Person $person): array
    {
        $infoChanges = Workflow::query()
            ->where('type', WorkflowType::ChangePersonalInfo->value)
            ->where('initiator_id', $person->id)
            ->where('status', WorkflowStatus::InProgress->value)
            ->latest('id')
            ->get()
            ->map(function (Workflow $w): array {
                $fields = collect(array_keys($w->data['changes'] ?? []))->map(fn (string $field): string => str_replace('_', ' ', $field));

                return [
                    'kind' => 'info_change',
                    'title' => 'Change to your '.($fields->isEmpty() ? 'details' : $fields->join(', ', ' and ')),
                    'asked' => $w->created_at?->toDateString(),
                    'status' => 'With HR',
                ];
            });

        $supplies = SupplyRequest::query()
            ->where('requested_by', $person->id)
            ->whereIn('status', [SupplyRequestStatus::Pending->value, SupplyRequestStatus::Approved->value])
            ->with('itemVariant.item:id,name')
            ->latest('id')
            ->get()
            ->map(fn (SupplyRequest $r): array => [
                'kind' => 'supply',
                'title' => trim($r->quantity.' × '.($r->itemVariant->item->name ?? $r->proposed_item_name ?? 'Supplies').($r->itemVariant !== null && $r->itemVariant->label() !== 'Default' ? ' ('.$r->itemVariant->label().')' : '')),
                'asked' => $r->created_at?->toDateString(),
                'status' => $r->status === SupplyRequestStatus::Approved ? 'Approved, waiting to be handed over' : 'Waiting for approval',
            ]);

        return $infoChanges->concat($supplies)->take(self::LIMIT)->values()->all();
    }
}
