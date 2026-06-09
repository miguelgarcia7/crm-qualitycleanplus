<?php

namespace Database\Factories;

use App\Domain\Recruiting\Enums\JobPostingStatus;
use App\Domain\Recruiting\Models\JobPosting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement([
            'Housekeeper',
            'Houseman',
            'Laundry Attendant',
            'Public Area Attendant',
            'Front Desk Agent',
        ]);

        return [
            'status' => JobPostingStatus::Draft,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1000, 9999),
            'pay_range' => '$15 - $18 / hr',
            'content' => fake()->paragraphs(2, true),
            'hour_start' => '08:00',
            'hour_end' => '16:00',
            'property_id' => null,
            'location_label' => fake()->city().', AZ',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => JobPostingStatus::Published]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => JobPostingStatus::Closed]);
    }
}
