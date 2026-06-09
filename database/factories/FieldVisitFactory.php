<?php

namespace Database\Factories;

use App\Domain\FieldVisits\Enums\FieldVisitStatus;
use App\Domain\FieldVisits\Models\FieldVisit;
use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldVisit>
 */
class FieldVisitFactory extends Factory
{
    protected $model = FieldVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'property_id' => Property::factory(),
            'status' => FieldVisitStatus::Open,
            'check_in_at' => now(),
            'check_in_gps_status' => 'ok',
            'was_inside_geofence' => true,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => FieldVisitStatus::Closed,
            'check_out_at' => now()->addHour(),
            'check_out_gps_status' => 'ok',
        ]);
    }
}
