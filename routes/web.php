<?php

use App\Http\Controllers\Site\SeoController;
use App\Http\Middleware\AllowedOnBackoffice;
use App\Http\Middleware\AllowedOnQcMinute;
use App\Http\Middleware\SetSiteLocale;
use App\Http\Middleware\UseMarketingVite;
use Illuminate\Support\Facades\Route;

/*
| Surface routes (ADR-0024). Two domains, three surfaces — config-driven so
| local Herd `.test` hosts and production `.com` hosts both resolve. Loaded
| under the `web` middleware group via bootstrap/app.php. All route files are
| closure-free (Route::view / Route::inertia / controllers) so they cache.
*/

// DOMAIN 1 — qualitycleanplus.com : marketing ("/") + back office ("/admin")
// Marketing uses its own Vite bundle (public/build/site) via UseMarketingVite,
// and is Spanish under /es (SetSiteLocale).
Route::domain(config('domains.main'))
    ->middleware([UseMarketingVite::class, SetSiteLocale::class])
    ->group(base_path('routes/marketing.php'));

// www.qualitycleanplus.com is where legacy links and the search index point;
// send it to the site, keeping the path (needs the www host attached in Cloud).
Route::domain('www.'.config('domains.main'))->group(function (): void {
    Route::get('/{path?}', [SeoController::class, 'toApex'])->where('path', '.*')->name('marketing.www');
});

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

// QC Minute is an app, not a public site — keep crawlers out entirely.
Route::domain(config('domains.qcminute'))
    ->get('/robots.txt', [SeoController::class, 'disallowAll'])
    ->name('qcminute.robots');

Route::domain(config('domains.qcminute'))
    ->middleware(['auth', AllowedOnQcMinute::class])
    ->group(base_path('routes/qcminute.php'));
