<?php

namespace App\Domain\Inventory\Enums;

/**
 * Who a supply request is for (ADR-0012).
 */
enum BeneficiaryType: string
{
    case SelfRequest = 'self';
    case Contractor = 'contractor';
    case GeneralOffice = 'general_office';

    public function label(): string
    {
        return match ($this) {
            self::SelfRequest => 'Self',
            self::Contractor => 'Contractor',
            self::GeneralOffice => 'General Office',
        };
    }
}
