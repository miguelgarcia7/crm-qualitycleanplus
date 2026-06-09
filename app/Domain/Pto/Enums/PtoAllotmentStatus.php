<?php

namespace App\Domain\Pto\Enums;

/**
 * Lifecycle of a hire-anniversary PTO year (ADR-0016). `open` is the active year;
 * `closed`/`forfeited` are prior years whose unused hours were lost (no rollover).
 */
enum PtoAllotmentStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Forfeited = 'forfeited';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
            self::Forfeited => 'Forfeited',
        };
    }
}
