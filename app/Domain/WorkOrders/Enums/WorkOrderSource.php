<?php

namespace App\Domain\WorkOrders\Enums;

/**
 * How a work order came to exist (20-domain/work-orders.md). Only
 * recruiter_created + imported are used in Phase 03; the workflow sources
 * arrive with Phase 04.
 */
enum WorkOrderSource: string
{
    case RecruiterCreated = 'recruiter_created';
    case Imported = 'imported';
    case PayIncrease = 'pay_increase';
    case Transfer = 'transfer';
    case TemporaryAssignment = 'temporary_assignment';

    public function label(): string
    {
        return match ($this) {
            self::RecruiterCreated => 'Recruiter Created',
            self::Imported => 'Imported',
            self::PayIncrease => 'Pay Increase',
            self::Transfer => 'Transfer',
            self::TemporaryAssignment => 'Temporary Assignment',
        };
    }
}
