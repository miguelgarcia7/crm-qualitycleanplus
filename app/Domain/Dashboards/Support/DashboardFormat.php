<?php

namespace App\Domain\Dashboards\Support;

use App\Domain\People\Models\Person;
use Carbon\CarbonImmutable;

/** Small text helpers the role dashboards share, so every card words dates and counts alike. */
class DashboardFormat
{
    /** "Good morning, Rita", by the hour where the office is. */
    public static function greeting(Person $user, CarbonImmutable $now): string
    {
        $part = match (true) {
            $now->hour < 12 => 'Good morning',
            $now->hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        return $part.', '.strtok((string) $user->name, ' ');
    }

    /** "Oct 5 – 11", "Sep 28 – Oct 4", or "Oct 5" for a single day. */
    public static function range(CarbonImmutable $start, CarbonImmutable $end): string
    {
        if ($start->isSameDay($end)) {
            return $start->format('M j');
        }

        return $start->format('M j').' – '.($start->isSameMonth($end) ? $end->format('j') : $end->format('M j'));
    }

    /** "1 property", "12 properties". */
    public static function plural(int $count, string $one, string $many): string
    {
        return number_format($count).' '.($count === 1 ? $one : $many);
    }

    /** "today", "1 day", "12 days". */
    public static function age(CarbonImmutable $since, CarbonImmutable $now): string
    {
        $days = (int) $since->diffInDays($now);

        return $days < 1 ? 'today' : self::plural($days, 'day', 'days');
    }
}
