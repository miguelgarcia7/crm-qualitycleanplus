<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\Recruiting\Enums\JobApplicationStatus;
use App\Domain\Recruiting\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'job_posting_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'desired_position' => 'Housekeeper',
            'desired_salary' => '$16 / hr',
            'desired_start_date' => now()->addWeek()->toDateString(),
            'transportation' => true,
            'work_at_qcp' => false,
            'another_staff_agency' => false,
            'convicted_felon' => false,
            'acknowledgement' => true,
            'status' => JobApplicationStatus::Submitted,
            'submitted_at' => now(),
        ];
    }

    public function reviewing(): static
    {
        return $this->state(fn () => ['status' => JobApplicationStatus::Reviewing]);
    }
}
