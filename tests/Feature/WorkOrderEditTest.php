<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** A valid edit-form submission for $wo, with $overrides on top. */
function workOrderPayload(WorkOrder $wo, array $overrides = []): array
{
    return [
        'pay_rate' => $wo->pay_rate / 100,
        'bill_rate' => $wo->bill_rate / 100,
        'ot_pay_rate' => $wo->ot_pay_rate / 100,
        'ot_bill_rate' => $wo->ot_bill_rate / 100,
        'start_date' => $wo->start_date->toDateString(),
        'end_date' => null,
        'status' => $wo->status->value,
        'direct_hire_threshold_hours' => intdiv($wo->direct_hire_threshold_minutes, 60),
        'notes' => null,
        ...$overrides,
    ];
}

it('opens the edit form with who, where and the property rate to compare against', function () {
    $property = Property::factory()->create(['name' => 'Acme Hotel', 'direct_hire_threshold_minutes' => 1500 * 60]);
    $position = Position::factory()->create(['name' => 'Houseman']);
    PropertyPositionRate::factory()->create(['property_id' => $property->id, 'position_id' => $position->id, 'pay_rate' => 1700, 'bill_rate' => 2750]);
    $wo = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => $position->id,
        'person_id' => Person::factory()->create(['name' => 'James Carter'])->id,
    ]);

    $this->actingAs(person('office_manager'))->get(main("/admin/work-orders/{$wo->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('workOrder.contractor', 'James Carter')
            ->where('workOrder.property', 'Acme Hotel')
            ->where('workOrder.position', 'Houseman')
            ->where('workOrder.bible_rate', ['pay_rate' => 1700, 'bill_rate' => 2750])
            ->where('workOrder.property_direct_hire_hours', 1500)
            ->where('workOrder.can_close', true),
        );
});

it('keeps any direct-hire threshold the contract states, not just 40-hour steps up to 2,080', function (int $hours) {
    $wo = WorkOrder::factory()->create();

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo, ['direct_hire_threshold_hours' => $hours]))
        ->assertSessionHasNoErrors();

    expect($wo->fresh()->direct_hire_threshold_minutes)->toBe($hours * 60);
})->with([1500, 2500, 0]);

it('will not close a work order from the form, which would skip the end date', function () {
    $wo = WorkOrder::factory()->create(['status' => WorkOrderStatus::Active]);

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo, ['status' => 'closed']))
        ->assertSessionHasErrors('status');

    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Active);
});

it('lets a closed work order be saved as it is, or reopened', function () {
    $wo = WorkOrder::factory()->create(['status' => WorkOrderStatus::Closed, 'end_date' => '2026-09-30']);

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo, ['status' => 'closed', 'notes' => 'Left for another job']))
        ->assertSessionHasNoErrors();

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo->fresh(), ['status' => 'active']))
        ->assertSessionHasNoErrors();

    expect($wo->fresh()->status)->toBe(WorkOrderStatus::Active);
});

it('offers each property with its contracted threshold when adding a work order', function () {
    $property = Property::factory()->create(['direct_hire_threshold_minutes' => 1000 * 60]);

    $this->actingAs(person('office_manager'))->get(main('/admin/work-orders/create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where(
            'catalogs.properties',
            fn ($properties) => collect($properties)->firstWhere('id', $property->id)['direct_hire_hours'] === 1000,
        ));
});

it('lets rates change until the first hours are logged', function () {
    $wo = WorkOrder::factory()->create(['pay_rate' => 8150]);

    // The classic typo, caught before anyone clocked in.
    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo, ['pay_rate' => 18.50, 'ot_pay_rate' => 27.75]))
        ->assertSessionHasNoErrors();

    expect($wo->fresh()->pay_rate)->toBe(1850);
});

it('locks the rates once hours are logged, but still saves everything else', function () {
    $wo = WorkOrder::factory()->create(['pay_rate' => 1850]);
    TimeEntry::factory()->create(['work_order_id' => $wo->id, 'person_id' => $wo->person_id, 'property_id' => $wo->property_id]);

    $this->actingAs(person('office_manager'))->get(main("/admin/work-orders/{$wo->id}/edit"))
        ->assertInertia(fn (Assert $page) => $page->where('workOrder.rates_locked', true));

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo, ['pay_rate' => 20.00]))
        ->assertSessionHasErrors('pay_rate');

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/work-orders/{$wo->id}"), workOrderPayload($wo, ['notes' => 'Prefers the morning shift']))
        ->assertSessionHasNoErrors();

    expect($wo->fresh())
        ->pay_rate->toBe(1850)
        ->notes->toBe('Prefers the morning shift');
});
