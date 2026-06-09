<?php

use App\Http\Controllers\Device\DeviceClockController;
use Illuminate\Support\Facades\Route;

/*
| Front-desk tablet kiosk — qcpstaffing.com/device/* (Phase 07c, ADR-0017).
| The kiosk page + activation are public; clock APIs use the device's Sanctum
| token (auth:device). Stateless Bearer APIs — device/* is CSRF-excepted in
| bootstrap/app.php. Closure-free (group closures are build-time) so it caches.
*/

Route::inertia('/', 'device/index')->name('device.kiosk');
Route::post('activate', [DeviceClockController::class, 'activate'])->name('device.activate');

Route::middleware('auth:device')->group(function () {
    Route::get('context', [DeviceClockController::class, 'context'])->name('device.context');
    Route::post('lookup', [DeviceClockController::class, 'lookup'])->name('device.lookup');
    Route::post('clock-in', [DeviceClockController::class, 'clockIn'])->name('device.clock-in');
    Route::post('clock-out', [DeviceClockController::class, 'clockOut'])->name('device.clock-out');
});
