<?php

namespace App\Domain\LegacyImport\Steps;

use App\Domain\LegacyImport\Support\LegacyIdMap;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Legacy `properties` → `properties`, plus the property-scoped satellites that
 * only need people and properties to exist: `property_user` → `property_assignments`
 * and `holidays`/`property_holiday` (phase-final-cutover.md).
 *
 * The legacy parent/child hierarchy is deliberately flattened — every child was
 * its own billing unit with its own punches and invoices, so each imports as a
 * top-level property and `parent_id` is dropped. Soft-deleted properties come
 * over as status `inactive`, never skipped (the archive-vs-history rule).
 */
class ImportProperties
{
    public function __construct(private readonly LegacyIdMap $map) {}

    /** @return array<string, int> */
    public function handle(): array
    {
        $legacy = DB::connection('legacy');

        $properties = $legacy->table('properties')->orderBy('id')->get();
        $pivotRows = $legacy->table('property_user')->get();
        $managers = $this->firstManagerByProperty($pivotRows);

        $stats = ['imported' => 0, 'updated' => 0, 'assignments' => 0, 'holidays' => 0, 'holiday_links' => 0];

        DB::transaction(function () use ($legacy, $properties, $pivotRows, $managers, &$stats): void {
            foreach ($properties as $property) {
                // Legacy stores a float percent (8.25); this app stores a fraction (0.0825).
                $taxRate = round(((float) $property->tax_rate) / 100, 4);
                $closingDay = ($property->closing_day >= 1 && $property->closing_day <= 31)
                    ? (int) $property->closing_day
                    : null;

                $row = [
                    'name' => trim($property->name),
                    'pm_name' => $managers[$property->id]->name ?? null,
                    'pm_phone' => isset($managers[$property->id]->phone) ? mb_substr($managers[$property->id]->phone, 0, 32) : null,
                    'main_phone' => $property->phone !== null ? mb_substr($property->phone, 0, 32) : null,
                    'address' => $property->address,
                    'city' => $property->city !== null ? mb_substr(trim($property->city), 0, 255) : null,
                    'state' => $property->state !== null ? mb_substr($property->state, 0, 64) : null,
                    'zip' => $property->zip !== null ? mb_substr($property->zip, 0, 16) : null,
                    'timezone' => $property->timezone ?: 'America/Chicago',
                    'latitude' => $property->latitude,
                    'longitude' => $property->longitude,
                    'geofence_radius_meters' => (int) ($property->geofence_radius_meters ?? 300),
                    'qr_token' => $property->qr_token,
                    'qr_clock_enabled' => (bool) $property->qr_clock_enabled,
                    'closing_day' => $closingDay,
                    'tax_rate' => $taxRate,
                    'status' => $property->deleted_at === null ? 'active' : 'inactive',
                    'time_source' => 'clock_in', // the legacy app is punch-based throughout
                ];

                $existingId = $this->map->newId('property', (int) $property->id);

                if ($existingId !== null) {
                    DB::table('properties')->where('id', $existingId)->update($row);
                    $stats['updated']++;
                } else {
                    $newId = (int) DB::table('properties')->insertGetId(
                        $row + ['created_at' => $property->created_at, 'updated_at' => $property->updated_at],
                    );
                    $this->map->remember('property', (int) $property->id, $newId);
                    $stats['imported']++;
                }
            }

            $stats['assignments'] = $this->importAssignments($pivotRows);
            [$stats['holidays'], $stats['holiday_links']] = $this->importHolidays($legacy);
        });

        return $stats;
    }

    /**
     * `property_user` → `property_assignments`. Legacy `employee_manager` is
     * this app's `recruiter`; the pivot's `contractor` value was never written
     * by the legacy app (contractor linkage flows through work orders).
     *
     * @param  Collection<int, \stdClass>  $pivotRows
     */
    private function importAssignments($pivotRows): int
    {
        $roleMap = ['property_manager' => 'property_manager', 'employee_manager' => 'recruiter'];
        $count = 0;

        foreach ($pivotRows as $pivot) {
            $role = $roleMap[$pivot->role] ?? null;
            $propertyId = $this->map->newId('property', (int) $pivot->property_id);
            $personId = $this->map->newId('person', (int) $pivot->user_id);

            if ($role === null || $propertyId === null || $personId === null) {
                continue;
            }

            DB::table('property_assignments')->updateOrInsert(
                ['property_id' => $propertyId, 'person_id' => $personId, 'role' => $role],
                ['updated_at' => now(), 'created_at' => $pivot->created_at ?? now()],
            );
            $count++;
        }

        return $count;
    }

    /**
     * The legacy holiday catalog has the same shape as this app's (it is where
     * the design came from) — upsert by slug, then link per property exactly as
     * legacy had it. HolidaySeeder can still run afterwards; it firstOrCreates
     * by slug and only adds defaults.
     *
     * @return array{int, int}
     */
    private function importHolidays(Connection $legacy): array
    {
        $holidays = 0;
        $links = 0;

        foreach ($legacy->table('holidays')->get() as $holiday) {
            $newId = DB::table('holidays')->where('slug', $holiday->slug)->value('id');

            if ($newId === null) {
                $newId = DB::table('holidays')->insertGetId([
                    'name' => $holiday->name,
                    'slug' => $holiday->slug,
                    'type' => $holiday->type,
                    'rule' => $holiday->rule,
                    'month' => $holiday->month,
                    'day' => $holiday->day,
                    'created_at' => $holiday->created_at ?? now(),
                    'updated_at' => $holiday->updated_at ?? now(),
                ]);
                $holidays++;
            }

            $this->map->remember('holiday', (int) $holiday->id, (int) $newId);
        }

        foreach ($legacy->table('property_holiday')->get() as $link) {
            $propertyId = $this->map->newId('property', (int) $link->property_id);
            $holidayId = $this->map->newId('holiday', (int) $link->holiday_id);

            if ($propertyId === null || $holidayId === null) {
                continue;
            }

            DB::table('property_holiday')->updateOrInsert(
                ['property_id' => $propertyId, 'holiday_id' => $holidayId],
                ['updated_at' => now(), 'created_at' => $link->created_at ?? now()],
            );
            $links++;
        }

        return [$holidays, $links];
    }

    /**
     * First property-manager user per legacy property, for the pm_name/pm_phone
     * display columns.
     *
     * @param  Collection<int, \stdClass>  $pivotRows
     * @return array<int, \stdClass>
     */
    private function firstManagerByProperty($pivotRows): array
    {
        $managerIds = $pivotRows->where('role', 'property_manager')->pluck('user_id')->unique();

        $users = DB::connection('legacy')->table('users')
            ->whereIn('id', $managerIds)
            ->get()
            ->keyBy('id');

        $result = [];

        foreach ($pivotRows->where('role', 'property_manager')->sortBy('id') as $pivot) {
            if (! isset($result[$pivot->property_id]) && $users->has($pivot->user_id)) {
                $result[$pivot->property_id] = $users->get($pivot->user_id);
            }
        }

        return $result;
    }
}
