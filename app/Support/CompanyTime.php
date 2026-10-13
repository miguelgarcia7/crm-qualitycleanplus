<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The company's clock (Central time, config app.company_timezone).
 *
 * Timestamps are stored in UTC. Anything the office does — a request, an
 * approval, an audit entry — is shown in company time, and "today" on the
 * office's screens and in the nightly jobs is the company's today. Work at a
 * property is the exception: punches, the work day and invoice due dates use
 * that property's own timezone (ADR-0030).
 *
 * Careful with queries: the database compares the wall-clock text of a bound
 * date, so compare a UTC column against `->utc()`, never against a Central
 * time value directly.
 */
final class CompanyTime
{
    public static function zone(): string
    {
        return (string) config('app.company_timezone', 'America/Chicago');
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zone());
    }

    /** Midnight at the start of the company's today. */
    public static function today(): CarbonImmutable
    {
        return self::now()->startOfDay();
    }

    /**
     * The company's calendar date as a plain date (midnight UTC), the same
     * shape as a DATE column, for date math against hire dates, end dates
     * and the like.
     */
    public static function date(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::now()->toDateString(), 'UTC');
    }

    /** A stored (UTC) timestamp on the company's clock, ready to format. */
    public static function of(?CarbonInterface $at): ?CarbonImmutable
    {
        return $at === null ? null : CarbonImmutable::instance($at)->setTimezone(self::zone());
    }
}
