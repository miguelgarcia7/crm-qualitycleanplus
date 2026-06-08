<?php

namespace Database\Factories;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workflow>
 */
class WorkflowFactory extends Factory
{
    protected $model = Workflow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => WorkflowType::SupplyRequest->value,
            'subject_type' => null,
            'subject_id' => null,
            'initiator_id' => Person::factory(),
            'status' => WorkflowStatus::Pending,
            'current_step_index' => 0,
            'data' => [],
        ];
    }
}
