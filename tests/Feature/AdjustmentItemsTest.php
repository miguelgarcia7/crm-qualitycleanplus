<?php

use App\Domain\Adjustments\Enums\AdjustmentType;
use App\Domain\Adjustments\Models\AdjustmentItem;
use App\Domain\Adjustments\Models\TimeEntryAdjustment;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\Time\Enums\PayrollPeriodStatus;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** An item on the list, as the office would have added it. */
function adjustmentItem(array $attributes = []): AdjustmentItem
{
    return AdjustmentItem::create([
        'name' => 'Perfect Attendance', 'type' => AdjustmentType::Incentive, 'default_value' => 50_00, 'is_billable' => true, 'active' => true,
        ...$attributes,
    ]);
}

/** A recruiter's hotel with a work order and this week's open payroll period. */
function adjustmentScenario($test): array
{
    $property = Property::factory()->create(['timezone' => 'America/Chicago']);
    $workOrder = WorkOrder::factory()->create(['property_id' => $property->id, 'position_id' => Position::factory()->create()->id]);
    $period = PayrollPeriod::factory()->create([
        'property_id' => $property->id,
        'week_start' => Carbon::now('America/Chicago')->startOfWeek(Carbon::MONDAY)->toDateString(),
        'week_end' => Carbon::now('America/Chicago')->startOfWeek(Carbon::MONDAY)->addDays(6)->toDateString(),
        'status' => PayrollPeriodStatus::Open,
    ]);
    $recruiter = person('recruiter');
    $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    return compact('property', 'workOrder', 'period', 'recruiter');
}

it('lets super admin, admin, office manager and payroll manage the list', function (string $role) {
    adjustmentItem();

    $this->actingAs(person($role))
        ->get(main('/admin/adjustment-items'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/adjustment-items/index')
            ->where('items.0.name', 'Perfect Attendance')
            ->where('items.0.default_value', 50_00)
            ->where('items.0.is_billable', true));
})->with(['super_admin', 'admin', 'office_manager', 'payroll']);

it('keeps everyone else out', function (string $role) {
    $this->actingAs(person($role))->get(main('/admin/adjustment-items'))->assertForbidden();
    $this->actingAs(person($role))->post(main('/admin/adjustment-items'), ['name' => 'X', 'type' => 'incentive'])->assertForbidden();
})->with(['recruiter', 'hr', 'front_desk']);

it('adds an item and writes it to History', function () {
    $this->actingAs(person('payroll'))
        ->post(main('/admin/adjustment-items'), ['name' => 'Referral Bonus', 'type' => 'incentive', 'default_amount' => '100', 'is_billable' => false])
        ->assertSessionHasNoErrors();

    $item = AdjustmentItem::query()->where('name', 'Referral Bonus')->firstOrFail();
    expect($item->default_value)->toBe(100_00)
        ->and($item->active)->toBeTrue()
        ->and($item->is_billable)->toBeFalse();

    $this->actingAs(person('payroll'))
        ->get(main('/admin/adjustment-items'))
        ->assertInertia(fn (Assert $page) => $page->where('items.0.history.0.text', 'Added'));
});

it('never bills the hotel for a deduction', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/adjustment-items'), ['name' => 'Advance Recovery', 'type' => 'deduction', 'default_amount' => null, 'is_billable' => true])
        ->assertSessionHasNoErrors();

    $item = AdjustmentItem::query()->where('name', 'Advance Recovery')->firstOrFail();
    expect($item->is_billable)->toBeFalse()->and($item->default_value)->toBeNull();
});

it('records each change in plain words, and making one inactive', function () {
    $item = adjustmentItem(['default_value' => 40_00]);
    $manager = tap(person('office_manager'))->update(['name' => 'Olivia Office']);

    $this->actingAs($manager)
        ->put(main("/admin/adjustment-items/{$item->id}"), ['name' => 'Perfect Attendance', 'type' => 'incentive', 'default_amount' => '50', 'is_billable' => true, 'active' => true])
        ->assertSessionHasNoErrors();
    $this->actingAs($manager)
        ->put(main("/admin/adjustment-items/{$item->id}"), ['name' => 'Perfect Attendance', 'type' => 'incentive', 'default_amount' => '50', 'is_billable' => true, 'active' => false])
        ->assertSessionHasNoErrors();

    expect($item->fresh()->active)->toBeFalse();

    $this->actingAs($manager)
        ->get(main('/admin/adjustment-items'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.history.0.text', 'Made inactive')
            ->where('items.0.history.1.text', 'Default amount $40.00 → $50.00')
            ->where('items.0.history.1.by', 'Olivia Office'));
});

it('refuses a second item with the same name', function () {
    adjustmentItem();

    $this->actingAs(person('admin'))
        ->post(main('/admin/adjustment-items'), ['name' => 'Perfect Attendance', 'type' => 'incentive'])
        ->assertSessionHasErrors(['name' => 'There is already an item with this name.']);
});

it('counts how often an item was used in the last 90 days', function () {
    $item = adjustmentItem();
    ['period' => $period, 'workOrder' => $wo] = adjustmentScenario($this);
    $row = ['person_id' => $wo->person_id, 'work_order_id' => $wo->id, 'property_id' => $period->property_id, 'payroll_period_id' => $period->id, 'adjustment_item_id' => $item->id, 'source_type' => 'manual', 'value' => 50_00, 'type' => 'incentive'];
    TimeEntryAdjustment::create($row);
    TimeEntryAdjustment::create($row)->forceFill(['created_at' => now()->subDays(120)])->save();

    $this->actingAs(person('payroll'))
        ->get(main('/admin/adjustment-items'))
        ->assertInertia(fn (Assert $page) => $page->where('items.0.used_90', 1));
});

it('adds a grid adjustment from an item: the item sets the type and billing, the recruiter the amount', function () {
    $item = adjustmentItem();
    ['period' => $period, 'workOrder' => $wo, 'recruiter' => $recruiter] = adjustmentScenario($this);

    $this->actingAs($recruiter)
        ->post(main("/admin/payroll-periods/{$period->id}/adjustments"), [
            'person_id' => $wo->person_id, 'work_order_id' => $wo->id, 'adjustment_item_id' => $item->id,
            'amount' => '45.00', 'notes' => '30 days, no callouts',
            // Ignored: the item decides these.
            'type' => 'deduction', 'is_billable' => false,
        ])
        ->assertSessionHasNoErrors();

    $adjustment = TimeEntryAdjustment::query()->latest('id')->firstOrFail();
    expect($adjustment->adjustment_item_id)->toBe($item->id)
        ->and($adjustment->type)->toBe(AdjustmentType::Incentive)
        ->and($adjustment->is_billable)->toBeTrue()
        ->and($adjustment->value)->toBe(45_00)
        ->and($adjustment->notes)->toBe('30 days, no callouts');
});

it('refuses a grid adjustment with no item, or an inactive one', function () {
    $retired = adjustmentItem(['name' => 'Old Bonus', 'active' => false]);
    ['period' => $period, 'workOrder' => $wo, 'recruiter' => $recruiter] = adjustmentScenario($this);
    $url = main("/admin/payroll-periods/{$period->id}/adjustments");

    $this->actingAs($recruiter)
        ->post($url, ['person_id' => $wo->person_id, 'work_order_id' => $wo->id, 'amount' => '10', 'type' => 'incentive'])
        ->assertSessionHasErrors(['adjustment_item_id' => 'Pick an item from the list.']);
    $this->actingAs($recruiter)
        ->post($url, ['person_id' => $wo->person_id, 'work_order_id' => $wo->id, 'adjustment_item_id' => $retired->id, 'amount' => '10'])
        ->assertSessionHasErrors('adjustment_item_id');

    expect(TimeEntryAdjustment::query()->count())->toBe(0);
});

it('offers only active items on the grid, and shows the item name on added ones', function () {
    $item = adjustmentItem();
    adjustmentItem(['name' => 'Old Bonus', 'active' => false]);
    ['property' => $property, 'period' => $period, 'workOrder' => $wo, 'recruiter' => $recruiter] = adjustmentScenario($this);
    TimeEntryAdjustment::create(['person_id' => $wo->person_id, 'work_order_id' => $wo->id, 'property_id' => $property->id, 'payroll_period_id' => $period->id, 'adjustment_item_id' => $item->id, 'source_type' => 'manual', 'value' => 50_00, 'type' => 'incentive', 'notes' => 'September']);

    $this->actingAs($recruiter)
        ->get(main("/admin/properties/{$property->id}/grid"))
        ->assertInertia(fn (Assert $page) => $page
            ->has('adjustmentItems', 1)
            ->where('adjustmentItems.0.name', 'Perfect Attendance')
            ->where('adjustments.0.item', 'Perfect Attendance')
            ->where('adjustments.0.notes', 'September'));
});
