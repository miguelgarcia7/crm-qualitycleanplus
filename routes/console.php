<?php

use App\Console\Commands\ContractExpirationCheck;
use App\Console\Commands\EnsurePayrollPeriods;
use App\Console\Commands\ProcessPtoTenureCrossings;
use App\Domain\Inventory\Jobs\ApplyScheduledContractorCharges;
use App\Domain\WorkOrders\Jobs\ProcessTemporaryAssignmentEnds;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Property Bible — daily contract expiration alerts (30/14 day).
Schedule::command(ContractExpirationCheck::class)->dailyAt('07:00');

// Time tracking — keep payroll periods materialized ahead (ADR-0009).
Schedule::command(EnsurePayrollPeriods::class)->dailyAt('00:15');

// Inventory — apply scheduled contractor charges as each period opens (ADR-0014).
Schedule::job(new ApplyScheduledContractorCharges)->dailyAt('00:30');

// Work orders — close temporary assignments when their window ends (ADR-0019).
Schedule::job(new ProcessTemporaryAssignmentEnds)->dailyAt('00:45');

// PTO — tier crossings + anniversary refreshes for active staff (ADR-0016).
Schedule::command(ProcessPtoTenureCrossings::class)->dailyAt('01:00');
