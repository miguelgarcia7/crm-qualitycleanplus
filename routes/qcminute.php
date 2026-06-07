<?php

use App\Http\Controllers\Minute\TimesheetApprovalController;
use Illuminate\Support\Facades\Route;

/*
| QC Minute — qcpstaffing.com "/" — React/Inertia (`minute` views).
| Mounted with [auth, allowed_on_qcminute] in routes/web.php.
| Tablet `/device/*` (Sanctum) is added with the device flow later.
| Closure-free so routes stay cacheable.
*/

Route::inertia('/', 'minute/dashboard/index')->name('qcminute.dashboard');

// Property-manager timesheet approval (Phase 03)
Route::get('timesheets', [TimesheetApprovalController::class, 'index'])->name('qcminute.timesheets.index');
Route::get('timesheets/{timesheet}', [TimesheetApprovalController::class, 'show'])->name('qcminute.timesheets.show');
Route::post('timesheets/{timesheet}/approve', [TimesheetApprovalController::class, 'approve'])->name('qcminute.timesheets.approve');
Route::post('timesheets/{timesheet}/decline', [TimesheetApprovalController::class, 'decline'])->name('qcminute.timesheets.decline');
