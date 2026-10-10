<?php

use App\Domain\PropertyBible\Models\Position;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyPositionRate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    // A Wednesday afternoon in Phoenix.
    $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));
});

it('marks each rate as current, scheduled or past at the property', function () {
    $property = Property::factory()->create();
    $position = Position::factory()->create(['name' => 'Housekeeper']);
    $rate = fn (array $attributes) => PropertyPositionRate::factory()->create(['property_id' => $property->id, 'position_id' => $position->id, ...$attributes]);

    $rate(['effective_date' => '2026-01-01', 'end_date' => '2026-06-30']);
    $rate(['effective_date' => '2026-07-01', 'end_date' => '2026-10-11']);
    $rate(['effective_date' => '2026-10-12', 'end_date' => null]);

    $this->actingAs(person('office_manager'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewRates', true)
            // Newest first within the position.
            ->where('rates.0.effective_date', '2026-10-12')
            ->where('rates.0.state', 'scheduled')
            ->where('rates.1.state', 'current')
            ->where('rates.2.state', 'past'),
        );
});

it('keeps rates and job codes from someone who cannot see rates', function () {
    $property = Property::factory()->create();
    PropertyPositionRate::factory()->create(['property_id' => $property->id]);

    // HR sees the property but not what it pays or bills.
    $this->actingAs(person('hr'))
        ->get(main("/admin/properties/{$property->id}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.viewRates', false)
            ->where('rates', [])
            ->where('jobCodes', []),
        );
});
