<?php

namespace App\Domain\FieldVisits\Enums;

/**
 * Lifecycle of a recruiter field visit (ADR-0017). `open` from check-in until
 * check-out; `closed` once checked out (normally or via the forgot-to-check-out path).
 */
enum FieldVisitStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }
}
