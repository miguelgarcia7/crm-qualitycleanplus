<?php

namespace App\Http\Controllers\Site;

use App\Domain\Recruiting\Models\JobPosting;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Public job board (Phase 08b-i) — lists published job postings on the marketing
 * site. Each row links to the application form for that posting.
 */
class JobBoardController extends Controller
{
    public function index(): View
    {
        $positions = JobPosting::query()
            ->published()
            ->with('property')
            ->latest()
            ->get();

        return view('site.pages.job_openings', compact('positions'));
    }
}
