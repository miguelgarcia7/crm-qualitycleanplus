<?php

namespace App\Domain\Billing\Enums;

/**
 * Invoice lifecycle (ADR-0006). Void is Phase 03b.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Invoiced = 'invoiced';
    case InvoiceSent = 'invoice_sent';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Invoiced => 'Invoiced',
            self::InvoiceSent => 'Invoice Sent',
            self::Voided => 'Voided',
        };
    }
}
