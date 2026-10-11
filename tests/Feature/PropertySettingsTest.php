<?php

use App\Domain\Devices\Models\Device;
use App\Domain\PropertyBible\Models\Property;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('saves one settings card without touching the other fields', function () {
    $property = Property::factory()->create(['name' => 'Acme Hotel', 'tax_rate' => 0.0625, 'billing_email' => 'old@acme.test']);

    $this->actingAs(person('office_manager'))
        ->from(main("/admin/properties/{$property->id}"))
        ->patch(main("/admin/properties/{$property->id}"), ['tax_rate' => 0.0875, 'billing_email' => 'ap@acme.test'])
        ->assertRedirect(main("/admin/properties/{$property->id}"))
        ->assertSessionHasNoErrors();

    $property->refresh();
    expect((float) $property->tax_rate)->toBe(0.0875)
        ->and($property->billing_email)->toBe('ap@acme.test')
        ->and($property->name)->toBe('Acme Hotel');

    // History names what changed rather than a generic "updated".
    expect(Activity::query()->whereMorphedTo('subject', $property)->latest('id')->value('description'))
        ->toBe('Updated billing email and tax rate');
});

it('still refuses a sent field that is blank', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/properties/{$property->id}"), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('logs nothing when a save changes nothing', function () {
    $property = Property::factory()->create(['pm_name' => 'Pat Manager']);
    $before = Activity::query()->whereMorphedTo('subject', $property)->count();

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/properties/{$property->id}"), ['pm_name' => 'Pat Manager'])
        ->assertSessionHasNoErrors();

    expect(Activity::query()->whereMorphedTo('subject', $property)->count())->toBe($before);
});

it('turns QR clock-in on and off from its own switch', function () {
    $property = Property::factory()->create(['qr_clock_enabled' => false]);

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/properties/{$property->id}"), ['qr_clock_enabled' => true])
        ->assertSessionHasNoErrors();

    expect($property->refresh())
        ->qr_clock_enabled->toBeTrue()
        ->qr_token->not->toBeNull();
});

it('will not turn QR on without a location, or clear the location while QR is on', function () {
    $noLocation = Property::factory()->create(['latitude' => null, 'longitude' => null, 'qr_clock_enabled' => false]);
    $live = Property::factory()->create(['qr_clock_enabled' => true]);

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/properties/{$noLocation->id}"), ['qr_clock_enabled' => true])
        ->assertSessionHasErrors('qr_clock_enabled');

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/properties/{$live->id}"), ['latitude' => null, 'longitude' => null, 'geofence_radius_meters' => 300])
        ->assertSessionHasErrors('latitude');

    expect($live->refresh()->latitude)->not->toBeNull();
});

it('marks a property inactive from the Status card', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->patch(main("/admin/properties/{$property->id}"), ['status' => 'inactive'])
        ->assertSessionHasNoErrors();

    expect($property->refresh()->status->value)->toBe('inactive');
});

it('forbids someone who cannot edit the property', function () {
    $property = Property::factory()->create(['name' => 'Acme Hotel']);

    $this->actingAs(person('hr'))
        ->patch(main("/admin/properties/{$property->id}"), ['name' => 'Renamed'])
        ->assertForbidden();

    expect($property->refresh()->name)->toBe('Acme Hotel');
});

it('sends old Edit links to the Settings tab', function () {
    $property = Property::factory()->create();

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}/edit"))
        ->assertRedirect(main("/admin/properties/{$property->id}").'#settings/details');
});

it('shows tablets and their pairing codes only to whoever manages devices', function () {
    $property = Property::factory()->create();
    Device::factory()->create(['property_id' => $property->id, 'name' => 'Lobby Tablet', 'activation_code' => 'ACME01']);

    $this->actingAs(person('super_admin'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('devices.0.name', 'Lobby Tablet')
            ->where('devices.0.activation_code', 'ACME01'));

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('devices', null));
});
