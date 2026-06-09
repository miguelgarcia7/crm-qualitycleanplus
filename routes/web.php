<?php

use App\Http\Middleware\AllowedOnBackoffice;
use App\Http\Middleware\AllowedOnQcMinute;
use Illuminate\Support\Facades\Route;

/*
| Surface routes (ADR-0024). Two domains, three surfaces — config-driven so
| local Herd `.test` hosts and production `.com` hosts both resolve. Loaded
| under the `web` middleware group via bootstrap/app.php. All route files are
| closure-free (Route::view / Route::inertia / controllers) so they cache.
*/

// DOMAIN 1 — qualitycleanplus.com : marketing ("/") + back office ("/admin")
Route::domain(config('domains.main'))
    ->group(base_path('routes/marketing.php'));

Route::domain(config('domains.main'))
    ->prefix('admin')
    ->middleware(['auth', AllowedOnBackoffice::class])
    ->group(base_path('routes/backoffice.php'));

// DOMAIN 2 — qcpstaffing.com : public contractor QR clock-in ("/clock-in/*"),
// then QC Minute ("/")  (+ /device/* later). The clock-in surface is public
// (phone + GPS + selfie are the credential, ADR-0017) and throttled.
Route::domain(config('domains.qcminute'))
    ->prefix('clock-in')
    ->middleware('throttle:30,1')
    ->group(base_path('routes/clock-in.php'));

// Front-desk tablet kiosk (Phase 07c) — paired via Sanctum device token; throttled.
Route::domain(config('domains.qcminute'))
    ->prefix('device')
    ->middleware('throttle:60,1')
    ->group(base_path('routes/device.php'));

Route::domain(config('domains.qcminute'))
    ->middleware(['auth', AllowedOnQcMinute::class])
    ->group(base_path('routes/qcminute.php'));
