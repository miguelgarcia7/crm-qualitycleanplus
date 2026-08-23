<?php

namespace App\Domain\PropertyBible\Support;

use App\Domain\PropertyBible\Enums\HolidayType;
use App\Domain\PropertyBible\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Resolves a holiday to its concrete date in a given year — always in the
 * property's timezone, so the local date string matches what a punch's local
 * start date will compare against (a UTC-resolved date can be off by one).
 * Custom holidays are validated at creation (checkdate), so resolution never
 * throws for them; an unknown legal rule is a programmer error and does throw.
 */
class HolidayDateResolver
{
    public static function resolve(Holiday $holiday, int $year, string $timezone): CarbonImmutable
    {
        if ($holiday->type === HolidayType::Custom) {
            return CarbonImmutable::create($year, (int) $holiday->month, (int) $holiday->day, 0, 0, 0, $timezone);
        }

        return match ($holiday->rule) {
            'january_1' => CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone),
            'july_4' => CarbonImmutable::create($year, 7, 4, 0, 0, 0, $timezone),
            'december_24' => CarbonImmutable::create($year, 12, 24, 0, 0, 0, $timezone),
            'december_25' => CarbonImmutable::create($year, 12, 25, 0, 0, 0, $timezone),
            'last_monday_may' => CarbonImmutable::create($year, 5, 1, 0, 0, 0, $timezone)->lastOfMonth(CarbonInterface::MONDAY),
            'first_monday_september' => CarbonImmutable::create($year, 9, 1, 0, 0, 0, $timezone)->firstOfMonth(CarbonInterface::MONDAY),
            'fourth_thursday_november' => CarbonImmutable::create($year, 11, 1, 0, 0, 0, $timezone)->nthOfMonth(4, CarbonInterface::THURSDAY),
            default => throw new InvalidArgumentException("Unknown holiday rule [{$holiday->rule}]."),
        };
    }
}
