<?php

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolePermissionSeeder::class);
});

function assignedProperty($recruiter, float $lat = 33.4484, float $lng = -112.0740): Property
{
    $property = Property::factory()->create(['latitude' => $lat, 'longitude' => $lng, 'geofence_radius_meters' => 300]);
    $property->assignments()->create(['person_id' => $recruiter->id, 'role' => PropertyAssignmentRole::Recruiter->value]);

    return $property;
}

/** @return array<string, mixed> */
function checkInPayload(Property $property, float $lat = 33.4484, float $lng = -112.0740, string $status = 'ok'): array
{
    $gps = $status === 'ok' ? ['lat' => $lat, 'lng' => $lng, 'accuracy' => 10] : [];

    return $gps + ['property_id' => $property->id, 'gps_status' => $status, 'selfie' => UploadedFile::fake()->image('selfie.jpg')];
}

it('checks a recruiter in inside the geofence with a selfie', function () {
    $recruiter = person('recruiter');
    $property = assignedProperty($recruiter);

    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($property))->assertRedirect();

    $visit = FieldVisit::query()->firstOrFail();
    expect($visit->status)->toBe(FieldVisitStatus::Open)
        ->and($visit->was_inside_geofence)->toBeTrue()
        ->and($visit->check_in_selfie_file_id)->not->toBeNull()
        ->and($visit->check_in_gps_status)->toBe('ok');
});

it('allows check-in with no GPS, flagged outside geofence', function () {
    $recruiter = person('recruiter');
    $property = assignedProperty($recruiter);

    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($property, status: 'unavailable'))->assertRedirect();

    $visit = FieldVisit::query()->firstOrFail();
    expect($visit->was_inside_geofence)->toBeFalse()
        ->and($visit->check_in_gps_status)->toBe('unavailable');
});

it('checks out an open visit (gps, no selfie)', function () {
    $recruiter = person('recruiter');
    $property = assignedProperty($recruiter);
    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($property));

    $this->actingAs($recruiter)->post(main('/admin/field-visits/check-out'), ['gps_status' => 'ok', 'lat' => 33.4484, 'lng' => -112.0740])
        ->assertRedirect();

    $visit = FieldVisit::query()->firstOrFail();
    expect($visit->status)->toBe(FieldVisitStatus::Closed)
        ->and($visit->check_out_at)->not->toBeNull()
        ->and($visit->was_late_close)->toBeFalse();
});

it('handles forgot-to-check-out: late-closes the old visit then opens the new one', function () {
    $recruiter = person('recruiter');
    $a = assignedProperty($recruiter);
    $b = assignedProperty($recruiter, lat: 34.0000, lng: -111.0000);

    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($a));
    // "I'm at a different property" → late checkout, then check in at B
    $this->actingAs($recruiter)->post(main('/admin/field-visits/check-out'), ['gps_status' => 'ok', 'lat' => 34.0, 'lng' => -111.0, 'late' => true]);
    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($b, lat: 34.0, lng: -111.0))->assertRedirect();

    $visitA = FieldVisit::query()->where('property_id', $a->id)->firstOrFail();
    $visitB = FieldVisit::query()->where('property_id', $b->id)->firstOrFail();
    expect($visitA->status)->toBe(FieldVisitStatus::Closed)
        ->and($visitA->was_late_close)->toBeTrue()
        ->and($visitB->status)->toBe(FieldVisitStatus::Open);
});

it('blocks a second check-in while one is open', function () {
    $recruiter = person('recruiter');
    $a = assignedProperty($recruiter);
    $b = assignedProperty($recruiter);
    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($a));

    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($b))->assertSessionHasErrors('visit');

    expect(FieldVisit::query()->count())->toBe(1);
});

it('forbids a user without the permission from checking in', function () {
    $payroll = person('payroll'); // back office, but no field_visits.create
    $property = Property::factory()->create(['latitude' => 33.4, 'longitude' => -112.0]);

    $this->actingAs($payroll)->post(main('/admin/field-visits'), checkInPayload($property))->assertForbidden();
});

it('scopes the history to own visits for a recruiter but all for an admin', function () {
    $recruiter = person('recruiter');
    $property = assignedProperty($recruiter);
    FieldVisit::factory()->create(['person_id' => $recruiter->id, 'property_id' => $property->id]);
    FieldVisit::factory()->create(['property_id' => $property->id]); // someone else's

    $this->actingAs($recruiter)->get(main('/admin/field-visits'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('visits', fn ($v) => collect($v)->count() === 1));

    $this->actingAs(person('office_manager'))->get(main('/admin/field-visits'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('visits', fn ($v) => collect($v)->count() === 2));
});

it('filters the history to off-geofence visits', function () {
    $admin = person('admin');
    $property = Property::factory()->create();
    FieldVisit::factory()->create(['property_id' => $property->id, 'was_inside_geofence' => true]);
    FieldVisit::factory()->create(['property_id' => $property->id, 'was_inside_geofence' => false]);

    $this->actingAs($admin)->get(main('/admin/field-visits?filter=off_geofence'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('visits', fn ($v) => collect($v)->count() === 1));
});
