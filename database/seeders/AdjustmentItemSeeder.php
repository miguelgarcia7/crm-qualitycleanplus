<?php

namespace Database\Seeders;

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use Illuminate\Database\Seeder;

/**
 * A starter Billing → Adjustment Items list for local data and the demo; the
 * real list is kept by the office on that page. Uniform and equipment charges
 * never go here (they come from supply requests, ADR-0014). Idempotent.
 */
class AdjustmentItemSeeder extends Seeder
{
    /** name => [type, default cents or null, billed to the hotel] */
    public const ITEMS = [
        'Attendance Bonus' => [AdjustmentType::Incentive, 25_00, false],
        'Perfect Attendance' => [AdjustmentType::Incentive, 50_00, true],
        'Holiday Bonus' => [AdjustmentType::Incentive, null, true],
        'Advance Recovery' => [AdjustmentType::Deduction, null, false],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $name => [$type, $default, $billable]) {
            AdjustmentItem::firstOrCreate(
                ['name' => $name],
                ['type' => $type, 'default_value' => $default, 'is_billable' => $billable, 'active' => true],
            );
        }
    }
}
