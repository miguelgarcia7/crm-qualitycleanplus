<?php

use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Back office — qualitycleanplus.com/admin — React/Inertia (`admin` bundle).
| Mounted with prefix `admin` + [auth, allowed_on_backoffice] in routes/web.php.
| Closure-free (Route::inertia / Route::redirect / controllers) so cacheable.
*/

Route::redirect('/', '/admin/dashboard');

Route::inertia('/dashboard', 'admin/dashboard/index')->name('backoffice.dashboard');

// Settings
Route::redirect('/settings', '/admin/settings/profile');
Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::patch('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');
