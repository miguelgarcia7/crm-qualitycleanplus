<?php

namespace App\Console\Commands;

use App\Domain\PropertyBible\Models\Property;
use App\Domain\Reports\Actions\RefreshMonthlyRevenue;
use App\Domain\Reports\Actions\RefreshWeeklyRollup;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Rebuilds report rollups over a date window (ADR-0028). Targeted triggers keep
 * cells fresh in real time; this nightly backstop heals drift from replayed
 * imports, manual fixes, or missed triggers. Every (property, week|month) cell
 * in the window is rebuilt — empty cells are deleted, so it also un-counts.
 */
class RefreshReportRollups extends Command
{
    protected $signature = 'reports:refresh-rollups
        {--from= : Start date (default: 90 days ago)}
        {--to= : End date (default: today)}';

    protected $description = 'Rebuild the report rollup tables for a date window';

    public function handle(RefreshWeeklyRollup $weekly, RefreshMonthlyRevenue $monthly): int
    {
        $from = CarbonImmutable::parse($this->option('from') ?? now()->subDays(90)->toDateString());
        $to = CarbonImmutable::parse($this->option('to') ?? now()->toDateString());

        if ($from->greaterThan($to)) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $months = [];
        for ($month = $from->startOfMonth(); $month->lessThanOrEqualTo($to); $month = $month->addMonth()) {
            $months[] = $month->toDateString();
        }

        $cells = 0;
        Property::query()->each(function (Property $property) use ($weekly, $monthly, $from, $to, $months, &$cells): void {
            // Weeks are property-anchored (closing day, ADR-0009) — walk each
            // property's own week sequence across the window.
            for ($week = $property->weekStartFor($from); $week->lessThanOrEqualTo($to); $week = $week->addWeek()) {
                $weekly->handle($property->id, $week->toDateString());
                $cells++;
            }
            foreach ($months as $monthStart) {
                $monthly->handle($property->id, $monthStart);
                $cells++;
            }
        });

        $this->info("Rollups refreshed: {$cells} cells ({$from->toDateString()} → {$to->toDateString()}).");

        return self::SUCCESS;
    }
}
