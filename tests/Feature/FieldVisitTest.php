<?php

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Enums\PropertyStatus;
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

it('counts what each flag would show', function () {
    $admin = person('admin');
    FieldVisit::factory()->create(); // open, inside the geofence
    FieldVisit::factory()->closed()->create(['was_inside_geofence' => false]);
    FieldVisit::factory()->closed()->create(['was_late_close' => true]);

    $this->actingAs($admin)->get(main('/admin/field-visits?flag=open'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('visits', 1)
            ->where('counts', ['' => 3, 'open' => 1, 'stale' => 0, 'off_site' => 1, 'late' => 1, 'covering' => 0]),
        );
});

it('lets a recruiter cover a hotel they aren’t assigned to, flagged Covering', function () {
    $recruiter = person('recruiter');
    $mine = assignedProperty($recruiter);
    $other = Property::factory()->create(['latitude' => 33.4484, 'longitude' => -112.0740, 'geofence_radius_meters' => 300]);

    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($other))->assertSessionHasNoErrors();
    $this->actingAs($recruiter)->post(main('/admin/field-visits/check-out'), ['gps_status' => 'ok', 'lat' => 33.4484, 'lng' => -112.0740]);
    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($mine))->assertSessionHasNoErrors();

    expect(FieldVisit::query()->where('property_id', $other->id)->value('was_covering'))->toBeTrue()
        ->and(FieldVisit::query()->where('property_id', $mine->id)->value('was_covering'))->toBeFalse();

    $this->actingAs(person('admin'))->get(main('/admin/field-visits?flag=covering'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('visits', 1)->where('visits.0.covering', true));
});

it('won’t check in at an inactive hotel', function () {
    $recruiter = person('recruiter');
    $closed = assignedProperty($recruiter);
    $closed->update(['status' => PropertyStatus::Inactive]);

    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($closed))->assertSessionHasErrors('property_id');
});

it('keeps the recruiter’s note from check-out', function () {
    $recruiter = person('recruiter');
    $property = assignedProperty($recruiter);
    $this->actingAs($recruiter)->post(main('/admin/field-visits'), checkInPayload($property));

    $this->actingAs($recruiter)
        ->post(main('/admin/field-visits/check-out'), ['gps_status' => 'denied', 'notes' => 'Met with the housekeeping manager'])
        ->assertSessionHasNoErrors();

    expect(FieldVisit::query()->value('notes'))->toBe('Met with the housekeeping manager');
});

it('gives the check-in panel every active hotel, the recruiter’s own marked', function () {
    $recruiter = person('recruiter');
    $mine = assignedProperty($recruiter);
    $other = Property::factory()->create();
    Property::factory()->create(['status' => PropertyStatus::Inactive]);

    $properties = collect($this->actingAs($recruiter)->getJson(main('/admin/field-visits/context'))->assertOk()->json('properties'));

    expect($properties)->toHaveCount(2)
        ->and($properties->firstWhere('id', $mine->id)['mine'])->toBeTrue()
        ->and($properties->firstWhere('id', $other->id)['mine'])->toBeFalse();
});

it('finds visits by recruiter, hotel, search and dates, paged on the server', function () {
    $this->travelTo('2026-10-13 15:00:00');
    $rita = tap(person('recruiter'))->update(['name' => 'Rita Recruiter']);
    $ray = tap(person('recruiter'))->update(['name' => 'Ray Recruiter']);
    $acme = Property::factory()->create(['name' => 'Acme Hotel', 'timezone' => 'America/Chicago']);
    $mag = Property::factory()->create(['name' => 'MAG Solutions']);
    FieldVisit::factory()->closed()->create(['person_id' => $rita->id, 'property_id' => $acme->id, 'check_in_at' => '2026-10-09 14:05:00', 'check_out_at' => '2026-10-09 14:52:00']);
    FieldVisit::factory()->closed()->create(['person_id' => $ray->id, 'property_id' => $mag->id, 'check_in_at' => '2026-10-05 16:00:00', 'check_out_at' => '2026-10-05 16:44:00']);
    FieldVisit::factory()->closed()->create(['person_id' => $rita->id, 'property_id' => $mag->id, 'check_in_at' => '2026-07-01 15:00:00', 'check_out_at' => '2026-07-01 15:30:00']);

    $admin = person('admin');
    $total = fn (string $query) => $this->actingAs($admin)->get(main('/admin/field-visits'.$query))->viewData('page')['props']['pagination']['total'];

    expect($total(''))->toBe(2) // last 30 days by default
        ->and($total('?range=all'))->toBe(3)
        ->and($total("?range=all&recruiter_id={$rita->id}"))->toBe(2)
        ->and($total("?range=all&property_id={$mag->id}"))->toBe(2)
        ->and($total('?range=all&search=acme'))->toBe(1)
        ->and($total('?range=all&search=ray'))->toBe(1)
        ->and($total('?range=custom&from=2026-07-01&to=2026-07-31'))->toBe(1)
        ->and($total('?range=last_month'))->toBe(0);

    $this->actingAs($admin)->get(main('/admin/field-visits'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('visits.0.property', 'Acme Hotel')
            ->where('visits.0.in', '9:05 AM')
            ->where('visits.0.minutes', 47));

    FieldVisit::factory()->count(12)->closed()->create(['person_id' => $ray->id, 'property_id' => $mag->id, 'check_in_at' => '2026-10-10 12:00:00']);
    $this->actingAs($admin)->get(main('/admin/field-visits?per_page=10&page=2'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('visits', 4)->where('pagination.total', 14));
});

it('shows open visits whatever their date, and those open over a day', function () {
    $this->travelTo('2026-10-13 15:00:00');
    FieldVisit::factory()->create(['check_in_at' => '2026-08-01 15:00:00']); // forgotten for weeks
    FieldVisit::factory()->create(['check_in_at' => '2026-10-13 13:00:00']); // checked in 2 h ago

    $this->actingAs(person('admin'))->get(main('/admin/field-visits?flag=open'))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('visits', 2)->where('counts.stale', 1)->where('counts.open', 2));

    // The old dashboard link (filter=open) still lands on open visits.
    $this->actingAs(person('admin'))->get(main('/admin/field-visits?filter=open'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('filters.flag', 'open'));
});

it('exports what the list shows to Excel', function () {
    $recruiter = person('recruiter');
    $property = assignedProperty($recruiter);
    FieldVisit::factory()->closed()->create(['person_id' => $recruiter->id, 'property_id' => $property->id, 'was_covering' => true]);

    $this->actingAs(person('office_manager'))->get(main('/admin/field-visits/export?flag=covering'))
        ->assertOk()
        ->assertDownload();

    $this->actingAs(person('payroll'))->get(main('/admin/field-visits/export'))->assertForbidden();
});
