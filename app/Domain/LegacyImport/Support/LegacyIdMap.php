<?php

namespace App\Domain\LegacyImport\Support;

use Illuminate\Support\Facades\DB;

/**
 * Read/write access to the legacy_id_map crosswalk, cached per entity so import
 * steps can resolve parents (a time entry's contractor, an invoice's property)
 * without a query per row.
 */
class LegacyIdMap
{
    /** @var array<string, array<int, int>> entity => [legacy_id => new_id] */
    private array $cache = [];

    public function newId(string $entity, ?int $legacyId): ?int
    {
        if ($legacyId === null) {
            return null;
        }

        return $this->all($entity)[$legacyId] ?? null;
    }

    /** @return array<int, int> legacy_id => new_id */
    public function all(string $entity): array
    {
        return $this->cache[$entity] ??= DB::table('legacy_id_map')
            ->where('entity', $entity)
            ->pluck('new_id', 'legacy_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function remember(string $entity, int $legacyId, int $newId): void
    {
        DB::table('legacy_id_map')->updateOrInsert(
            ['entity' => $entity, 'legacy_id' => $legacyId],
            ['new_id' => $newId, 'updated_at' => now(), 'created_at' => now()],
        );

        if (isset($this->cache[$entity])) {
            $this->cache[$entity][$legacyId] = $newId;
        }
    }
}
