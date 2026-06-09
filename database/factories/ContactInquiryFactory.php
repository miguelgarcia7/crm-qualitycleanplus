<?php

namespace Database\Factories;

use App\Domain\Marketing\Models\ContactInquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactInquiry>
 */
class ContactInquiryFactory extends Factory
{
    protected $model = ContactInquiry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'job_seeker',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->numerify('###-###-####'),
            'call_back_time' => 'Daytime',
            'message' => fake()->sentence(),
        ];
    }

    public function business(): static
    {
        return $this->state(fn () => [
            'type' => 'business',
            'company' => fake()->company(),
            'inquiry_type' => 'Looking to Hire for Team',
            'call_back_time' => null,
        ]);
    }
}
