<?php

namespace Database\Factories;

use App\Domain\Devices\Models\Device;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => 'Front Desk Tablet',
            'activation_code' => Device::newCode(),
            'is_activated' => false,
        ];
    }

    public function activated(): static
    {
        return $this->state(fn () => ['is_activated' => true, 'last_seen_at' => now()]);
    }
}
