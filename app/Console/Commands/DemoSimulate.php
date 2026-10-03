<?php

namespace App\Console\Commands;

use App\Domain\Demo\Actions\AdvanceDemoBilling;
use App\Domain\Demo\Actions\SimulateClock;
use App\Domain\Demo\DemoRoster;
use App\Domain\PropertyBible\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Brings the Acme Hotel demo up to now (docs/80-plan/demo-environment.md):
 * any contractor punches that have come due, then any billing steps. Scheduled
 * every few minutes in demo mode; safe to run by hand at any time.
 */
class DemoSimulate extends Command
{
    protected $signature = 'demo:simulate';

    protected $description = 'Advance the Acme Hotel demo to now: contractor punches and weekly billing steps';

    public function handle(SimulateClock $clock, AdvanceDemoBilling $billing): int
    {
        if ($this->laravel->isProduction() || ! config('demo.enabled')) {
            $this->error('demo:simulate only runs with DEMO_MODE=true, and never in production.');

            return self::FAILURE;
        }

        $property = Property::query()->where('name', DemoRoster::PROPERTY)->first();
        if ($property === null) {
            $this->warn('There is no '.DemoRoster::PROPERTY.' yet — run demo:reset first.');

            return self::SUCCESS;
        }

        // From the start of last week, so punches missed while the environment
        // was down are filled in until that week goes to the PM on Monday.
        $now = CarbonImmutable::now();
        $from = $property->weekStartFor($now->setTimezone($property->timezone))->subWeek();

        $punches = $clock->handle($property, $from, $now);
        $billing->handle($property, $now);

        $this->info("Recorded {$punches} punches.");

        return self::SUCCESS;
    }
}
