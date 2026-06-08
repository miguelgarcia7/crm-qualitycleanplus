<?php

namespace Database\Seeders;

use App\Domain\Inventory\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The three default inventory categories (ADR-0012). Idempotent — safe in
 * production. Office managers may add more categories later.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Office Supplies', 'slug' => 'office_supplies', 'has_variants' => false],
            ['name' => 'Uniforms', 'slug' => 'uniforms', 'has_variants' => true],
            ['name' => 'Equipment', 'slug' => 'equipment', 'has_variants' => false],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => $category['slug']],
                ['name' => $category['name'], 'has_variants' => $category['has_variants'], 'active' => true],
            );
        }
    }
}
