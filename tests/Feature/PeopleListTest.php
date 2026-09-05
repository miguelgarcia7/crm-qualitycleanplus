<?php

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/**
 * The person names on one page of the directory.
 *
 * The actor must be passed in: person() creates a new record each call, and
 * every one of them is staff — calling it per request would quietly grow the
 * staff list the test is asserting on.
 */
function directoryNames(Person $as, string $query = ''): array
{
    $names = [];

    test()->actingAs($as)->get(main('/admin/people'.$query))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use (&$names) {
            $names = collect($page->toArray()['props']['people'])->pluck('name')->all();
        });

    return $names;
}

it('slices the list in the database rather than shipping every row', function () {
    Person::factory()->count(30)->create(['status' => PersonStatus::ContractorActive]);

    $this->actingAs(person('admin'))->get(main('/admin/people'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/people/index')
            ->has('people', 25)
            ->where('pagination.total', 30)
            ->where('pagination.last_page', 2),
        );
});

it('returns a second page that neither repeats nor drops a row', function () {
    $admin = person('admin');
    Person::factory()->count(30)->create(['status' => PersonStatus::ContractorActive]);

    $first = directoryNames($admin);
    $second = directoryNames($admin, '?page=2');

    expect($second)->toHaveCount(5)
        ->and(array_intersect($first, $second))->toBeEmpty()
        ->and(array_unique([...$first, ...$second]))->toHaveCount(30);
});

it('fetches only the tab on screen, not both lists', function () {
    $admin = person('admin');
    Person::factory()->create(['name' => 'Carla Contractor', 'status' => PersonStatus::ContractorActive]);
    Person::factory()->create(['name' => 'Stacy Staffer', 'status' => PersonStatus::StaffActive]);

    // Both lists used to load on every visit, including the hidden one.
    expect(directoryNames($admin))->toBe(['Carla Contractor'])
        // The actor is a staff record too, so the staff tab holds them as well.
        ->and(directoryNames($admin, '?tab=staff'))->toContain('Stacy Staffer')
        ->and(directoryNames($admin, '?tab=staff'))->not->toContain('Carla Contractor');
});

it('counts both tabs even though only one is fetched', function () {
    $admin = person('admin');
    Person::factory()->count(3)->create(['status' => PersonStatus::ContractorActive]);
    Person::factory()->count(2)->create(['status' => PersonStatus::StaffActive]);

    // Counted from the database rather than hardcoded: the acting admin is
    // itself a staff record and belongs in the staff total.
    $staffTotal = Person::query()->whereIn('status', [PersonStatus::StaffActive, PersonStatus::StaffInactive])->count();

    $this->actingAs($admin)->get(main('/admin/people'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('counts.contractors', 3)
            // The header must not lie about the tab you are not on.
            ->where('counts.staff', $staffTotal),
        );
});

it('leaves tab counts alone when a status filter is applied', function () {
    $admin = person('admin');
    Person::factory()->count(3)->create(['status' => PersonStatus::ContractorActive]);
    Person::factory()->count(2)->create(['status' => PersonStatus::Terminated]);

    $this->actingAs(person('admin'))->get(main('/admin/people?status=terminated'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('people', 2)
            // The count describes the tab, not the filtered slice — otherwise
            // the tab label changes every time you touch the status filter.
            ->where('counts.contractors', 5),
        );
});

it('filters by a status belonging to the tab, and ignores one that does not', function () {
    $admin = person('admin');
    Person::factory()->count(3)->create(['status' => PersonStatus::ContractorActive]);
    Person::factory()->count(2)->create(['status' => PersonStatus::Terminated]);
    Person::factory()->count(4)->create(['status' => PersonStatus::StaffActive]);
    $staffTotal = Person::query()->where('status', PersonStatus::StaffActive)->count();

    expect(directoryNames($admin, '?status=terminated'))->toHaveCount(2)
        // staff_active is not a contractor status — must not narrow to zero on
        // the contractors tab, and must not leak staff into it either.
        ->and(directoryNames($admin, '?status=staff_active'))->toHaveCount(5)
        ->and(directoryNames($admin, '?tab=staff&status=staff_active'))->toHaveCount($staffTotal);
});

it('searches on name, email and phone', function () {
    $admin = person('admin');
    Person::factory()->create([
        'name' => 'Marcus Webb', 'email' => 'marcus@example.com', 'phone' => '555-0142',
        'status' => PersonStatus::ContractorActive,
    ]);
    Person::factory()->create([
        'name' => 'Ana Reyes', 'email' => 'ana@example.com', 'phone' => '555-0199',
        'status' => PersonStatus::ContractorActive,
    ]);

    expect(directoryNames($admin, '?search=Marcus'))->toBe(['Marcus Webb'])
        ->and(directoryNames($admin, '?search=ana@example'))->toBe(['Ana Reyes'])
        ->and(directoryNames($admin, '?search=0142'))->toBe(['Marcus Webb'])
        ->and(directoryNames($admin, '?search=nothing at all'))->toBeEmpty();
});

it('orders by a whitelisted column and falls back when handed anything else', function () {
    $admin = person('admin');
    foreach (['Zoe Chen', 'Ana Reyes', 'Marcus Webb'] as $name) {
        Person::factory()->create(['name' => $name, 'status' => PersonStatus::ContractorActive]);
    }

    expect(directoryNames($admin, '?sort=name&direction=asc'))->toBe(['Ana Reyes', 'Marcus Webb', 'Zoe Chen'])
        ->and(directoryNames($admin, '?sort=name&direction=desc'))->toBe(['Zoe Chen', 'Marcus Webb', 'Ana Reyes']);

    $this->actingAs(person('admin'))->get(main('/admin/people?sort=password&direction=sideways'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc'),
        );
});

it('refuses a sort that belongs to the other tab', function () {
    Person::factory()->count(2)->create(['status' => PersonStatus::StaffActive]);

    // `recruiter` is a contractors-only column; on staff it must not reach the
    // query builder, where the joined table does not even exist.
    $this->actingAs(person('admin'))->get(main('/admin/people?tab=staff&sort=recruiter'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'name'));

    $this->actingAs(person('admin'))->get(main('/admin/people?sort=hire_date'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.sort', 'name'));
});

it('keeps a recruiter scoped to their own contractors through paging', function () {
    $recruiter = person('recruiter');
    Person::factory()->count(2)->create([
        'status' => PersonStatus::ContractorActive,
        'primary_recruiter_id' => $recruiter->id,
    ]);
    Person::factory()->count(30)->create(['status' => PersonStatus::ContractorActive]);

    expect(directoryNames($recruiter))->toHaveCount(2);

    test()->actingAs($recruiter)->get(main('/admin/people'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('pagination.total', 2));
});

it('honours the per-page choice and ignores one it does not offer', function () {
    $admin = person('admin');
    Person::factory()->count(30)->create(['status' => PersonStatus::ContractorActive]);

    expect(directoryNames($admin, '?per_page=10'))->toHaveCount(10)
        ->and(directoryNames($admin, '?per_page=5000'))->toHaveCount(25);
});
