<?php

namespace App\Domain\FieldVisits\Enums;

/**
 * How GPS resolved for a field-visit event (ADR-0017). Unlike contractor clock-in,
 * recruiter GPS is informational: `unavailable`/`denied` are allowed and flagged
 * rather than blocking.
 */
enum GpsStatus: string
{
    case Ok = 'ok';
    case Unavailable = 'unavailable';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Unavailable => 'Unavailable',
            self::Denied => 'Denied',
        };
    }
}
