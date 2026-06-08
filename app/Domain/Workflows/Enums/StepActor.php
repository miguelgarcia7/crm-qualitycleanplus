<?php

namespace App\Domain\Workflows\Enums;

/**
 * Who executes a step: the engine (automatically) or a human (via My Tasks).
 */
enum StepActor: string
{
    case System = 'system';
    case Human = 'human';
}
