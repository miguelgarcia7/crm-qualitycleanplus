<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `work_time_records` (≈69k) → `time_entries` (phase-final-cutover.md).
 *
 * Source is `imported` with the punch method preserved (`kiosk` → tablet,
 * `qr` → qr); rate snapshots come from the imported work order (legacy changed
 * rates by creating a new work order, so the per-WO rate is stable — verified
 * downstream against frozen invoice snapshots). Soft-deleted punches carry
 * their deleted_at: voided data stays excluded from sums but visible in
 * history. Open punches (no clock-out) import open.
 *
 * Idempotency: this step is wipe-and-reload — rows are identified by
 * source_metadata.legacy_table and hard-deleted before re-insert, so no
 * per-row id map is needed (nothing downstream references an entry by id).
 * Weeks without a legacy timesheet get their payroll period created here,
 * anchored by the property's own closing-day cadence.
 */
class ImportTimeEntries
{
    private const LEGACY_TABLE = 'work_time_records';

    private const CHUNK = 1000;

    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $stats = [
            'imported' => 0, 'training_rows' => 0, 'open_punches' => 0,
            'deleted_carried' => 0, 'periods_created' => 0, 'skipped_unmapped' => 0,
            'property_reattached' => 0, 'billed_rate_used' => 0,
        ];

        DB::table('time_entries')
            ->where('source', 'imported')
            ->where('source_metadata->legacy_table', self::LEGACY_TABLE)
            ->delete();

        $people = $this->map->all('person');
        $properties = $this->map->all('property');
        $workOrders = $this->map->all('work_order');

        $woRates = DB::table('work_orders')
            ->whereIn('id', array_values($workOrders))
            ->get(['id', 'property_id', 'pay_rate', 'bill_rate', 'ot_pay_rate', 'ot_bill_rate'])
            ->keyBy('id');

        $propertyModels = Property::query()->whereIn('id', array_values($properties))->get()->keyBy('id');
        $periods = $this->periodsByProperty();
        $today = now()->toDateString();

        // Rates as actually billed: legacy work-order rates were edited in
        // place over time, so an invoiced punch snapshots the frozen invoice
        // item's rates for its (invoice, work order); only uninvoiced punches
        // fall back to the work order's current rates.
        $billedRates = [];

        foreach (DB::connection('legacy')->table('invoice_items')->get(['invoice_id', 'work_order_id', 'rate', 'contractor_rate', 'overtime_rate']) as $item) {
            $billedRates[$item->invoice_id.':'.$item->work_order_id] = $item;
        }

        DB::connection('legacy')->table('work_time_records')->orderBy('id')
            ->chunk(self::CHUNK, function ($records) use ($people, $properties, $workOrders, $woRates, $billedRates, $propertyModels, &$periods, $today, &$stats): void {
                $rows = [];

                foreach ($records as $wtr) {
                    $personId = $people[$wtr->contractor_id] ?? null;
                    $punchPropertyId = $properties[$wtr->property_id] ?? null;
                    $workOrderId = $workOrders[$wtr->work_order_id] ?? null;
                    $rates = $workOrderId !== null ? $woRates->get($workOrderId) : null;

                    if ($personId === null || $punchPropertyId === null || $rates === null) {
                        $stats['skipped_unmapped']++;

                        continue;
                    }

                    // A few legacy punches were recorded at a sibling property of
                    // the work order's (the parent/child era). Summaries and
                    // periods key on the WO's property here, so the entry follows
                    // the WO; the punch property stays in the audit metadata.
                    $propertyId = (int) $rates->property_id;

                    if ($propertyId !== $punchPropertyId) {
                        $stats['property_reattached']++;
                    }

                    $localDate = Carbon::parse($wtr->start_time)->toDateString();
                    $periodId = $this->periodFor($periods, $propertyModels, $propertyId, $localDate, $today, $stats);

                    $durationMinutes = null;

                    if ($wtr->end_time_utc !== null) {
                        $durationMinutes = max(0, intdiv(
                            Carbon::parse($wtr->end_time_utc)->getTimestamp() - Carbon::parse($wtr->start_time_utc)->getTimestamp(),
                            60,
                        ));
                    } else {
                        $stats['open_punches']++;
                    }

                    if ($wtr->deleted_at !== null) {
                        $stats['deleted_carried']++;
                    }

                    $training = (int) ($wtr->training_minutes ?? 0);

                    $billed = $wtr->invoice_id !== null
                        ? ($billedRates[$wtr->invoice_id.':'.$wtr->work_order_id] ?? null)
                        : null;
                    $payRate = (int) ($billed->contractor_rate ?? $rates->pay_rate);
                    $billRate = (int) ($billed->rate ?? $rates->bill_rate);

                    if ($billed !== null) {
                        $stats['billed_rate_used']++;
                    }

                    $base = [
                        'person_id' => $personId,
                        'work_order_id' => $workOrderId,
                        'property_id' => $propertyId,
                        'payroll_period_id' => $periodId,
                        'source' => 'imported',
                        'clock_method' => $wtr->method === 'qr' ? 'qr' : 'tablet',
                        'timezone' => $wtr->timezone ?: null,
                        'pay_rate_snapshot' => $payRate,
                        'bill_rate_snapshot' => $billRate,
                        'ot_pay_rate_snapshot' => (int) round($payRate * 1.5),
                        'ot_bill_rate_snapshot' => (int) ($billed->overtime_rate ?? round($billRate * 1.5)),
                        'was_updated' => (bool) $wtr->was_updated,
                        'deleted_at' => $wtr->deleted_at,
                        'created_at' => $wtr->created_at,
                        'updated_at' => $wtr->updated_at,
                    ];

                    $rows[] = $base + [
                        'entry_type' => 'work',
                        'start_at_utc' => $wtr->start_time_utc,
                        'end_at_utc' => $wtr->end_time_utc,
                        'duration_minutes' => $durationMinutes !== null ? max(0, $durationMinutes - $training) : null,
                        'clock_in_gps_lat' => $wtr->clock_in_gps_lat,
                        'clock_in_gps_lng' => $wtr->clock_in_gps_lng,
                        'clock_in_gps_accuracy_meters' => $wtr->clock_in_gps_accuracy_m !== null ? (int) round((float) $wtr->clock_in_gps_accuracy_m) : null,
                        'clock_in_gps_flag_reason' => $wtr->clock_in_gps_flag_reason,
                        'clock_out_gps_lat' => $wtr->clock_out_gps_lat,
                        'clock_out_gps_lng' => $wtr->clock_out_gps_lng,
                        'clock_out_gps_accuracy_meters' => $wtr->clock_out_gps_accuracy_m !== null ? (int) round((float) $wtr->clock_out_gps_accuracy_m) : null,
                        'clock_out_gps_flag_reason' => $wtr->clock_out_gps_flag_reason,
                        'source_metadata' => json_encode([
                            'legacy_table' => self::LEGACY_TABLE,
                            'legacy_id' => (int) $wtr->id,
                            'clock_out_method' => $wtr->clock_out_method,
                            'punch_reason' => $wtr->punch_reason,
                            'clock_out_reason' => $wtr->clock_out_reason,
                            'legacy_device_id' => $wtr->device_id,
                        ] + ($propertyId !== $punchPropertyId ? ['legacy_property_id' => (int) $wtr->property_id] : [])),
                    ];
                    $stats['imported']++;

                    // Legacy folded training minutes into the shift; here they are
                    // their own duration-only entry at the training bucket. Bulk
                    // insert() needs identical keys on every row, so the clock and
                    // GPS columns are present but null.
                    if ($training > 0) {
                        $rows[] = $base + [
                            'entry_type' => 'training',
                            'start_at_utc' => null,
                            'end_at_utc' => null,
                            'duration_minutes' => $training,
                            'clock_in_gps_lat' => null,
                            'clock_in_gps_lng' => null,
                            'clock_in_gps_accuracy_meters' => null,
                            'clock_in_gps_flag_reason' => null,
                            'clock_out_gps_lat' => null,
                            'clock_out_gps_lng' => null,
                            'clock_out_gps_accuracy_meters' => null,
                            'clock_out_gps_flag_reason' => null,
                            'source_metadata' => json_encode([
                                'legacy_table' => self::LEGACY_TABLE,
                                'legacy_id' => (int) $wtr->id,
                                'split' => 'training_minutes',
                            ]),
                        ];
                        $stats['training_rows']++;
                    }
                }

                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('time_entries')->insert($chunk);
                }
            });

        return $stats;
    }

    /** @return array<int, array<string, array{id: int, week_end: string}>> property id => week_start => period */
    private function periodsByProperty(): array
    {
        $result = [];

        foreach (DB::table('payroll_periods')->get(['id', 'property_id', 'week_start', 'week_end']) as $period) {
            $result[(int) $period->property_id][$period->week_start] = [
                'id' => (int) $period->id,
                'week_end' => $period->week_end,
            ];
        }

        return $result;
    }

    /**
     * @param  array<int, array<string, array{id: int, week_end: string}>>  $periods
     * @param  Collection<int, Property>  $propertyModels
     * @param  array<string, int>  $stats
     */
    private function periodFor(array &$periods, $propertyModels, int $propertyId, string $localDate, string $today, array &$stats): int
    {
        foreach ($periods[$propertyId] ?? [] as $weekStart => $period) {
            if ($weekStart <= $localDate && $localDate <= $period['week_end']) {
                return $period['id'];
            }
        }

        /** @var Property $property */
        $property = $propertyModels->get($propertyId);
        $weekStart = $property->weekStartFor($localDate)->toDateString();
        $weekEnd = $property->weekStartFor($localDate)->addDays(6)->toDateString();

        $id = (int) DB::table('payroll_periods')->insertGetId([
            'property_id' => $propertyId,
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'status' => $weekEnd >= $today ? 'open' : 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $periods[$propertyId][$weekStart] = ['id' => $id, 'week_end' => $weekEnd];
        $stats['periods_created']++;

        return $id;
    }
}
