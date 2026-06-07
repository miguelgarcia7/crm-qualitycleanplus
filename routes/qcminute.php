<?php

use Illuminate\Support\Facades\Route;

/*
| QC Minute — qcpstaffing.com "/" — React/Inertia (`minute` views).
| Mounted with [auth, allowed_on_qcminute] in routes/web.php.
| Tablet `/device/*` (Sanctum) is added with the device flow later.
| Closure-free so routes stay cacheable.
*/

Route::inertia('/', 'minute/dashboard/index')->name('qcminute.dashboard');
