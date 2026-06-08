<?php

namespace App\Domain\Workflows\Definitions;

use App\Domain\Workflows\Enums\StepActor;
use App\Domain\Workflows\Enums\StepType;

/**
 * A declarative description of one step, returned by a WorkflowDefinition. The
 * engine materializes these into workflow_steps rows when a workflow starts.
 */
final class StepBlueprint
{
    public function __construct(
        public string $key,
        public string $name,
        public StepType $type,
        public StepActor $actor = StepActor::Human,
        public ?int $assignedTo = null,
        public ?string $assignedRole = null,
        public ?string $requiredPermission = null,
    ) {}

    public static function system(string $key, string $name, StepType $type = StepType::Action): self
    {
        return new self($key, $name, $type, StepActor::System);
    }

    public static function humanRole(string $key, string $name, string $role, ?string $permission = null, StepType $type = StepType::Action): self
    {
        return new self($key, $name, $type, StepActor::Human, assignedRole: $role, requiredPermission: $permission);
    }

    public static function humanPerson(string $key, string $name, int $personId, ?string $permission = null, StepType $type = StepType::Action): self
    {
        return new self($key, $name, $type, StepActor::Human, assignedTo: $personId, requiredPermission: $permission);
    }
}
