<?php

namespace Database\Seeders;

use App\Domain\PropertyBible\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Predefined department catalog (20-domain/property-bible.md §2).
 * Extensible by super_admin later; these are the starting set.
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Housekeeping',
            'Banquets',
            'Food & Beverage',
            'Public Spaces',
            'Kitchen / Culinary',
        ];

        foreach ($departments as $name) {
            Department::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );
        }
    }
}
