<?php

use App\Domain\PropertyBible\Models\Department;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed([RolePermissionSeeder::class, DepartmentSeeder::class]));

/**
 * @return array<string, mixed>
 */
function propertyPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Marriott Downtown',
        'timezone' => 'America/Phoenix',
        'geofence_radius_meters' => 300,
        'tax_rate' => 0.0875,
        'status' => 'active',
    ], $overrides);
}

it('lets an office manager create a property', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/properties'), propertyPayload())
        ->assertRedirect();

    expect(Property::where('name', 'Marriott Downtown')->exists())->toBeTrue();
});

it('rejects a lone coordinate and an unusable geofence radius', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/properties'), array_merge(propertyPayload(), ['latitude' => 33.44, 'longitude' => null]))
        ->assertSessionHasErrors('longitude');

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/properties'), array_merge(propertyPayload(), ['geofence_radius_meters' => 10]))
        ->assertSessionHasErrors('geofence_radius_meters');

    $this->actingAs(person('office_manager'))
        ->post(main('/admin/properties'), array_merge(propertyPayload(), ['geofence_radius_meters' => 9999]))
        ->assertSessionHasErrors('geofence_radius_meters');
});

it('records property creation in the activity log', function () {
    $this->actingAs(person('office_manager'))
        ->post(main('/admin/properties'), propertyPayload());

    $property = Property::firstWhere('name', 'Marriott Downtown');

    expect(Activity::where('subject_type', $property->getMorphClass())
        ->where('subject_id', $property->id)
        ->where('event', 'created')
        ->count())->toBe(1);
});

it('forbids a recruiter from creating a property', function () {
    $this->actingAs(person('recruiter'))
        ->post(main('/admin/properties'), propertyPayload())
        ->assertForbidden();
});

it('blocks a contractor from the property bible entirely', function () {
    $this->actingAs(person('contractor'))
        ->get(main('/admin/properties'))
        ->assertForbidden();
});

it('shows a super admin every property', function () {
    Property::factory()->count(3)->create();

    $this->actingAs(person('super_admin'))
        ->get(main('/admin/properties'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/properties/index')
            ->has('properties', 3));
});

it('scopes a recruiter to only their assigned properties', function () {
    $assigned = Property::factory()->create();
    Property::factory()->count(2)->create(); // not assigned

    $recruiter = person('recruiter');
    PropertyAssignment::factory()->create([
        'property_id' => $assigned->id,
        'person_id' => $recruiter->id,
    ]);

    $this->actingAs($recruiter)
        ->get(main('/admin/properties'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties', 1)
            ->where('properties.0.id', $assigned->id));
});

it('lets an office manager add a department to a property', function () {
    $property = Property::factory()->create();
    $department = Department::first();

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/properties/{$property->id}/departments"), [
            'department_id' => $department->id,
            'manager_name' => 'Jane Doe',
        ])
        ->assertRedirect();

    expect($property->departments()->count())->toBe(1);
});

it('rejects a duplicate department on the same property', function () {
    $property = Property::factory()->create();
    $department = Department::first();
    $property->departments()->create(['department_id' => $department->id]);

    $this->actingAs(person('office_manager'))
        ->post(main("/admin/properties/{$property->id}/departments"), [
            'department_id' => $department->id,
        ])
        ->assertSessionHasErrors('department_id');
});
