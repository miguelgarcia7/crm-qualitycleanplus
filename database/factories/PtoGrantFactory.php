<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoGrantType;
use App\Domain\Pto\Models\PtoGrant;
use App\Domain\Pto\Models\PtoYearAllotment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PtoGrant>
 */
class PtoGrantFactory extends Factory
{
    protected $model = PtoGrant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'year_allotment_id' => PtoYearAllotment::factory(),
            'grant_type' => PtoGrantType::AnnualRefresh,
            'vacation_hours' => 40,
            'scheduled_hours' => 40,
            'unscheduled_hours' => 40,
            'reason' => 'Annual refresh',
            'effective_date' => now()->toDateString(),
        ];
    }
}
