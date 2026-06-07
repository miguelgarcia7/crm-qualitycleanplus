<?php

use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed([RolePermissionSeeder::class, PositionSeeder::class]));

it('resolves the current rate as the latest effective row on or before today', function () {
    $property = Property::factory()->create();
    $position = Position::first();

    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'effective_date' => now()->subMonths(2)->toDateString(), 'is_active' => true,
    ]);
    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2200, 'bill_rate' => 3300, 'ot_pay_rate' => 3300, 'ot_bill_rate' => 4950,
        'effective_date' => now()->subDay()->toDateString(), 'is_active' => true,
    ]);
    // A future rate must be ignored until its effective date arrives.
    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2500, 'bill_rate' => 3600, 'ot_pay_rate' => 3750, 'ot_bill_rate' => 5400,
        'effective_date' => now()->addMonth()->toDateString(), 'is_active' => true,
    ]);

    expect($property->currentRateFor($position)->pay_rate)->toBe(2200);
});

it('returns null when a position has no rate', function () {
    $property = Property::factory()->create();
    $position = Position::first();

    expect($property->currentRateFor($position))->toBeNull();
});

it('adds a new effective-dated rate and closes out the prior open row', function () {
    $property = Property::factory()->create();
    $position = Position::first();

    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3000, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4500,
        'effective_date' => now()->subMonth()->toDateString(), 'is_active' => true,
    ]);

    $this->actingAs(person('payroll'))
        ->post(main("/admin/properties/{$property->id}/rates"), [
            'position_id' => $position->id,
            'pay_rate' => 22, 'bill_rate' => 33, 'ot_pay_rate' => 33, 'ot_bill_rate' => 49.50,
            'effective_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    // Dollars converted to cents on the new row.
    $current = $property->fresh()->currentRateFor($position);
    expect($current->pay_rate)->toBe(2200)
        ->and($current->bill_rate)->toBe(3300)
        ->and($current->ot_bill_rate)->toBe(4950);

    // Prior row was closed out (end_date set).
    $prior = $property->positionRates()->where('pay_rate', 2000)->first();
    expect($prior->end_date)->not->toBeNull();
});

it('forbids a recruiter from editing rates', function () {
    $property = Property::factory()->create();
    $position = Position::first();

    $this->actingAs(person('recruiter'))
        ->post(main("/admin/properties/{$property->id}/rates"), [
            'position_id' => $position->id,
            'pay_rate' => 20, 'bill_rate' => 30, 'ot_pay_rate' => 30, 'ot_bill_rate' => 45,
            'effective_date' => now()->toDateString(),
        ])
        ->assertForbidden();
});
