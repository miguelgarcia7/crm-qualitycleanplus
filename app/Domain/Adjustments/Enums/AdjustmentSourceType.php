<?php

namespace App\Domain\Adjustments\Enums;

/**
 * Where an applied adjustment came from. Manual entries use the catalog; supply
 * requests and their termination consolidation come from the inventory chain
 * (ADR-0014); imports arrive via the Phase 05 import flow.
 */
enum AdjustmentSourceType: string
{
    case Manual = 'manual';
    case SupplyRequest = 'supply_request';
    case SupplyRequestTerminationConsolidation = 'supply_request_termination_consolidation';
    case Import = 'import';
    case Other = 'other';
}
