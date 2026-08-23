<?php

use App\Domain\PropertyBible\Models\Department;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('lets an office manager create a department', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/departments'), ['name' => 'Laundry', 'is_active' => true])
        ->assertRedirect()
        ->assertSessionHas('success');

    $department = Department::firstWhere('name', 'Laundry');
    expect($department)->not->toBeNull()
        ->and($department->slug)->toBe('laundry')
        ->and($department->is_active)->toBeTrue();
});

it('rejects a duplicate department name', function () {
    Department::factory()->create(['name' => 'Housekeeping']);

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/departments'), ['name' => 'Housekeeping', 'is_active' => true])
        ->assertSessionHasErrors('name');
});

it('renames a department everywhere while keeping its slug stable', function () {
    $department = Department::factory()->create(['name' => 'Banquets', 'slug' => 'banquets']);
    $property = Property::factory()->create();
    $property->departments()->create(['department_id' => $department->id, 'manager_name' => 'Sam Lee']);

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/departments/{$department->id}"), ['name' => 'Banquets & Events', 'is_active' => true])
        ->assertRedirect();

    $department->refresh();
    expect($department->name)->toBe('Banquets & Events')
        ->and($department->slug)->toBe('banquets')
        ->and($property->departments()->first()->department->name)->toBe('Banquets & Events');
});

it('hides a deactivated department from the property department dropdown', function () {
    $active = Department::factory()->create(['name' => 'Housekeeping']);
    $retired = Department::factory()->create(['name' => 'Spa']);
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->put(main("/admin/departments/{$retired->id}"), ['name' => 'Spa', 'is_active' => false])
        ->assertRedirect();

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn ($page) => $page
            ->where('catalogs.departments', fn ($departments) => collect($departments)->pluck('name')->doesntContain('Spa')
                && collect($departments)->pluck('name')->contains($active->name)));
});

it('lets a recruiter edit the catalog but payroll only view it', function () {
    Department::factory()->create();

    $this->actingAs(person('recruiter'))
        ->get(main('/admin/departments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.edit', true));

    $this->actingAs(person('payroll'))
        ->get(main('/admin/departments'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('can.edit', false));

    $this->actingAs(person('payroll'))
        ->post(main('/admin/departments'), ['name' => 'Sneaky', 'is_active' => true])
        ->assertForbidden();
});

it('blocks a contractor from the departments catalog', function () {
    $this->actingAs(person('contractor'))
        ->get(main('/admin/departments'))
        ->assertForbidden();
});
