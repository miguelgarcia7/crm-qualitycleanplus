<?php

namespace App\Domain\Workflows\Enums;

/**
 * Lifecycle of a single materialized workflow step.
 */
enum StepStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Skipped = 'skipped';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Done => 'Done',
            self::Skipped => 'Skipped',
            self::Rejected => 'Rejected',
        };
    }
}
