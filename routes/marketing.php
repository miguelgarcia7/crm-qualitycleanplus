<?php

use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\PageController;
use Illuminate\Support\Facades\Route;

/*
| Marketing surface — qualitycleanplus.com "/" — public, server-rendered Blade
| for SEO (ADR-0023). Its own `site` asset bundle (resources/css/site +
| resources/js/site). Closure-free so routes stay cacheable (Herd route-caches
| in dev). Job board + application form are added in 08b-i increments 2-3.
*/

Route::get('/', [PageController::class, 'home'])->name('marketing.home');
Route::get('/services', [PageController::class, 'services'])->name('marketing.services');
Route::get('/about-us', [PageController::class, 'aboutUs'])->name('marketing.about');
Route::get('/contact-us', [PageController::class, 'contactUs'])->name('marketing.contact');

// Contact forms — Job Seekers + Business inquiries (GET form, POST store).
Route::get('/contact-us/job-seekers', [ContactController::class, 'jobSeekers'])->name('marketing.contact.job-seekers');
Route::post('/contact-us/job-seekers', [ContactController::class, 'storeJobSeeker'])->name('marketing.contact.job-seekers.store');
Route::get('/contact-us/business-inquiries', [ContactController::class, 'businessInquiries'])->name('marketing.contact.business');
Route::post('/contact-us/business-inquiries', [ContactController::class, 'storeBusinessInquiry'])->name('marketing.contact.business.store');
