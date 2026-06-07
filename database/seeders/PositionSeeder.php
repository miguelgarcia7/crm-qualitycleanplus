<?php

namespace Database\Seeders;

use App\Domain\PropertyBible\Models\Position;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter global position catalog (20-domain/property-bible.md §3).
 * Per-property rates are set against these in property_position_rates.
 */
class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Housekeeper',
            'Houseman',
            'Banquet Server',
            'Dishwasher',
            'Janitor',
            'Public Space Attendant',
            'Laundry Attendant',
            'Cook',
        ];

        foreach ($positions as $name) {
            Position::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
