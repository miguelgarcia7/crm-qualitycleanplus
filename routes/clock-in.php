<?php

use App\Http\Controllers\Public\QrClockInController;
use Illuminate\Support\Facades\Route;

/*
| Public contractor QR clock-in — qcpstaffing.com/clock-in/{property} (Phase 07a).
| No auth: phone + GPS + selfie are the credential (ADR-0017). Mounted with a
| throttle in routes/web.php. Closure-free so the route table stays cacheable.
*/

Route::get('{property}', [QrClockInController::class, 'show'])->name('clock-in.show');
Route::post('{property}/lookup', [QrClockInController::class, 'lookup'])->name('clock-in.lookup');
Route::post('{property}/in', [QrClockInController::class, 'clockIn'])->name('clock-in.in');
Route::post('{property}/out', [QrClockInController::class, 'clockOut'])->name('clock-in.out');
