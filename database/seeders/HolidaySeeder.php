<?php

namespace Database\Seeders;

use App\Domain\PropertyBible\Enums\HolidayType;
use App\Domain\PropertyBible\Models\Holiday;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Database\Seeder;

/**
 * Seeds the legal holiday calendar (20-domain/property-bible.md) and attaches
 * the default set to every existing property. Idempotent — safe to re-run.
 */
class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Holiday::LEGAL_HOLIDAYS as $slug => [$name, $rule]) {
            Holiday::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'type' => HolidayType::Legal, 'rule' => $rule],
            );
        }

        $defaultIds = Holiday::query()->whereIn('slug', Holiday::DEFAULT_ENABLED_SLUGS)->pluck('id')->all();

        Property::query()->each(function (Property $property) use ($defaultIds): void {
            $property->holidays()->syncWithoutDetaching($defaultIds);
        });
    }
}
