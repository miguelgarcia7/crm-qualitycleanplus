<?php

namespace App\Console\Commands;

use App\Domain\Demo\DemoRoster;
use App\Domain\Settings\Models\Setting;
use Database\Seeders\CompanySettingsSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\HolidaySeeder;
use Database\Seeders\InventorySeeder;
use Database\Seeders\PositionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Rebuilds the database as the demo (docs/80-plan/demo-environment.md):
 * migrate:fresh, the structural seeders, then DemoSeeder. Locally the
 * legacy data comes back with bin/legacy-sync; on Cloud this is run from the
 * demo environment's Commands tab, never as a deploy command.
 */
class DemoReset extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe the database and seed the demo companies (DEMO_MODE only, never production)';

    /** Structural seeders only — SampleDataSeeder's companies stay out of the demo. */
    private const SEEDERS = [
        RolePermissionSeeder::class,
        DepartmentSeeder::class,
        PositionSeeder::class,
        HolidaySeeder::class,
        InventorySeeder::class,
        CompanySettingsSeeder::class,
        DemoSeeder::class,
    ];

    public function handle(): int
    {
        // Demo mode too, not just "not production": staging holds the real
        // legacy data and need not run as APP_ENV=production.
        if ($this->laravel->isProduction() || ! config('demo.enabled')) {
            $this->error('demo:reset wipes the database: it only runs with DEMO_MODE=true, and never in production.');

            return self::FAILURE;
        }

        $database = config('database.connections.'.config('database.default').'.database');
        if (! $this->option('force') && ! $this->confirm("This wipes every table in [{$database}] and replaces it with the demo. Continue?")) {
            return self::FAILURE;
        }

        // Settings survive the wipe: the company identity set at
        // /admin/settings/company isn't demo data, and invoices freeze it when
        // they're generated — so it must be back before the history is billed.
        $settings = Schema::hasTable('settings') ? Setting::query()->pluck('value', 'key')->all() : [];

        $this->call('migrate:fresh', ['--force' => true]);
        Setting::put($settings);
        foreach (self::SEEDERS as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $backOffice = config('domains.main').'/admin';
        $qcMinute = (string) config('domains.qcminute');
        $rows = [];
        foreach (DemoRoster::STAFF as $email => [$role]) {
            $rows[] = [$role, $email, implode(', ', DemoRoster::propertiesFor($email)), $role === 'property_manager' ? $qcMinute : $backOffice];
        }
        foreach (DemoRoster::contractors() as $contractor) {
            $rows[] = ['contractor', $contractor['email'], $contractor['property'], $qcMinute];
        }

        $this->newLine();
        $this->info('Demo ready. Every login uses the DEMO_PASSWORD (default: password).');
        $this->table(['Role', 'Email', 'Company', 'Sign in at'], $rows);

        return self::SUCCESS;
    }
}
