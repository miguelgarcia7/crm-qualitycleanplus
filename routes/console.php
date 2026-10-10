<?php

use App\Console\Commands\ContractExpirationCheck;
use App\Console\Commands\DemoSimulate;
use App\Console\Commands\EnsurePayrollPeriods;
use App\Console\Commands\ProcessPtoTenureCrossings;
use App\Console\Commands\RefreshReportRollups;
use App\Domain\Adjustments\Jobs\ApplyScheduledContractorCharges;
use App\Domain\WorkOrders\Jobs\ProcessTemporaryAssignmentEnds;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Every scheduled task carries a plain-language ->description(): the System
// reference page lists them for Admin (tests/Feature/SystemReferenceTest.php).

// Property Bible — daily contract expiration alerts (30/14 day).
Schedule::command(ContractExpirationCheck::class)->dailyAt('07:00')
    ->description('Alert Admin and Payroll about contracts ending in 30 or 14 days');

// Time tracking — keep payroll periods materialized ahead (ADR-0009).
Schedule::command(EnsurePayrollPeriods::class)->dailyAt('00:15')
    ->description('Create the upcoming pay weeks');

// Inventory — apply scheduled contractor charges as each period opens (ADR-0014).
Schedule::job(new ApplyScheduledContractorCharges)->dailyAt('00:30')
    ->description('Apply scheduled contractor charges as each pay week opens');

// Work orders — close temporary assignments when their window ends (ADR-0019).
Schedule::job(new ProcessTemporaryAssignmentEnds)->dailyAt('00:45')
    ->description('End temporary assignments whose end date has passed');

// PTO — tier crossings + anniversary refreshes for active staff (ADR-0016).
Schedule::command(ProcessPtoTenureCrossings::class)->dailyAt('01:00')
    ->description('Move staff to their next PTO tier and refresh anniversary balances');

// Reports — nightly rollup backstop over the trailing 90 days (ADR-0028).
Schedule::command(RefreshReportRollups::class)->dailyAt('01:30')
    ->description('Rebuild report totals for the last 90 days');

// Demo environment only — Acme Hotel's simulated punches + billing steps
// (docs/80-plan/demo-environment.md). Weekday working hours, written as one
// cron expression because Laravel Cloud reads schedule:list to decide when
// to wake a sleeping environment.
if (config('demo.enabled')) {
    Schedule::command(DemoSimulate::class)
        ->cron('*/5 6-18 * * 1-5')
        ->timezone('America/Chicago')
        ->withoutOverlapping()
        ->description('Demo: clock contractors in and out and move timesheets and invoices along');
}
