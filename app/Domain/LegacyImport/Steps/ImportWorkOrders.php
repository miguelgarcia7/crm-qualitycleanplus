<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `work_orders` → `work_orders` (phase-final-cutover.md).
 *
 * Every row imports, soft-deleted included: deleted or ended work orders become
 * status `closed` with deleted_at left NULL — archived state, not deletion, so
 * two years of invoice history keeps resolving. Rates are cents on both sides;
 * OT columns are filled at 1.5× base (verified downstream against the frozen
 * legacy invoice snapshots). The legacy probationary_period is HOURS; this
 * app's direct-hire threshold is minutes.
 */
class ImportWorkOrders
{
    private const DEFAULT_THRESHOLD_HOURS = 2080;

    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $legacy = DB::connection('legacy');
        $stats = ['imported' => 0, 'updated' => 0, 'skipped_incomplete' => 0];
        $today = now()->toDateString();

        DB::transaction(function () use ($legacy, $today, &$stats): void {
            foreach ($legacy->table('work_orders')->orderBy('id')->get() as $wo) {
                $personId = $this->map->newId('person', $wo->contractor_id !== null ? (int) $wo->contractor_id : null);
                $propertyId = $this->map->newId('property', $wo->property_id !== null ? (int) $wo->property_id : null);
                $positionId = $this->map->newId('position', $wo->job_type_id !== null ? (int) $wo->job_type_id : null);

                if ($personId === null || $propertyId === null || $positionId === null) {
                    $stats['skipped_incomplete']++;

                    continue;
                }

                $startDate = Carbon::parse($wo->start_date)->toDateString();
                $endDate = $wo->end_date !== null ? Carbon::parse($wo->end_date)->toDateString() : null;

                $isClosed = $wo->deleted_at !== null || ($endDate !== null && $endDate < $today);

                $payRate = (int) $wo->contractor_rate;
                $billRate = (int) $wo->property_rate;

                $row = [
                    'person_id' => $personId,
                    'property_id' => $propertyId,
                    'position_id' => $positionId,
                    'pay_rate' => $payRate,
                    'bill_rate' => $billRate,
                    'ot_pay_rate' => (int) round($payRate * 1.5),
                    'ot_bill_rate' => (int) round($billRate * 1.5),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $isClosed ? 'closed' : 'active',
                    'direct_hire_threshold_minutes' => ((int) ($wo->probationary_period ?? self::DEFAULT_THRESHOLD_HOURS)) * 60,
                    'source' => 'imported',
                ];

                $existingId = $this->map->newId('work_order', (int) $wo->id);

                if ($existingId !== null) {
                    DB::table('work_orders')->where('id', $existingId)->update($row);
                    $stats['updated']++;
                } else {
                    $newId = (int) DB::table('work_orders')->insertGetId(
                        $row + ['created_at' => $wo->created_at, 'updated_at' => $wo->updated_at],
                    );
                    $this->map->remember('work_order', (int) $wo->id, $newId);
                    $stats['imported']++;
                }
            }
        });

        return $stats;
    }
}
