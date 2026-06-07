<?php

namespace App\Domain\PropertyBible\Enums;

/**
 * Type of a property contract document. See 20-domain/property-bible.md §4.
 */
enum ContractType: string
{
    case Msa = 'msa';
    case Addendum = 'addendum';
    case Sow = 'sow';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Msa => 'Master Services Agreement',
            self::Addendum => 'Addendum',
            self::Sow => 'Statement of Work',
            self::Other => 'Other',
        };
    }
}
