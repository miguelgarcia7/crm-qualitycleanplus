<?php

use App\Http\Controllers\Minute\ChangePersonalInfoController;
use App\Http\Controllers\Minute\MoreStaffController;
use App\Http\Controllers\Minute\PayIncreaseController;
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

// Property-manager pay increase requests (Phase 04b, ADR-0020)
Route::get('pay-increases', [PayIncreaseController::class, 'index'])->name('qcminute.pay-increases.index');
Route::post('pay-increases', [PayIncreaseController::class, 'store'])->name('qcminute.pay-increases.store');

// Property-manager staffing requests (Phase 04b-iii, ADR-0021)
Route::get('staffing-requests', [MoreStaffController::class, 'index'])->name('qcminute.more-staff.index');
Route::post('staffing-requests', [MoreStaffController::class, 'store'])->name('qcminute.more-staff.store');
Route::post('staffing-requests/{moreStaffRequest}/cancel', [MoreStaffController::class, 'cancel'])->name('qcminute.more-staff.cancel');

// Self-service personal-info change requests (Phase 04b-iii)
Route::get('my-info', [ChangePersonalInfoController::class, 'index'])->name('qcminute.info-changes.index');
Route::post('my-info', [ChangePersonalInfoController::class, 'store'])->name('qcminute.info-changes.store');
