<?php

namespace App\Domain\Dashboards\Services;

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\Devices\Models\Device;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyTimeSource;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The Overview tab of a property's page: this week's figures, the last few
 * weeks, and the setup gaps and stalled weeks someone should act on.
 * Read-only; each part is gated by the permission that guards its full page
 * (the grid, the timesheet history, contracts, …), so the overview never
 * shows more than the viewer could open elsewhere.
 */
class PropertyOverview
{
    private const RECENT_WEEKS = 6;

    /** Matches the property-manager dashboard's contract warning. */
    private const CONTRACT_WARNING_DAYS = 30;

    /**
     * @return array{thisWeek: array<string, mixed>|null, weeks: list<array<string, mixed>>|null, attention: list<array<string, mixed>>}
     */
    public function build(Property $property, Person $user): array
    {
        return [
            'thisWeek' => $user->can('time_entries.view') ? $this->thisWeek($property) : null,
            'weeks' => $user->can('timesheets.view_history') ? $this->recentWeeks($property) : null,
            'attention' => $this->attention($property, $user),
        ];
    }

    /**
     * The current pay week, as the grid's summary bar shows it.
     *
     * @return array<string, mixed>
     */
    private function thisWeek(Property $property): array
    {
        $today = CarbonImmutable::now($property->timezone);
        $start = $property->weekStartFor($today);

        $period = PayrollPeriod::query()
            ->where('property_id', $property->id)
            ->whereDate('week_start', $start->toDateString())
            ->with('timesheet')
            ->first();

        $totals = $period === null ? null : $this->totalsByPeriod($property, [$period->id])->get($period->id);

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $start->addDays(6)->toDateString(),
            'status' => $period?->timesheet?->status->value,
            'status_label' => $period?->timesheet?->status->label(),
            ...$this->totalsPayload($totals),
            'needs_look' => $period === null ? 0 : $this->needsLook($period, $today),
        ];
    }

    /**
     * Punches with no clock-out from an earlier day, or recorded without
     * verified GPS — the grid's "Needs a look" count.
     */
    private function needsLook(PayrollPeriod $period, CarbonImmutable $today): int
    {
        return TimeEntry::query()
            ->where('payroll_period_id', $period->id)
            ->where(fn ($q) => $q
                ->whereNotNull('clock_in_gps_flag_reason')
                ->orWhereNotNull('clock_out_gps_flag_reason')
                ->orWhere(fn ($q) => $q->whereNull('end_at_utc')->where('start_at_utc', '<', $today->startOfDay()->utc())))
            ->count();
    }

    /**
     * The latest timesheets that have started, newest first.
     *
     * @return list<array<string, mixed>>
     */
    private function recentWeeks(Property $property): array
    {
        $today = CarbonImmutable::now($property->timezone)->toDateString();

        $timesheets = Timesheet::query()
            ->where('timesheets.property_id', $property->id)
            ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
            ->whereDate('payroll_periods.week_start', '<=', $today)
            ->select('timesheets.*', 'payroll_periods.week_start as period_start', 'payroll_periods.week_end as period_end')
            ->with('invoice:id,invoice_number')
            ->orderByDesc('payroll_periods.week_start')
            ->limit(self::RECENT_WEEKS)
            ->get();

        $totals = $this->totalsByPeriod($property, $timesheets->pluck('payroll_period_id')->all());

        return $timesheets->map(fn (Timesheet $t): array => [
            'id' => $t->id,
            'week_start' => CarbonImmutable::parse($t->getAttribute('period_start'))->toDateString(),
            'week_end' => CarbonImmutable::parse($t->getAttribute('period_end'))->toDateString(),
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            ...$this->totalsPayload($totals->get($t->payroll_period_id)),
            'invoice_number' => $t->invoice?->invoice_number,
            'sent_for_approval_at' => $t->sent_for_approval_at?->toDateString(),
        ])->values()->all();
    }

    /**
     * Hour and money totals per payroll period, from the time summaries.
     *
     * @param  list<int>  $periodIds
     * @return Collection<int|string, stdClass>
     */
    private function totalsByPeriod(Property $property, array $periodIds): Collection
    {
        if ($periodIds === []) {
            return collect();
        }

        return DB::table('time_summaries')
            ->where('property_id', $property->id)
            ->whereIn('payroll_period_id', $periodIds)
            ->groupBy('payroll_period_id')
            ->select([
                'payroll_period_id',
                // People with any time logged; a pay increase can give one contractor two work orders.
                DB::raw('COUNT(DISTINCT CASE WHEN regular_minutes + overtime_minutes + holiday_minutes + training_minutes > 0 THEN person_id END) as people'),
                DB::raw('SUM(regular_minutes + overtime_minutes + holiday_minutes + training_minutes) as total_minutes'),
                DB::raw('SUM(overtime_minutes) as overtime_minutes'),
                DB::raw('SUM(total_pay) as total_pay'),
                DB::raw('SUM(total_bill) as total_bill'),
            ])
            ->get()
            ->keyBy('payroll_period_id');
    }

    /**
     * @return array{people: int, total_minutes: int, overtime_minutes: int, total_pay: int, total_bill: int}
     */
    private function totalsPayload(?stdClass $totals): array
    {
        return [
            'people' => (int) ($totals->people ?? 0),
            'total_minutes' => (int) ($totals->total_minutes ?? 0),
            'overtime_minutes' => (int) ($totals->overtime_minutes ?? 0),
            'total_pay' => (int) ($totals->total_pay ?? 0),
            'total_bill' => (int) ($totals->total_bill ?? 0),
        ];
    }

    /**
     * What needs someone: weeks stuck in approval, and gaps in the property's
     * setup. Each item names where to fix it — an `href`, or a tab (and
     * Settings section) on the property page.
     *
     * @return list<array<string, mixed>>
     */
    private function attention(Property $property, Person $user): array
    {
        $items = [];

        if ($user->can('time_entries.view')) {
            $stalled = Timesheet::query()
                ->where('timesheets.property_id', $property->id)
                ->whereIn('timesheets.status', [TimesheetStatus::PendingApproval->value, TimesheetStatus::Declined->value])
                ->join('payroll_periods', 'payroll_periods.id', '=', 'timesheets.payroll_period_id')
                ->select('timesheets.*', 'payroll_periods.week_start as period_start')
                ->orderBy('payroll_periods.week_start')
                ->get();

            foreach ($stalled as $timesheet) {
                $weekStart = CarbonImmutable::parse($timesheet->getAttribute('period_start'));
                $declined = $timesheet->status === TimesheetStatus::Declined;

                $items[] = [
                    'key' => "timesheet-{$timesheet->id}",
                    'title' => 'Week of '.$weekStart->format('M j').($declined ? ' was declined' : ' is waiting on approval'),
                    'detail' => $declined
                        ? ($timesheet->decline_reason ?: 'Fix the hours and send it again.')
                        : 'Sent '.($timesheet->sent_for_approval_at?->format('M j') ?? '—').', '.$timesheet->sent_for_approval_at?->diffForHumans().'.',
                    'action' => ['label' => 'Open week', 'href' => "/admin/properties/{$property->id}/grid?week={$weekStart->toDateString()}"],
                ];
            }
        }

        if ($property->time_source === PropertyTimeSource::ClockIn) {
            if ($property->latitude === null || $property->longitude === null) {
                $items[] = [
                    'key' => 'geofence',
                    'title' => 'No geofence set',
                    'detail' => "QR clock-in can't be turned on until the property has coordinates.",
                    'action' => ['label' => 'Set location', 'href' => "/admin/properties/{$property->id}/edit"],
                ];
            }

            if ($user->can('devices.manage')) {
                Device::query()
                    ->where('property_id', $property->id)
                    ->where('is_activated', false)
                    ->orderBy('name')
                    ->get(['id', 'name', 'activation_code'])
                    ->each(function (Device $device) use (&$items): void {
                        $items[] = [
                            'key' => "device-{$device->id}",
                            'title' => "{$device->name} is not activated",
                            'detail' => "Enter code {$device->activation_code} on the tablet to pair it.",
                            'action' => ['label' => 'Devices', 'href' => '/admin/devices'],
                        ];
                    });
            }
        }

        if ($user->can('viewRates', $property) && $property->configuredPositions()->isEmpty()) {
            $items[] = [
                'key' => 'rates',
                'title' => 'No current rates',
                'detail' => "New work orders can't be created here until a position has a rate.",
                'action' => ['label' => 'Rates', 'tab' => 'rates'],
            ];
        }

        if ($user->can('viewDepartments', $property) && ! $property->departments()->exists()) {
            $items[] = [
                'key' => 'departments',
                'title' => 'No department managers on file',
                'detail' => 'Add each department and who runs it at the hotel.',
                'action' => ['label' => 'Contacts', 'tab' => 'settings', 'section' => 'contacts'],
            ];
        }

        if ($user->can('bible.contracts.view')) {
            if (! $property->contracts()->where('is_active', true)->exists()) {
                $items[] = [
                    'key' => 'contract',
                    'title' => 'No contract on file',
                    'detail' => 'Upload the signed agreement for this property.',
                    'action' => ['label' => 'Contracts', 'tab' => 'settings', 'section' => 'contracts'],
                ];
            }

            Contract::query()
                ->where('property_id', $property->id)
                ->expiringWithin(self::CONTRACT_WARNING_DAYS)
                ->orderBy('expiration_date')
                ->get()
                ->each(function (Contract $contract) use (&$items): void {
                    $items[] = [
                        'key' => "contract-{$contract->id}",
                        'title' => "{$contract->name} expires ".$contract->expiration_date?->format('M j'),
                        'detail' => 'Renew it or upload the new agreement.',
                        'action' => ['label' => 'Contracts', 'tab' => 'settings', 'section' => 'contracts'],
                    ];
                });
        }

        return $items;
    }
}
