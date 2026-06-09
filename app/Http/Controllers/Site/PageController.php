<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Static marketing pages (Phase 08b-i, ADR-0023) — server-rendered Blade on
 * qualitycleanplus.com. No auth.
 */
class PageController extends Controller
{
    public function home(): View
    {
        // Testimonials are not modelled yet (deferred); pass an empty collection
        // so the home view hides the section rather than erroring.
        return view('site.pages.home', ['testimonials' => collect()]);
    }

    public function services(): View
    {
        return view('site.pages.services');
    }

    public function aboutUs(): View
    {
        return view('site.pages.about_us');
    }

    public function contactUs(): View
    {
        return view('site.pages.contact_us');
    }
}
