<?php

namespace Database\Factories;

use App\Domain\People\Enums\ReasonCategory;
use App\Domain\People\Enums\TerminationType;
use App\Domain\People\Models\Person;
use App\Domain\People\Models\TerminationRecord;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TerminationRecord>
 */
class TerminationRecordFactory extends Factory
{
    protected $model = TerminationRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'person_id' => Person::factory(),
            'initiated_by' => Person::factory(),
            'effective_date' => now()->toDateString(),
            'termination_type' => TerminationType::Voluntary,
            'reason_category' => ReasonCategory::Resignation,
            'notes' => null,
            'rehireable' => true,
        ];
    }
}
