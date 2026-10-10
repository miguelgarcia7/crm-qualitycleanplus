<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lists the placements running at the property, by contractor, with direct-hire progress', function () {
    $property = Property::factory()->create();
    $zoe = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'person_id' => Person::factory()->create(['name' => 'Zoe Park'])->id,
        'direct_hire_threshold_minutes' => 6000,
    ]);
    WorkOrder::factory()->create([
        'property_id' => $property->id,
        'person_id' => Person::factory()->create(['name' => 'Amy Chen'])->id,
        'status' => WorkOrderStatus::Suspended,
    ]);
    WorkOrder::factory()->closed()->create(['property_id' => $property->id]);
    WorkOrder::factory()->create(); // another property's
    TimeSummary::factory()->create(['work_order_id' => $zoe->id, 'person_id' => $zoe->person_id, 'property_id' => $property->id, 'regular_minutes' => 1500]);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->has('workOrders.rows', 2)
            ->where('workOrders.closed_count', 1)
            ->where('workOrders.rows.0.contractor', 'Amy Chen')
            ->where('workOrders.rows.0.status', 'suspended')
            ->where('workOrders.rows.1.contractor', 'Zoe Park')
            ->where('workOrders.rows.1.direct_hire.worked_hours', 25)
            ->where('workOrders.rows.1.direct_hire.percent', 25)
            ->where('can.createWorkOrders', true),
        );
});

it('leaves the work orders off for someone who cannot see them', function () {
    $property = Property::factory()->create();
    WorkOrder::factory()->create(['property_id' => $property->id]);
    // Office manager without work-order access: take the permission away from the role.
    $viewer = person('office_manager');
    $viewer->roles->first()->revokePermissionTo('work_orders.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($viewer->fresh())
        ->get(main("/admin/properties/{$property->id}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('workOrders', null));
});

it('starts a new work order at the property it was opened from', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/work-orders/create?property_id={$property->id}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/work-orders/form')
            ->where('propertyId', $property->id),
        );
});
