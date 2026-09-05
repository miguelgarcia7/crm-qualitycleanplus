<?php

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\WorkOrders\Enums\WorkOrderStatus;
use App\Domain\WorkOrders\Models\WorkOrder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

/** Work orders at one property, all with the same status. */
function workOrdersAt(Property $property, int $count, WorkOrderStatus $status = WorkOrderStatus::Active): void
{
    WorkOrder::factory()->count($count)->create([
        'property_id' => $property->id,
        'position_id' => Position::factory()->create()->id,
        'status' => $status,
    ]);
}

/** The work-order ids on one page of the list. */
function workOrderIds(string $query = '', ?Person $as = null): array
{
    $ids = [];

    test()->actingAs($as ?? person('office_manager'))->get(main('/admin/work-orders'.$query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$ids) {
            $ids = collect($page->toArray()['props']['workOrders'])->pluck('id')->all();
        });

    return $ids;
}

it('slices the list in the database rather than shipping every row', function () {
    workOrdersAt(Property::factory()->create(), 30);

    $this->actingAs(person('office_manager'))->get(main('/admin/work-orders'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/work-orders/index')
            ->has('workOrders', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2),
        );
});

it('returns a second page that neither repeats nor drops a row', function () {
    workOrdersAt(Property::factory()->create(), 30);

    $first = workOrderIds();
    $second = workOrderIds('?page=2');

    expect($second)->toHaveCount(5)
        // Start dates tie constantly; without a stable tiebreaker rows leak
        // between pages.
        ->and(array_intersect($first, $second))->toBeEmpty()
        ->and(array_unique([...$first, ...$second]))->toHaveCount(30);
});

it('honours the per-page choice and ignores one it does not offer', function () {
    workOrdersAt(Property::factory()->create(), 30);

    expect(workOrderIds('?per_page=10'))->toHaveCount(10)
        ->and(workOrderIds('?per_page=5000'))->toHaveCount(25);
});

it('filters by status, offering every status rather than only what page one holds', function () {
    $property = Property::factory()->create();
    workOrdersAt($property, 3, WorkOrderStatus::Active);
    workOrdersAt($property, 2, WorkOrderStatus::Closed);

    expect(workOrderIds('?status=closed'))->toHaveCount(2)
        ->and(workOrderIds('?status=active'))->toHaveCount(3)
        // Not a real status — falls back to showing everything.
        ->and(workOrderIds('?status=deleted'))->toHaveCount(5);

    $this->actingAs(person('office_manager'))->get(main('/admin/work-orders'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('statuses', count(WorkOrderStatus::cases())),
        );
});

it('searches across contractor, property and position', function () {
    $sunrise = Property::factory()->create(['name' => 'Sunrise Villas']);
    $harbor = Property::factory()->create(['name' => 'Harbor Point']);

    WorkOrder::factory()->create([
        'property_id' => $sunrise->id,
        'person_id' => Person::factory()->create(['name' => 'Marcus Webb'])->id,
        'position_id' => Position::factory()->create(['name' => 'Night Porter'])->id,
    ]);
    WorkOrder::factory()->create([
        'property_id' => $harbor->id,
        'person_id' => Person::factory()->create(['name' => 'Ana Reyes'])->id,
        'position_id' => Position::factory()->create(['name' => 'Housekeeper'])->id,
    ]);

    expect(workOrderIds('?search=Marcus'))->toHaveCount(1)
        ->and(workOrderIds('?search=Harbor'))->toHaveCount(1)
        ->and(workOrderIds('?search=Porter'))->toHaveCount(1)
        ->and(workOrderIds('?search=nothing at all'))->toBeEmpty();
});

it('filters by property', function () {
    $sunrise = Property::factory()->create();
    $harbor = Property::factory()->create();
    workOrdersAt($sunrise, 2);
    workOrdersAt($harbor, 3);

    expect(workOrderIds("?property_id={$harbor->id}"))->toHaveCount(3);
});

it('orders by a whitelisted column and falls back when handed anything else', function () {
    $property = Property::factory()->create();
    foreach (['Ana Reyes', 'Marcus Webb', 'Zoe Chen'] as $name) {
        WorkOrder::factory()->create([
            'property_id' => $property->id,
            'person_id' => Person::factory()->create(['name' => $name])->id,
            'position_id' => Position::factory()->create()->id,
        ]);
    }

    $namesFor = function (string $query): array {
        $names = [];
        test()->actingAs(person('office_manager'))->get(main('/admin/work-orders'.$query))
            ->assertInertia(function (AssertableInertia $page) use (&$names) {
                $names = collect($page->toArray()['props']['workOrders'])->pluck('contractor')->all();
            });

        return $names;
    };

    expect($namesFor('?sort=contractor&direction=asc'))->toBe(['Ana Reyes', 'Marcus Webb', 'Zoe Chen'])
        ->and($namesFor('?sort=contractor&direction=desc'))->toBe(['Zoe Chen', 'Marcus Webb', 'Ana Reyes']);

    // An unknown column must not reach the query builder.
    $this->actingAs(person('office_manager'))->get(main('/admin/work-orders?sort=password&direction=sideways'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc'),
        );
});

it('keeps a recruiter scoped to their properties through filters and paging', function () {
    $assigned = Property::factory()->create();
    $other = Property::factory()->create();
    workOrdersAt($assigned, 2);
    workOrdersAt($other, 30);

    $recruiter = person('recruiter');
    $assigned->assignments()->create(['person_id' => $recruiter->id, 'role' => 'recruiter']);

    // Scoping is applied in the query, so it survives paging rather than being
    // a filter over rows already fetched.
    expect(workOrderIds('', $recruiter))->toHaveCount(2)
        // Naming another property in the URL must not widen what they see.
        ->and(workOrderIds("?property_id={$other->id}", $recruiter))->toBeEmpty();

    test()->actingAs($recruiter)->get(main('/admin/work-orders'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pagination.total', 2));
});

it('computes direct-hire progress only for the rows on the page', function () {
    workOrdersAt(Property::factory()->create(), 30);

    $this->actingAs(person('office_manager'))->get(main('/admin/work-orders?per_page=10'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('workOrders', 10)
            ->has('workOrders.0.direct_hire'),
        );
});
