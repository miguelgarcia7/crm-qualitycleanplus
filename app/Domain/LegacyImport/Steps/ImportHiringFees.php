<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\LegacyImport\Support\PeopleMergeMap;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Legacy hiring fees → contractor charge schedules (phase-final-cutover.md).
 *
 * `users.hiring_fee` becomes a `hiring_fee` charge schedule; each historical
 * `contractor_fee_deductions` row becomes an applied schedule entry plus the
 * matching non-billable payroll deduction, so collection history is complete
 * and remaining balances resume on their own (AllocateChargeScheduleEntries
 * tops up active schedules as periods open).
 *
 * Status per the decision log: fully collected → completed; still-active
 * contractor with a balance → active; inactive contractor with a balance →
 * cancelled, with the remainder written off in the activity log using the same
 * `charge_written_off` vocabulary as ProcessFinalPaycheck. Data repairs: a fee
 * of 0 with deductions infers its total from what was collected; a per-payment
 * of 0 falls back to the configured default.
 */
class ImportHiringFees
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $legacy = DB::connection('legacy');
        $stats = [
            'schedules' => 0, 'entries' => 0, 'completed' => 0, 'active' => 0,
            'cancelled' => 0, 'written_off_cents' => 0, 'entries_skipped' => 0,
        ];

        $defaultPer = (int) config('qcp.hiring_fee.per_period_cents', 20_00);
        $losers = PeopleMergeMap::losers();

        // Fee terms per legacy user; merged duplicates contribute their fee only
        // when the survivor has none.
        $users = $legacy->table('users')
            ->where('hiring_fee', '>', 0)
            ->get(['id', 'hiring_fee', 'hiring_fee_deduction_per_timesheet'])
            ->keyBy('id');

        $deductions = $legacy->table('contractor_fee_deductions')->orderBy('week_start')->get();

        // Group everything by SURVIVOR legacy user id, so merged people end up
        // with one schedule covering the whole group's history.
        $feeByUser = [];
        $deductionsByUser = [];

        foreach ($users as $id => $user) {
            $survivor = $losers[$id] ?? (int) $id;
            $feeByUser[$survivor] ??= $user;
        }

        foreach ($deductions as $deduction) {
            $survivor = $losers[$deduction->contractor_id] ?? (int) $deduction->contractor_id;
            $deductionsByUser[$survivor][] = $deduction;
        }

        $allUserIds = array_unique([...array_keys($feeByUser), ...array_keys($deductionsByUser)]);

        $properties = Property::query()->whereIn(
            'id',
            array_values($this->map->all('property')),
        )->get()->keyBy('id');

        DB::transaction(function () use ($allUserIds, $feeByUser, $deductionsByUser, $defaultPer, $properties, &$stats): void {
            $this->wipePreviousRun();

            $personStatuses = DB::table('people')
                ->whereIn('id', array_values($this->map->all('person')))
                ->pluck('status', 'id');

            foreach ($allUserIds as $legacyUserId) {
                $personId = $this->map->newId('person', $legacyUserId);

                if ($personId === null) {
                    continue;
                }

                $rows = $deductionsByUser[$legacyUserId] ?? [];
                $collected = array_sum(array_map(fn ($d): int => (int) $d->amount, $rows));
                $fee = (int) ($feeByUser[$legacyUserId]->hiring_fee ?? 0);
                $total = $fee > 0 ? $fee : $collected; // fee of 0 with history: infer from collections

                if ($total <= 0) {
                    continue;
                }

                $per = (int) ($feeByUser[$legacyUserId]->hiring_fee_deduction_per_timesheet ?? 0);
                $per = min($per > 0 ? $per : $defaultPer, $total);

                $status = match (true) {
                    $collected >= $total => 'completed',
                    $personStatuses[$personId] === 'contractor_active' => 'active',
                    default => 'cancelled',
                };

                $scheduleId = (int) DB::table('contractor_charge_schedules')->insertGetId([
                    'person_id' => $personId,
                    'reason' => 'hiring_fee',
                    'total_amount' => $total,
                    'num_payments' => (int) ceil($total / $per),
                    'amount_per_payment' => $per,
                    'status' => $status,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->map->remember('hiring_schedule', $legacyUserId, $scheduleId);
                $stats['schedules']++;
                $stats[$status === 'completed' ? 'completed' : ($status === 'active' ? 'active' : 'cancelled')]++;

                $index = 1;

                foreach ($rows as $deduction) {
                    $periodId = $this->periodForDeduction($deduction, $properties);

                    if ($periodId === null) {
                        $stats['entries_skipped']++;

                        continue;
                    }

                    $propertyId = (int) DB::table('payroll_periods')->where('id', $periodId)->value('property_id');

                    $adjustmentId = (int) DB::table('time_entry_adjustments')->insertGetId([
                        'person_id' => $personId,
                        'work_order_id' => $this->map->newId('work_order', $deduction->work_order_id !== null ? (int) $deduction->work_order_id : null),
                        'property_id' => $propertyId,
                        'payroll_period_id' => $periodId,
                        'source_type' => 'import',
                        'value' => (int) $deduction->amount,
                        'type' => 'deduction',
                        'is_billable' => false,
                        'notes' => 'Hiring fee (legacy import)',
                        'created_at' => $deduction->created_at,
                        'updated_at' => $deduction->updated_at,
                    ]);

                    DB::table('contractor_charge_schedule_entries')->insert([
                        'schedule_id' => $scheduleId,
                        'payroll_period_id' => $periodId,
                        'amount' => (int) $deduction->amount,
                        'payment_index' => $index++,
                        'status' => 'applied',
                        'applied_adjustment_id' => $adjustmentId,
                        'created_at' => $deduction->created_at,
                        'updated_at' => $deduction->updated_at,
                    ]);
                    $stats['entries']++;
                }

                if ($status === 'cancelled') {
                    $remainder = $total - $collected;
                    $stats['written_off_cents'] += $remainder;

                    DB::table('activity_log')->insert([
                        'log_name' => 'inventory',
                        'description' => 'Uncollected contractor charge written off as QCP expense at import (inactive contractor)',
                        'subject_type' => Person::class,
                        'subject_id' => $personId,
                        'event' => 'charge_written_off',
                        'properties' => json_encode(['remainder_cents' => $remainder, 'legacy_import' => true]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return $stats;
    }

    /** Remove everything a previous run of this step created, so re-runs rebuild cleanly. */
    private function wipePreviousRun(): void
    {
        $previous = array_values($this->map->all('hiring_schedule'));

        if ($previous !== []) {
            DB::table('contractor_charge_schedules')->whereIn('id', $previous)->delete(); // entries cascade
        }

        DB::table('time_entry_adjustments')
            ->where('source_type', 'import')
            ->where('notes', 'Hiring fee (legacy import)')
            ->delete();

        DB::table('activity_log')
            ->where('event', 'charge_written_off')
            ->where('properties->legacy_import', true)
            ->delete();
    }

    /**
     * The payroll period a legacy deduction belongs to: via its timesheet when
     * linked, otherwise the canonical week at the work order's property.
     *
     * @param  Collection<int, Property>  $properties
     */
    private function periodForDeduction(\stdClass $deduction, $properties): ?int
    {
        $timesheetId = $this->map->newId('timesheet', $deduction->property_timesheet_id !== null ? (int) $deduction->property_timesheet_id : null);

        if ($timesheetId !== null) {
            return (int) DB::table('timesheets')->where('id', $timesheetId)->value('payroll_period_id');
        }

        $workOrderId = $this->map->newId('work_order', $deduction->work_order_id !== null ? (int) $deduction->work_order_id : null);

        if ($workOrderId === null || $deduction->week_start === null) {
            return null;
        }

        $propertyId = (int) DB::table('work_orders')->where('id', $workOrderId)->value('property_id');
        /** @var Property|null $property */
        $property = $properties->get($propertyId);

        if ($property === null) {
            return null;
        }

        $start = $property->weekStartFor(Carbon::parse($deduction->week_start)->toDateString());

        $existing = DB::table('payroll_periods')
            ->where('property_id', $propertyId)
            ->where('week_start', $start->toDateString())
            ->value('id');

        return $existing !== null
            ? (int) $existing
            : (int) DB::table('payroll_periods')->insertGetId([
                'property_id' => $propertyId,
                'week_start' => $start->toDateString(),
                'week_end' => $start->addDays(6)->toDateString(),
                'status' => 'closed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
