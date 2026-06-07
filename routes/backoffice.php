<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\PropertyAssignmentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyDepartmentController;
use App\Http\Controllers\PropertyPositionRateController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Route;

/*
| Back office — qualitycleanplus.com/admin — React/Inertia (`admin` bundle).
| Mounted with prefix `admin` + [auth, allowed_on_backoffice] in routes/web.php.
| Closure-free (Route::inertia / Route::redirect / controllers) so cacheable.
*/

Route::redirect('/', '/admin/dashboard');

Route::inertia('/dashboard', 'admin/dashboard/index')->name('backoffice.dashboard');

// Property Bible (Phase 02)
Route::resource('properties', PropertyController::class);

// Bible sub-sections (nested under a property)
Route::post('properties/{property}/departments', [PropertyDepartmentController::class, 'store'])->name('properties.departments.store');
Route::match(['put', 'patch'], 'properties/{property}/departments/{department}', [PropertyDepartmentController::class, 'update'])->name('properties.departments.update');
Route::delete('properties/{property}/departments/{department}', [PropertyDepartmentController::class, 'destroy'])->name('properties.departments.destroy');

Route::post('properties/{property}/rates', [PropertyPositionRateController::class, 'store'])->name('properties.rates.store');
Route::delete('properties/{property}/rates/{rate}', [PropertyPositionRateController::class, 'destroy'])->name('properties.rates.destroy');

Route::post('properties/{property}/contracts', [ContractController::class, 'store'])->name('properties.contracts.store');
Route::get('properties/{property}/contracts/{contract}/download', [ContractController::class, 'download'])->name('properties.contracts.download');
Route::delete('properties/{property}/contracts/{contract}', [ContractController::class, 'destroy'])->name('properties.contracts.destroy');

Route::post('properties/{property}/assignments', [PropertyAssignmentController::class, 'store'])->name('properties.assignments.store');
Route::delete('properties/{property}/assignments/{assignment}', [PropertyAssignmentController::class, 'destroy'])->name('properties.assignments.destroy');

// Work Orders (Phase 03)
Route::get('work-orders/rate-lookup', [WorkOrderController::class, 'rateLookup'])->name('work-orders.rate-lookup');
Route::resource('work-orders', WorkOrderController::class)->only(['index', 'create', 'store', 'edit', 'update']);
Route::post('work-orders/{work_order}/close', [WorkOrderController::class, 'close'])->name('work-orders.close');

// Time tracking — live weekly grid + manual entries (Phase 03)
Route::get('properties/{property}/grid', [TimeEntryController::class, 'grid'])->name('properties.grid');
Route::post('work-orders/{work_order}/time-entries', [TimeEntryController::class, 'store'])->name('time-entries.store');
Route::delete('time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');

// Settings
Route::redirect('/settings', '/admin/settings/profile');
Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
