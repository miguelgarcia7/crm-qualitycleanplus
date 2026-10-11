<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Devices\Models\Device;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What needs someone at each property: weeks stuck in approval, and gaps in
 * its setup. One set of rules for the property page's Overview (one property)
 * and the properties list (all of them) — each check is one query for the
 * whole set, never one per property.
 *
 * Each item names where to fix it: an `href`, or a tab (and Settings section)
 * on the property page. Checks are gated by the permission behind the page
 * they summarise. Callers pass only properties the viewer may see, so the
 * "(own)" scoping is already done.
 */
class PropertyAttention
{
    /** Matches the property-manager dashboard's contract warning. */
    private const CONTRACT_WARNING_DAYS = 30;

    /**
     * @param  Collection<int, Property>  $properties
     * @return array<int, list<array<string, mixed>>> items per property id, in display order
     */
    public function forMany(Collection $properties, Person $user): array
    {
        $ids = $properties->pluck('id')->all();
        $items = array_fill_keys($ids, []);

        if ($ids === []) {
            return $items;
        }

        if ($user->can('time_entries.view')) {
            $this->stalledWeeks($ids)->each(function (Timesheet $timesheet) use (&$items): void {
                $weekStart = CarbonImmutable::parse($timesheet->getAttribute('period_start'));
                $declined = $timesheet->status === TimesheetStatus::Declined;

                $items[$timesheet->property_id][] = [
                    'key' => "timesheet-{$timesheet->id}",
                    'title' => 'Week of '.$weekStart->format('M j').($declined ? ' was declined' : ' is waiting on approval'),
                    'detail' => $declined
                        ? ($timesheet->decline_reason ?: 'Fix the hours and send it again.')
                        : 'Sent '.($timesheet->sent_for_approval_at?->format('M j') ?? '—').', '.$timesheet->sent_for_approval_at?->diffForHumans().'.',
                    'action' => ['label' => 'Open week', 'href' => "/admin/properties/{$timesheet->property_id}/grid?week={$weekStart->toDateString()}"],
                ];
            });
        }

        $clockIn = $properties->filter(fn (Property $p): bool => $p->time_source === PropertyTimeSource::ClockIn);

        foreach ($clockIn as $property) {
            if ($property->latitude === null || $property->longitude === null) {
                $items[$property->id][] = [
                    'key' => 'geofence',
                    'title' => 'No geofence set',
                    'detail' => "QR clock-in can't be turned on until the property has coordinates.",
                    'action' => ['label' => 'Set location', 'tab' => 'settings', 'section' => 'clockin'],
                ];
            }
        }

        if ($user->can('devices.manage') && $clockIn->isNotEmpty()) {
            Device::query()
                ->whereIn('property_id', $clockIn->pluck('id'))
                ->where('is_activated', false)
                ->orderBy('name')
                ->get(['id', 'property_id', 'name', 'activation_code'])
                ->each(function (Device $device) use (&$items): void {
                    $items[$device->property_id][] = [
                        'key' => "device-{$device->id}",
                        'title' => "{$device->name} is not activated",
                        'detail' => "Enter code {$device->activation_code} on the tablet to pair it.",
                        'action' => ['label' => 'Devices', 'href' => '/admin/devices'],
                    ];
                });
        }

        if ($user->can('bible.rates.view')) {
            // Same rule as Property::configuredPositions(): an active rate already in effect.
            $priced = PropertyPositionRate::query()
                ->whereIn('property_id', $ids)
                ->where('is_active', true)
                ->whereDate('effective_date', '<=', now())
                ->whereHas('position', fn ($q) => $q->where('is_active', true))
                ->distinct()
                ->pluck('property_id')
                ->all();

            foreach (array_diff($ids, $priced) as $id) {
                $items[$id][] = [
                    'key' => 'rates',
                    'title' => 'No current rates',
                    'detail' => "New work orders can't be created here until a position has a rate.",
                    'action' => ['label' => 'Rates', 'tab' => 'rates'],
                ];
            }
        }

        if ($user->can('bible.departments.view')) {
            $withDepartments = PropertyDepartment::query()->whereIn('property_id', $ids)->distinct()->pluck('property_id')->all();

            foreach (array_diff($ids, $withDepartments) as $id) {
                $items[$id][] = [
                    'key' => 'departments',
                    'title' => 'No department managers on file',
                    'detail' => 'Add each department and who runs it at the hotel.',
                    'action' => ['label' => 'Contacts', 'tab' => 'settings', 'section' => 'contacts'],
                ];
            }
        }

        if ($user->can('bible.contracts.view')) {
            $withContract = Contract::query()->whereIn('property_id', $ids)->where('is_active', true)->distinct()->pluck('property_id')->all();

            foreach (array_diff($ids, $withContract) as $id) {
                $items[$id][] = [
                    'key' => 'contract',
                    'title' => 'No contract on file',
                    'detail' => 'Upload the signed agreement for this property.',
                    'action' => ['label' => 'Contracts', 'tab' => 'settings', 'section' => 'contracts'],
                ];
            }

            Contract::query()
                ->whereIn('property_id', $ids)
                ->expiringWithin(self::CONTRACT_WARNING_DAYS)
                ->orderBy('expiration_date')
                ->get()
                ->each(function (Contract $contract) use (&$items): void {
                    $items[$contract->property_id][] = [
                        'key' => "contract-{$contract->id}",
                        'title' => "{$contract->name} expires ".$contract->expiration_date?->format('M j'),
                        'detail' => 'Renew it or upload the new agreement.',
                        'action' => ['label' => 'Contracts', 'tab' => 'settings', 'section' => 'contracts'],
                    ];
                });
        }

        return $items;
    }

    /**
     * Timesheets waiting on the property manager or sent back to us, oldest
     * week first.
     *
     * @param  list<int>  $propertyIds
     * @return Collection<int, Timesheet>
     */
    private function stalledWeeks(array $propertyIds): Collection
    {
        return Timesheet::query()
            ->whereIn('timesheets.property_id', $propertyIds)
            ->whereIn('timesheets.status', [TimesheetStatus::PendingApproval->value, TimesheetStatus::Declined->value])
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->select('timesheets.*', 'payroll_periods.week_start as period_start')
            ->orderBy('payroll_periods.week_start')
            ->get();
    }
}
