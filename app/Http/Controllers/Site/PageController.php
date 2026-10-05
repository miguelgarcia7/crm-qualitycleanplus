<?php

namespace App\Http\Controllers\Site;

use App\Domain\Marketing\Models\Testimonial;
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
        // Newest first, like the legacy site; the view hides the section when empty.
        return view('site.pages.home', [
            'testimonials' => Testimonial::query()->active()->latest()->latest('id')->get(),
        ]);
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
