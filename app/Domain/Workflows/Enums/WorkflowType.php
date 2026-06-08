<?php

namespace App\Domain\Workflows\Enums;

/**
 * The catalog of workflow types. Each maps (via the WorkflowRegistry) to a
 * concrete WorkflowDefinition class (ADR-0026). Phase 04a ships supply_request;
 * the people/WO workflows (termination, transfer, …) land in Phase 04b.
 */
enum WorkflowType: string
{
    case SupplyRequest = 'supply_request';

    public function label(): string
    {
        return match ($this) {
            self::SupplyRequest => 'Supply Request',
        };
    }
}
