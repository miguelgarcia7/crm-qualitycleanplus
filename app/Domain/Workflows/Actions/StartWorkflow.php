<?php

namespace App\Domain\Workflows\Actions;

use App\Domain\People\Models\Person;
use App\Domain\Workflows\Definitions\WorkflowRegistry;
use App\Domain\Workflows\Enums\WorkflowStatus;
use App\Domain\Workflows\Enums\WorkflowType;
use App\Domain\Workflows\Models\Workflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Creates a workflow instance, materializes its steps from the definition's
 * blueprint, then advances past any leading `system` steps. Returns the fresh
 * workflow.
 */
class StartWorkflow
{
    public function __construct(
        private WorkflowRegistry $registry,
        private AdvanceWorkflow $advance,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(WorkflowType $type, ?Model $subject, Person $initiator, array $data = []): Workflow
    {
        return DB::transaction(function () use ($type, $subject, $initiator, $data): Workflow {
            $definition = $this->registry->for($type);

            $workflow = Workflow::create([
                'type' => $type->value,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'initiator_id' => $initiator->id,
                'status' => WorkflowStatus::Pending,
                'current_step_index' => 0,
                'data' => $data,
            ]);

            foreach ($definition->steps($workflow) as $index => $blueprint) {
                $workflow->steps()->create([
                    'step_index' => $index,
                    'step_key' => $blueprint->key,
                    'name' => $blueprint->name,
                    'step_type' => $blueprint->type,
                    'actor' => $blueprint->actor,
                    'assigned_to' => $blueprint->assignedTo,
                    'assigned_role' => $blueprint->assignedRole,
                    'required_permission' => $blueprint->requiredPermission,
                ]);
            }

            $definition->onStart($workflow);
            $this->advance->handle($workflow);

            return $workflow->refresh();
        });
    }
}
