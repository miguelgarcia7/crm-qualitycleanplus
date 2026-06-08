<?php

namespace App\Domain\Inventory\Enums;

/**
 * Lifecycle of an equipment assignment (ADR-0012, ADR-0018).
 */
enum EquipmentAssignmentStatus: string
{
    case Assigned = 'assigned';
    case Returned = 'returned';
    case Lost = 'lost';
    case Retired = 'retired';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
