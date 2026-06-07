<?php

use Illuminate\Support\Facades\Route;

/*
| Marketing surface — qualitycleanplus.com "/" — public, server-rendered Blade
| for SEO (ADR-0023). Its own `site` asset bundle comes when marketing is built.
| Closure-free so routes stay cacheable (Herd route-caches in dev).
*/

Route::view('/', 'site.welcome')->name('marketing.home');
