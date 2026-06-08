<?php

namespace App\Domain\PropertyBible\Enums;

/**
 * How a property's billable hours arrive. `clock_in` properties capture time via
 * QC Minute / device clock events (Phase 03). `import` properties don't clock in —
 * their hours come from a weekly Excel export uploaded through the import wizard
 * (Phase 05, 40-flows/import-hours.md). Drives which properties the import flow
 * offers and whether the PM-approval gate is skipped (ADR-0007).
 */
enum PropertyTimeSource: string
{
    case ClockIn = 'clock_in';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::ClockIn => 'Clock-in',
            self::Import => 'Import',
        };
    }
}
