<?php

namespace App\Domain\PropertyBible\Enums;

enum HolidayType: string
{
    case Legal = 'legal';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Legal => 'Legal',
            self::Custom => 'Custom',
        };
    }
}
