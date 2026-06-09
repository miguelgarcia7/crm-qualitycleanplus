<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoAllotmentStatus;
use App\Domain\Pto\Enums\PtoTier;
use App\Domain\Pto\Models\PtoYearAllotment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PtoYearAllotment>
 */
class PtoYearAllotmentFactory extends Factory
{
    protected $model = PtoYearAllotment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subMonths(2)->startOfDay();

        return [
            'person_id' => Person::factory(),
            'year_start' => $start->toDateString(),
            'year_end' => $start->copy()->addYear()->toDateString(),
            'tier_at_year_start' => PtoTier::Tier3->value,
            'vacation_allotment' => 40,
            'scheduled_allotment' => 40,
            'unscheduled_allotment' => 40,
            'status' => PtoAllotmentStatus::Open,
        ];
    }
}
