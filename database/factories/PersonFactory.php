<?php

namespace Database\Factories;

use App\Enums\PersonStatus;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->phoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => PersonStatus::StaffActive,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    public function staffActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PersonStatus::StaffActive,
            'hire_date' => now()->subYear(),
        ]);
    }

    public function contractorActive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PersonStatus::ContractorActive,
            'application_date' => now()->subMonths(2),
            'converted_to_contractor_at' => now()->subMonth(),
        ]);
    }
}
