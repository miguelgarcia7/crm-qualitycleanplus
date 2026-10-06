<?php

use App\Http\Controllers\Site\ApplicationController;
use App\Http\Controllers\Site\ContactController;
use App\Http\Controllers\Site\JobBoardController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\SeoController;
use App\Http\Controllers\Site\TestimonialPhotoController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

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
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('marketing.privacy');
Route::get('/terms-of-use', [PageController::class, 'terms'])->name('marketing.terms');

// Public job board (published postings).
Route::get('/job-openings', [JobBoardController::class, 'index'])->name('marketing.job-openings');

// Employment application. Declare the static + thank-you routes before the
// {posting} wildcard so "thank-you" isn't captured as a posting slug.
Route::get('/application', [ApplicationController::class, 'index'])->name('marketing.application');
Route::post('/application', [ApplicationController::class, 'store'])->middleware('throttle:marketing-forms')->name('marketing.application.store');
Route::get('/application/thank-you', [ApplicationController::class, 'thankYou'])->name('marketing.application.thank-you');
// Legacy linked postings by numeric id (old ids don't map to postings here).
Route::permanentRedirect('/application/{legacyId}', '/application')->whereNumber('legacyId');
Route::get('/application/{posting}', [ApplicationController::class, 'apply'])->name('marketing.application.apply');

// Contact forms — Job Seekers + Business inquiries (GET form, POST store).
Route::get('/contact-us/job-seekers', [ContactController::class, 'jobSeekers'])->name('marketing.contact.job-seekers');
Route::post('/contact-us/job-seekers', [ContactController::class, 'storeJobSeeker'])->middleware('throttle:marketing-forms')->name('marketing.contact.job-seekers.store');
Route::get('/contact-us/business-inquiries', [ContactController::class, 'businessInquiries'])->name('marketing.contact.business');
Route::post('/contact-us/business-inquiries', [ContactController::class, 'storeBusinessInquiry'])->middleware('throttle:marketing-forms')->name('marketing.contact.business.store');
Route::get('/testimonials/{testimonial}/photo', TestimonialPhotoController::class)->name('marketing.testimonials.photo');

/*
| Spanish (marketing-site-audit.md D1) — the same pages under `marketing.es.*`,
| keeping the legacy site's Spanish slugs so existing links and rankings carry
| over. SetSiteLocale switches to Spanish for anything under /es; views link
| through SiteLocale, which picks the route for the current language.
*/
Route::get('/es', [PageController::class, 'home'])->name('marketing.es.home');
Route::get('/es/servicios', [PageController::class, 'services'])->name('marketing.es.services');
Route::get('/es/quienes_somos', [PageController::class, 'aboutUs'])->name('marketing.es.about');
Route::get('/es/contactenos', [PageController::class, 'contactUs'])->name('marketing.es.contact');
Route::get('/es/politica-de-privacidad', [PageController::class, 'privacy'])->name('marketing.es.privacy');
Route::get('/es/terminos-de-uso', [PageController::class, 'terms'])->name('marketing.es.terms');
Route::get('/es/ofertas-de-trabajo', [JobBoardController::class, 'index'])->name('marketing.es.job-openings');
Route::get('/es/solicitud', [ApplicationController::class, 'index'])->name('marketing.es.application');
Route::post('/es/solicitud', [ApplicationController::class, 'store'])->middleware('throttle:marketing-forms')->name('marketing.es.application.store');
Route::get('/es/solicitud/gracias', [ApplicationController::class, 'thankYou'])->name('marketing.es.application.thank-you');
Route::permanentRedirect('/es/solicitud/{legacyId}', '/es/solicitud')->whereNumber('legacyId');
Route::get('/es/solicitud/{posting}', [ApplicationController::class, 'apply'])->name('marketing.es.application.apply');
Route::get('/es/contactenos/solicitantes-de-empleo', [ContactController::class, 'jobSeekers'])->name('marketing.es.contact.job-seekers');
Route::post('/es/contactenos/solicitantes-de-empleo', [ContactController::class, 'storeJobSeeker'])->middleware('throttle:marketing-forms')->name('marketing.es.contact.job-seekers.store');
Route::get('/es/contactenos/consultas-para-negocios', [ContactController::class, 'businessInquiries'])->name('marketing.es.contact.business');
Route::post('/es/contactenos/consultas-para-negocios', [ContactController::class, 'storeBusinessInquiry'])->middleware('throttle:marketing-forms')->name('marketing.es.contact.business.store');

/*
| SEO (marketing-site-audit.md) — generated so they follow the environment
| (Seo::indexable): production invites crawlers, everything else stays out.
*/
// No session or cookies: crawlers don't need one, and a Set-Cookie would stop
// caches from reusing these responses.
Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->group(function (): void {
    Route::get('/robots.txt', [SeoController::class, 'robots'])->name('marketing.robots');
    Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('marketing.sitemap');
});

// Legacy pages that no longer exist here.
Route::permanentRedirect('/partners/baseball', '/');
