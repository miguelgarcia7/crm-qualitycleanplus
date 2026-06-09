<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\Pto\Enums\PtoBucket;
use App\Domain\Pto\Enums\PtoRequestStatus;
use App\Domain\Pto\Models\PtoRequest;
use App\Domain\Pto\Models\PtoYearAllotment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PtoRequest>
 */
class PtoRequestFactory extends Factory
{
    protected $model = PtoRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'year_allotment_id' => PtoYearAllotment::factory(),
            'bucket' => PtoBucket::Vacation,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'hours' => 8,
            'reason' => 'Time off',
            'status' => PtoRequestStatus::Pending,
            'submitted_at' => now(),
        ];
    }
}
