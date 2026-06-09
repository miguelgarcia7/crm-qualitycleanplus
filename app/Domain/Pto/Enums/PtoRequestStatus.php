<?php

namespace App\Domain\Pto\Enums;

/**
 * Lifecycle of a PTO request (ADR-0016). `pending` and `approved` both reserve
 * hours (deduct-on-submission); `rejected`/`cancelled` return them. Terminal:
 * approved (until cancelled), rejected, cancelled.
 */
enum PtoRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Statuses that reserve hours against the allotment. */
    public function reservesHours(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }
}
