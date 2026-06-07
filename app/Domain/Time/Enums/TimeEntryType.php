<?php

namespace App\Domain\Time\Enums;

/**
 * Whether the entry is paid work or (separately bucketed) training.
 */
enum TimeEntryType: string
{
    case Work = 'work';
    case Training = 'training';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
