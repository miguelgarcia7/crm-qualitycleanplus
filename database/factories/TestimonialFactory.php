<?php

namespace Database\Factories;

use App\Domain\Marketing\Enums\TestimonialSource;
use App\Domain\Marketing\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'quote' => fake()->sentence(14),
            'source' => fake()->randomElement(TestimonialSource::cases()),
            'rating' => 5,
            'is_active' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
