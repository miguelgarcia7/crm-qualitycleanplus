<?php

use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an office manager create a position', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/positions'), ['name' => 'Night Auditor', 'notes' => null, 'is_active' => true])
        ->assertRedirect()
        ->assertSessionHas('success');

    $position = Position::firstWhere('name', 'Night Auditor');
    expect($position)->not->toBeNull()
        ->and($position->slug)->toBe('night-auditor')
        ->and($position->is_active)->toBeTrue();
});

it('rejects a duplicate position name', function () {
    Position::factory()->create(['name' => 'Housekeeper']);

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/positions'), ['name' => 'Housekeeper', 'is_active' => true])
        ->assertSessionHasErrors('name');
});

it('renames a position everywhere while keeping its slug stable', function () {
    $position = Position::factory()->create(['name' => 'Houseman', 'slug' => 'houseman']);
    $property = Property::factory()->create();
    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3200, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4800,
        'effective_date' => now()->subMonth()->toDateString(), 'is_active' => true,
    ]);

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/positions/{$position->id}"), ['name' => 'House Attendant', 'notes' => null, 'is_active' => true])
        ->assertRedirect();

    $position->refresh();
    expect($position->name)->toBe('House Attendant')
        ->and($position->slug)->toBe('houseman')
        ->and($property->currentRateFor($position)->position->name)->toBe('House Attendant');
});

it('hides a deactivated position from the work-order position lookup', function () {
    $position = Position::factory()->create(['name' => 'Dishwasher']);
    $property = Property::factory()->create();
    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3200, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4800,
        'effective_date' => now()->subMonth()->toDateString(), 'is_active' => true,
    ]);

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/positions/{$position->id}"), ['name' => 'Dishwasher', 'notes' => null, 'is_active' => false])
        ->assertRedirect();

    $this->actingAs(person('office_manager'))
        ->getJson(main("/admin/work-orders/position-lookup?property_id={$property->id}"))
        ->assertOk()
        ->assertJsonCount(0);
});

it('shows the catalog with usage counts', function () {
    $position = Position::factory()->create(['name' => 'Banquet Server']);
    $propertyA = Property::factory()->create();
    $propertyB = Property::factory()->create();
    foreach ([$propertyA, $propertyB] as $property) {
        $property->positionRates()->create([
            'position_id' => $position->id,
            'pay_rate' => 2000, 'bill_rate' => 3200, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4800,
            'effective_date' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);
    }

    $this->actingAs(person('office_manager'))
        ->get(main('/admin/positions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/positions/index')
            ->where('positions.0.name', 'Banquet Server')
            ->where('positions.0.properties_count', 2)
            ->where('can.edit', true));
});

it('lets a recruiter view the catalog but not change it', function () {
    Position::factory()->create();

    $this->actingAs(person('recruiter'))
        ->get(main('/admin/positions'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.edit', false));

    $this->actingAs(person('recruiter'))
        ->post(main('/admin/positions'), ['name' => 'Sneaky', 'is_active' => true])
        ->assertForbidden();
});

it('blocks a contractor from the positions catalog', function () {
    $this->actingAs(person('contractor'))
        ->get(main('/admin/positions'))
        ->assertForbidden();
});
