<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('creates a work order, converting dollar rates to cents', function () {
    $contractor = Person::factory()->create(['status' => PersonStatus::ContractorActive]);
    $property = Property::factory()->create();
    $position = Position::factory()->create();

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/work-orders'), [
            'person_id' => $contractor->id,
            'property_id' => $property->id,
            'position_id' => $position->id,
            'pay_rate' => 18,
            'bill_rate' => 30,
            'ot_pay_rate' => 27,
            'ot_bill_rate' => 45,
            'start_date' => now()->toDateString(),
        ])
        ->assertRedirect();

    $wo = WorkOrder::first();
    expect($wo->pay_rate)->toBe(1800)
        ->and($wo->bill_rate)->toBe(3000)
        ->and($wo->ot_bill_rate)->toBe(4500);
});

it('looks up the current Bible rate for a property + position', function () {
    $property = Property::factory()->create();
    $position = Position::factory()->create();
    $property->positionRates()->create([
        'position_id' => $position->id,
        'pay_rate' => 2000, 'bill_rate' => 3200, 'ot_pay_rate' => 3000, 'ot_bill_rate' => 4800,
        'effective_date' => now()->subMonth()->toDateString(), 'is_active' => true,
    ]);

    $this->actingAs(person('office_manager'))
        ->getJson(main("/admin/work-orders/rate-lookup?property_id={$property->id}&position_id={$position->id}"))
        ->assertOk()
        ->assertJson(['pay_rate' => 2000, 'bill_rate' => 3200]);
});

it('blocks a contractor from the work-orders area', function () {
    $this->actingAs(person('contractor'))
        ->get(main('/admin/work-orders'))
        ->assertForbidden();
});

it('scopes a recruiter to work orders for assigned properties', function () {
    $assigned = Property::factory()->create();
    $other = Property::factory()->create();
    WorkOrder::factory()->create(['property_id' => $assigned->id]);
    WorkOrder::factory()->create(['property_id' => $other->id]);

    $recruiter = person('recruiter');
    $assigned->assignments()->create(['person_id' => $recruiter->id, 'role' => 'recruiter']);

    $this->actingAs($recruiter)
        ->get(main('/admin/work-orders'))
        ->assertInertia(fn ($page) => $page->has('workOrders', 1));
});
