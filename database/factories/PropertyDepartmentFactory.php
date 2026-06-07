<?php

namespace Database\Factories;

use App\Domain\PropertyBible\Models\Department;
use App\Domain\PropertyBible\Models\Property;
use App\Domain\PropertyBible\Models\PropertyDepartment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PropertyDepartment>
 */
class PropertyDepartmentFactory extends Factory
{
    protected $model = PropertyDepartment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'department_id' => Department::factory(),
            'manager_name' => fake()->name(),
            'manager_phone' => fake()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
