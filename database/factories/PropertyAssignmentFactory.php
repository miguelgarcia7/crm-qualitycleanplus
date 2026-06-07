<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\PropertyBible\Enums\PropertyAssignmentRole;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyAssignment>
 */
class PropertyAssignmentFactory extends Factory
{
    protected $model = PropertyAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'person_id' => Person::factory(),
            'role' => PropertyAssignmentRole::Recruiter,
        ];
    }

    public function propertyManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => PropertyAssignmentRole::PropertyManager,
        ]);
    }
}
