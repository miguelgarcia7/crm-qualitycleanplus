<?php

use App\Console\Commands\ContractExpirationCheck;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Property Bible — daily contract expiration alerts (30/14 day).
Schedule::command(ContractExpirationCheck::class)->dailyAt('07:00');
