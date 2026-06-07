<?php

namespace App\Domain\Billing\Enums;

/**
 * Timesheet approval lifecycle (ADR-0007, 20-domain/timesheets.md).
 */
enum TimesheetStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Declined = 'declined';
    case Approved = 'approved';
    case Invoiced = 'invoiced';
    case InvoiceSent = 'invoice_sent';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Declined => 'Declined',
            self::Approved => 'Approved',
            self::Invoiced => 'Invoiced',
            self::InvoiceSent => 'Invoice Sent',
            self::Voided => 'Voided',
        };
    }

    /** A recruiter may submit from draft or declined. */
    public function canSubmit(): bool
    {
        return in_array($this, [self::Draft, self::Declined], true);
    }

    /** A PM may act only while pending. */
    public function canDecide(): bool
    {
        return $this === self::PendingApproval;
    }
}
