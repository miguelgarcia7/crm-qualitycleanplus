<?php

use App\Http\Controllers\Public\QrClockInController;
use Illuminate\Support\Facades\Route;

/*
| Public contractor QR clock-in — qcpstaffing.com/clock-in/{token} (Phase 07a).
| No auth: phone + GPS + selfie are the credential (ADR-0017). The token is the
| property's unguessable qr_token (minted when QR clock-in is enabled) — an
| unknown token and a disabled property 404 identically so the URL space can't
| be probed. Mounted with a throttle in routes/web.php. Closure-free so the
| route table stays cacheable.
*/

Route::get('{token}', [QrClockInController::class, 'show'])->name('clock-in.show');
Route::post('{token}/lookup', [QrClockInController::class, 'lookup'])->name('clock-in.lookup');
Route::post('{token}/in', [QrClockInController::class, 'clockIn'])->name('clock-in.in');
Route::post('{token}/out', [QrClockInController::class, 'clockOut'])->name('clock-in.out');
