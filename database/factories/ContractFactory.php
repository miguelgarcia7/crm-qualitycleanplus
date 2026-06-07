<?php

namespace Database\Factories;

use App\Domain\PropertyBible\Enums\ContractType;
use App\Domain\PropertyBible\Models\Contract;
use App\Domain\PropertyBible\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'name' => 'MSA - '.fake()->company(),
            'type' => ContractType::Msa,
            'effective_date' => now()->subYear()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'notes' => null,
            'is_active' => true,
        ];
    }

    /**
     * A contract whose expiration_date is a given number of days from today.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expiration_date' => now()->addDays($days)->toDateString(),
        ]);
    }
}
