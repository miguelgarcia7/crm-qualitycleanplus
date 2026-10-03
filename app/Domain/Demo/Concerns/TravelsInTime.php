<?php

namespace App\Domain\Demo\Concerns;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Runs a callback with the clock set to a planned moment. The real clock and
 * billing actions stamp now(); the demo needs them to land at human times (a
 * punch at 7:56, an approval on Monday at 11:00), not at whichever cron tick
 * noticed the step was due.
 */
trait TravelsInTime
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(CarbonInterface $moment, callable $callback): mixed
    {
        // In the app timezone: while a test-now is set, Carbon reads Eloquent's
        // zone-less datetime strings in the test-now's zone, so a moment left
        // in the property's zone would shift every timestamp read back.
        $moment = CarbonImmutable::instance($moment)->setTimezone((string) config('app.timezone'));
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
            CarbonImmutable::setTestNow($previous);
        }
    }
}
