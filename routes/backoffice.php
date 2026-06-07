<?php

use App\Http\Controllers\ContractController;
use App\Http\Controllers\PropertyAssignmentController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\PropertyDepartmentController;
use App\Http\Controllers\PropertyPositionRateController;
use App\Http\Controllers\Settings\ProfileController;
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

// Settings
Route::redirect('/settings', '/admin/settings/profile');
Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
