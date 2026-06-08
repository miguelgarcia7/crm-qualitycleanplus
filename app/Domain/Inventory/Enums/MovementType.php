<?php

namespace App\Domain\Inventory\Enums;

/**
 * The kind of a stock movement (ADR-0012, ADR-0015). Determines the sign and
 * whether a reason is required.
 */
enum MovementType: string
{
    case PurchaseOrderReceipt = 'purchase_order_receipt';
    case DirectReceipt = 'direct_receipt';
    case CountCorrection = 'count_correction';
    case Issuance = 'issuance';
    case ManualIssuance = 'manual_issuance';
    case Return = 'return';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseOrderReceipt => 'PO Receipt',
            self::DirectReceipt => 'Direct Receipt',
            self::CountCorrection => 'Count Correction',
            self::Issuance => 'Issuance',
            self::ManualIssuance => 'Manual Issuance',
            self::Return => 'Return',
        };
    }

    /** Inflows add stock; outflows subtract. Count correction is signed by caller. */
    public function isInflow(): bool
    {
        return in_array($this, [self::PurchaseOrderReceipt, self::DirectReceipt, self::Return], true);
    }

    public function isOutflow(): bool
    {
        return in_array($this, [self::Issuance, self::ManualIssuance], true);
    }

    /** Movements that demand an explicit reason for the audit trail. */
    public function requiresReason(): bool
    {
        return in_array($this, [self::DirectReceipt, self::CountCorrection, self::ManualIssuance, self::Return], true);
    }
}
