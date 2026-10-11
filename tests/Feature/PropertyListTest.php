<?php

use App\Domain\Billing\Enums\TimesheetStatus;
use App\Domain\Billing\Models\Timesheet;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed([RolePermissionSeeder::class, DepartmentSeeder::class]);
    // A Wednesday afternoon in Phoenix, so "this week" is unambiguous.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));
});

/** A property with nothing missing from its setup. */
function setUpProperty(array $attributes = []): Property
{
    $property = Property::factory()->create($attributes);
    PropertyPositionRate::factory()->create(['property_id' => $property->id]);
    PropertyDepartment::factory()->create(['property_id' => $property->id]);
    Contract::factory()->create(['property_id' => $property->id]);

    return $property;
}

function listWeek(Property $property, string $monday, TimesheetStatus $status, array $timesheet = []): PayrollPeriod
{
    $period = PayrollPeriod::factory()->forWeek(Carbon::parse($monday))->create(['property_id' => $property->id]);
    Timesheet::factory()->create(['property_id' => $property->id, 'payroll_period_id' => $period->id, 'status' => $status, ...$timesheet]);

    return $period;
}

/** @return array<string, array<string, mixed>> list rows keyed by property name */
function listRows(string $role): array
{
    $rows = [];

    test()->actingAs(person($role))
        ->get(main('/admin/properties'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$rows) {
            $rows = collect($page->toArray()['props']['properties'])->keyBy('name')->all();
        });

    return $rows;
}

it('gives each property its contractors and what needs attention', function () {
    $acme = setUpProperty(['name' => 'Acme Hotel']);
    listWeek($acme, '2026-09-28', TimesheetStatus::PendingApproval, ['sent_for_approval_at' => '2026-10-05 14:30:00']);
    WorkOrder::factory()->count(2)->create(['property_id' => $acme->id]);
    WorkOrder::factory()->closed()->create(['property_id' => $acme->id]);

    setUpProperty(['name' => 'Quiet Inn']);

    $rows = listRows('office_manager');

    expect($rows['Acme Hotel'])
        ->contractors->toBe(2)
        ->attention_count->toBe(1)
        ->attention_top->toBe('Week of Sep 28 is waiting on approval')
        ->time_source->toBe('clock_in')
        ->not->toHaveKey('week');

    expect($rows['Quiet Inn'])
        ->contractors->toBe(0)
        ->attention_count->toBe(0)
        ->attention_top->toBeNull();
});

it('counts the same attention items as the property page', function () {
    $property = Property::factory()->create(['name' => 'Bare Hotel']); // no rates, departments or contract

    $overview = [];
    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(function (Assert $page) use (&$overview) {
            $overview = $page->toArray()['props']['overview']['attention'];
        });

    expect(listRows('office_manager')['Bare Hotel'])
        ->attention_count->toBe(count($overview))
        ->attention_top->toBe($overview[0]['title']);
});

it('leaves contractors out for someone who cannot see work orders', function () {
    setUpProperty(['name' => 'Acme Hotel']);
    $viewer = person('office_manager');
    $viewer->roles->first()->revokePermissionTo('work_orders.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $rows = [];
    $this->actingAs($viewer->fresh())
        ->get(main('/admin/properties'))
        ->assertInertia(function (Assert $page) use (&$rows) {
            $rows = collect($page->toArray()['props']['properties'])->keyBy('name')->all();
        });

    expect($rows['Acme Hotel']['contractors'])->toBeNull();
});

it('builds the columns in the same few queries however many properties there are', function () {
    $queriesFor = function (int $count): int {
        Property::query()->delete();
        foreach (range(1, $count) as $i) {
            $p = setUpProperty();
            listWeek($p, '2026-10-05', TimesheetStatus::Draft);
            WorkOrder::factory()->create(['property_id' => $p->id]);
        }

        listRows('office_manager'); // warm the permission cache, which loads once per run
        DB::flushQueryLog();
        DB::enableQueryLog();
        listRows('office_manager');
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    expect($queriesFor(8))->toBe($queriesFor(2));
});
