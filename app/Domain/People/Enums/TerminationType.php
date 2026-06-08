<?php

namespace App\Domain\People\Enums;

/**
 * How a tenure ended (ADR-0018). Drives reporting and rehire eligibility, not
 * any branching in the workflow itself (every termination runs the same steps).
 */
enum TerminationType: string
{
    case Voluntary = 'voluntary';
    case Involuntary = 'involuntary';
    case JobAbandonment = 'job_abandonment';
    case EndOfAssignment = 'end_of_assignment';

    public function label(): string
    {
        return match ($this) {
            self::Voluntary => 'Voluntary',
            self::Involuntary => 'Involuntary',
            self::JobAbandonment => 'Job Abandonment',
            self::EndOfAssignment => 'End of Assignment',
        };
    }
}
