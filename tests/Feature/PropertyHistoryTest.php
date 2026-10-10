<?php

use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** `$count` changes logged against the property, all in the same second, as a bulk edit writes them. */
function logPropertyChanges(Property $property, int $count): void
{
    foreach (range(1, $count) as $i) {
        activity()->performedOn($property)->log("Change {$i}");
    }
}

/** The descriptions on one page of the property's History section. */
function historyPage(Property $property, int $page): array
{
    $rows = [];

    test()->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}?history_page={$page}"))
        ->assertOk()
        ->assertInertia(function (Assert $p) use (&$rows) {
            $rows = $p->toArray()['props']['history'];
        });

    return $rows;
}

it('pages the history 25 at a time, newest first', function () {
    $property = Property::factory()->create();
    logPropertyChanges($property, 30);
    // Creating the property may log its own entry too, so count what is there.
    $total = Activity::query()->whereMorphedTo('subject', $property)->count();

    $first = historyPage($property, 1);
    $second = historyPage($property, 2);

    expect($first['rows'])->toHaveCount(25)
        ->and($first['rows'][0]['description'])->toBe('Change 30')
        ->and($first['total'])->toBe($total)
        ->and($first['last_page'])->toBe(2)
        ->and($second['current_page'])->toBe(2)
        ->and(count($second['rows']))->toBe($total - 25)
        // Same-second rows: the id tiebreaker keeps every row on exactly one page.
        ->and(array_intersect(array_column($first['rows'], 'id'), array_column($second['rows'], 'id')))->toBeEmpty();
});

it('keeps a short history on one page', function () {
    $property = Property::factory()->create();
    logPropertyChanges($property, 3);

    expect(historyPage($property, 1))
        ->last_page->toBe(1)
        ->from->toBe(1);
});
