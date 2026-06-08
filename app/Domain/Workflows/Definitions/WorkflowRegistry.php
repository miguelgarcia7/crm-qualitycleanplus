<?php

namespace App\Domain\Workflows\Definitions;

use App\Domain\Workflows\Enums\WorkflowType;
use InvalidArgumentException;

/**
 * Maps each WorkflowType to its concrete WorkflowDefinition class. Definitions
 * live in their owning context (e.g. SupplyRequestDefinition under Inventory);
 * they're registered here from a service provider so the Workflows context does
 * not depend on the others (ADR-0026).
 */
class WorkflowRegistry
{
    /** @var array<string, class-string<WorkflowDefinition>> */
    private array $map = [];

    /**
     * @param  class-string<WorkflowDefinition>  $definitionClass
     */
    public function register(WorkflowType $type, string $definitionClass): void
    {
        $this->map[$type->value] = $definitionClass;
    }

    public function for(WorkflowType $type): WorkflowDefinition
    {
        $class = $this->map[$type->value] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("No workflow definition registered for type [{$type->value}].");
        }

        return app($class);
    }
}
