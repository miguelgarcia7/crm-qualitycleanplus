<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use App\Domain\Time\Models\PayrollPeriod;
use App\Domain\Time\Models\TimeSummary;
use App\Domain\WorkOrders\Enums\WorkOrderSource;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // A Wednesday afternoon in Phoenix, so "this week" is unambiguous.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));
});

/** The side panel's data for ?panel=<id>, as $as sees it. */
function panelFor(int $id, ?Person $as = null): ?array
{
    $panel = null;

    test()->actingAs($as ?? person('office_manager'))
        ->get(main("/admin/work-orders?panel={$id}"))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$panel) {
            $panel = $page->toArray()['props']['panel'];
        });

    return $panel;
}

it('opens a work order from a link, with its rates against the property rate', function () {
    $property = Property::factory()->create(['name' => 'Acme Hotel']);
    $position = Position::factory()->create(['name' => 'Houseman']);
    PropertyPositionRate::factory()->create(['property_id' => $property->id, 'position_id' => $position->id, 'pay_rate' => 1700, 'bill_rate' => 2750]);
    $wo = WorkOrder::factory()->create([
        'property_id' => $property->id,
        'position_id' => $position->id,
        'person_id' => Person::factory()->create(['name' => 'James Carter', 'phone' => '(312) 555-0142'])->id,
        'pay_rate' => 1850,
        'bill_rate' => 2950,
        'start_date' => '2026-06-12',
    ]);

    expect(panelFor($wo->id))
        ->id->toBe($wo->id)
        ->contractor->toBe('James Carter')
        ->phone->toBe('(312) 555-0142')
        ->property->toBe('Acme Hotel')
        ->position->toBe('Houseman')
        ->pay_rate->toBe(1850)
        ->bible_rate->toBe(['pay_rate' => 1700, 'bill_rate' => 2750])
        ->this_week_start->toBe('2026-10-05')
        ->can->toMatchArray(['edit' => true, 'close' => true]);
});

it('lists the latest weeks with hours, newest first', function () {
    $wo = WorkOrder::factory()->create();
    foreach (['2026-09-21' => [2254, 0], '2026-09-28' => [2400, 577], '2026-10-05' => [1783, 0]] as $monday => [$regular, $overtime]) {
        $period = PayrollPeriod::factory()->forWeek(Carbon::parse($monday))->create(['property_id' => $wo->property_id]);
        TimeSummary::factory()->create([
            'work_order_id' => $wo->id, 'person_id' => $wo->person_id, 'property_id' => $wo->property_id,
            'payroll_period_id' => $period->id, 'week_start' => $monday, 'week_end' => Carbon::parse($monday)->addDays(6)->toDateString(),
            'regular_minutes' => $regular, 'overtime_minutes' => $overtime, 'holiday_minutes' => 0, 'training_minutes' => 0,
        ]);
    }

    $weeks = panelFor($wo->id)['weeks'];

    expect(array_column($weeks, 'week_start'))->toBe(['2026-10-05', '2026-09-28', '2026-09-21'])
        ->and($weeks[1])->toMatchArray(['total_minutes' => 2977, 'overtime_minutes' => 577]);
});

it('tells how a placement came about and what replaced it', function () {
    $original = WorkOrder::factory()->create(['start_date' => '2026-05-08', 'status' => WorkOrderStatus::Closed, 'end_date' => '2026-08-31']);
    $raise = WorkOrder::factory()->create([
        'person_id' => $original->person_id,
        'property_id' => $original->property_id,
        'parent_wo_id' => $original->id,
        'source' => WorkOrderSource::PayIncrease,
        'start_date' => '2026-09-01',
    ]);

    expect(array_column(panelFor($original->id)['history'], 'text'))
        ->toContain('Pay increase: continued on work order', 'Closed')
        ->and(panelFor($raise->id)['history'][0])->toMatchArray(['text' => 'Pay increase from work order', 'work_order_id' => $original->id])
        ->and(panelFor($original->id)['can'])->toMatchArray(['close' => false, 'transfer' => false]);
});

it('opens nothing for a work order the viewer cannot see, or one that does not exist', function () {
    $recruiter = person('recruiter');
    $theirs = Property::factory()->create();
    $theirs->assignments()->create(['person_id' => $recruiter->id, 'role' => 'recruiter']);
    $elsewhere = WorkOrder::factory()->create(); // another property

    expect(panelFor($elsewhere->id, $recruiter))->toBeNull()
        ->and(panelFor(999999))->toBeNull();
});

it('leaves the panel closed without a panel id', function () {
    WorkOrder::factory()->create();

    $this->actingAs(person('office_manager'))
        ->get(main('/admin/work-orders'))
        ->assertInertia(fn (Assert $page) => $page->where('panel', null));
});
