<?php

namespace App\Console\Commands;

use App\Domain\LegacyImport\Steps\ImportComments;
use App\Domain\LegacyImport\Steps\ImportFiles;
use App\Domain\LegacyImport\Steps\ImportHiringFees;
use App\Domain\LegacyImport\Steps\ImportInvoices;
use App\Domain\LegacyImport\Steps\ImportPayrollPeriods;
use App\Domain\LegacyImport\Steps\ImportPeople;
use App\Domain\LegacyImport\Steps\ImportPositions;
use App\Domain\LegacyImport\Steps\ImportProperties;
use App\Domain\LegacyImport\Steps\ImportTimeEntries;
use App\Domain\LegacyImport\Steps\ImportTimesheets;
use App\Domain\LegacyImport\Steps\ImportWorkOrders;
use App\Domain\LegacyImport\Steps\RecomputeSummaries;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The QC Minute cutover importer (docs/80-plan/phase-final-cutover.md): reads
 * the legacy database over the read-only `legacy` connection and writes into
 * this app in dependency order. Idempotent — every step records its ids in
 * legacy_id_map, so a re-run updates instead of duplicating.
 */
class LegacyImport extends Command
{
    protected $signature = 'legacy:import
        {--only=* : Run only these steps}
        {--list : Show the step order and exit}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Import the legacy QC Minute database (see docs/80-plan/phase-final-cutover.md)';

    /** Step name => class, in dependency order. Null = planned, not built yet. */
    private const STEPS = [
        'people' => ImportPeople::class,
        'properties' => ImportProperties::class,
        'positions' => ImportPositions::class,
        'work-orders' => ImportWorkOrders::class,
        'payroll-periods' => ImportPayrollPeriods::class,
        'time-entries' => ImportTimeEntries::class,
        'timesheets' => ImportTimesheets::class,
        'invoices' => ImportInvoices::class,
        'hiring-fees' => ImportHiringFees::class,
        'comments' => ImportComments::class,
        'files' => ImportFiles::class,
        'summaries' => RecomputeSummaries::class,
    ];

    public function handle(): int
    {
        if ($this->option('list')) {
            foreach (array_keys(self::STEPS) as $name) {
                $this->line($name);
            }

            return self::SUCCESS;
        }

        $source = (string) config('database.connections.legacy.database');
        $target = (string) config('database.connections.'.config('database.default').'.database');

        if ($source === $target) {
            $this->error("The legacy connection points at the app's own database ({$target}).");

            return self::FAILURE;
        }

        try {
            $userCount = DB::connection('legacy')->table('users')->count();
        } catch (\Throwable $e) {
            $this->error("Cannot read the legacy database `{$source}`: {$e->getMessage()}");

            return self::FAILURE;
        }

        $only = (array) $this->option('only');
        $unknown = array_diff($only, array_keys(self::STEPS));

        if ($unknown !== []) {
            $this->error('Unknown step(s): '.implode(', ', $unknown).'. Use --list to see them.');

            return self::FAILURE;
        }

        $steps = $only === []
            ? self::STEPS
            : array_intersect_key(self::STEPS, array_flip($only));

        $this->info("Importing from `{$source}` ({$userCount} legacy users) into `{$target}`.");

        if (! $this->option('force') && ! $this->confirm('Proceed?')) {
            return self::FAILURE;
        }

        foreach ($steps as $name => $class) {
            $this->components->task($name, function () use ($class, $name): void {
                $stats = app($class)->handle();

                $this->newLine();
                $this->line("  {$name}: ".collect($stats)->map(fn ($v, $k) => "{$k} {$v}")->implode(', '));
            });
        }

        return self::SUCCESS;
    }
}
