<?php

namespace Database\Seeders;

use App\Domain\Inventory\Actions\CreateItem;
use App\Domain\Inventory\Actions\ReceiveStock;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Item;
use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Actions\CreateManualTimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

/**
 * Local/dev demo data: one property with Bible rates, a recruiter assigned to it,
 * and a few contractors on active work orders — enough to exercise the Phase 03
 * pipeline. Idempotent. Never run in production.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::firstOrCreate(
            ['name' => 'Sample Marriott Downtown'],
            [
                'pm_name' => 'Pat Manager',
                'pm_phone' => '602-555-0100',
                'city' => 'Phoenix',
                'state' => 'AZ',
                'timezone' => 'America/Phoenix',
                'tax_rate' => 0.0875,
                'status' => PropertyStatus::Active,
            ],
        );

        if ($property->workOrders()->exists()) {
            return; // already seeded
        }

        // Recruiter who owns this property.
        $recruiter = Person::firstOrCreate(
            ['email' => 'recruiter@example.com'],
            ['name' => 'Rita Recruiter', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now()],
        );
        $recruiter->syncRoles('recruiter');
        $property->assignments()->firstOrCreate(
            ['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value],
        );

        // A property manager who logs into QC Minute to approve timesheets.
        $pm = Person::firstOrCreate(
            ['email' => 'pm@example.com'],
            ['name' => 'Paula PM', 'password' => Hash::make('password'), 'status' => PersonStatus::StaffActive, 'email_verified_at' => now()],
        );
        $pm->syncRoles('property_manager');
        $property->assignments()->firstOrCreate(
            ['person_id' => $pm->id, 'role' => PropertyAssignmentRole::PropertyManager->value],
        );

        // Two positions with Bible rates (cents).
        $positions = Position::query()->whereIn('slug', ['housekeeper', 'banquet-server'])->get();
        foreach ($positions as $i => $position) {
            $pay = 1800 + $i * 200;
            $bill = $pay + 1200;
            $property->positionRates()->firstOrCreate(
                ['position_id' => $position->id, 'effective_date' => now()->subMonths(3)->toDateString()],
                [
                    'pay_rate' => $pay, 'bill_rate' => $bill,
                    'ot_pay_rate' => (int) round($pay * 1.5), 'ot_bill_rate' => (int) round($bill * 1.5),
                    'is_active' => true, 'created_by' => $recruiter->id,
                ],
            );
        }

        // Three contractors, each on an active work order at the property.
        $workOrders = [];
        foreach (['Carlos Contractor', 'Dana Cleaner', 'Sam Server'] as $i => $name) {
            $position = $positions[$i % $positions->count()];
            $rate = $property->currentRateFor($position->id);

            $contractor = Person::factory()->create([
                'name' => $name,
                'status' => PersonStatus::ContractorActive,
                'primary_recruiter_id' => $recruiter->id,
            ]);
            $contractor->syncRoles('contractor');

            $workOrders[] = WorkOrder::create([
                'person_id' => $contractor->id,
                'property_id' => $property->id,
                'position_id' => $position->id,
                'pay_rate' => $rate->pay_rate,
                'bill_rate' => $rate->bill_rate,
                'ot_pay_rate' => $rate->ot_pay_rate,
                'ot_bill_rate' => $rate->ot_bill_rate,
                'start_date' => now()->subMonth()->toDateString(),
                'status' => WorkOrderStatus::Active,
                'source' => WorkOrderSource::RecruiterCreated,
                'created_by' => $recruiter->id,
            ]);
        }

        // Materialize payroll periods, then seed last week's hours (Mon–Fri 9h →
        // 45h = 40 regular + 5 OT) via the real entry path so summaries compute.
        Artisan::call('payroll:ensure-periods');
        $lastMonday = Carbon::now($property->timezone)->startOfWeek(Carbon::MONDAY)->subWeek();
        $createEntry = app(CreateManualTimeEntry::class);

        foreach ($workOrders as $workOrder) {
            for ($day = 0; $day < 5; $day++) {
                $createEntry->handle($workOrder, [
                    'date' => $lastMonday->copy()->addDays($day)->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'entry_type' => 'work',
                ], $recruiter);
            }
        }

        $this->seedInventory($recruiter);
    }

    /** A uniform (with size variants) and an equipment item, both stocked. */
    private function seedInventory(Person $actor): void
    {
        $createItem = app(CreateItem::class);
        $receive = app(ReceiveStock::class);

        $uniforms = Category::query()->where('slug', 'uniforms')->first();
        if ($uniforms !== null && ! Item::query()->where('name', 'Housekeeping Polo')->exists()) {
            $polo = $createItem->handle([
                'name' => 'Housekeeping Polo',
                'category_id' => $uniforms->id,
                'description' => 'Branded polo shirt',
                'has_variants' => true,
                'variants' => [
                    ['size' => 'S', 'color' => 'Navy', 'reorder_threshold' => 5],
                    ['size' => 'M', 'color' => 'Navy', 'reorder_threshold' => 5],
                    ['size' => 'L', 'color' => 'Navy', 'reorder_threshold' => 5],
                ],
            ], $actor);
            foreach ($polo->variants as $variant) {
                $receive->handle($variant, 20, 'Initial stock', $actor);
            }
        }

        $equipment = Category::query()->where('slug', 'equipment')->first();
        if ($equipment !== null && ! Item::query()->where('name', 'Backpack Vacuum')->exists()) {
            $vacuum = $createItem->handle([
                'name' => 'Backpack Vacuum',
                'category_id' => $equipment->id,
                'description' => 'Commercial backpack vacuum',
                'reorder_threshold' => 2,
            ], $actor);
            foreach ($vacuum->variants as $variant) {
                $receive->handle($variant, 6, 'Initial stock', $actor);
            }
        }
    }
}
