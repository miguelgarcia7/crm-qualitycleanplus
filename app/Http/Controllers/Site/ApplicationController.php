<?php

namespace App\Http\Controllers\Site;

use App\Domain\Marketing\Support\SiteLocale;
use App\Domain\Recruiting\Actions\SubmitApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreApplicationRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Public employment application (Phase 08b-i). The form may be opened blank
 * (/application) or pre-filled for a specific posting (/application/{posting});
 * submitting creates a Person(applicant) + JobApplication via {@see SubmitApplication}.
 */
class ApplicationController extends Controller
{
    public function index(): View
    {
        return view('site.pages.application', ['job' => null]);
    }

    public function apply(JobPosting $posting): View
    {
        abort_unless($posting->status->isPublic(), 404);

        return view('site.pages.application', ['job' => $posting]);
    }

    public function store(StoreApplicationRequest $request, SubmitApplication $action): RedirectResponse
    {
        // Only a published posting is linked. One closed while the applicant was
        // filling the form is dropped rather than rejected — the application still
        // lands, carrying the position they typed.
        $posting = $request->filled('job_id')
            ? JobPosting::query()->published()->find($request->integer('job_id'))
            : null;

        $action->handle($request->validated(), $posting);

        return redirect()->to((new SiteLocale)->route('application.thank-you'));
    }

    public function thankYou(): View
    {
        return view('site.pages.application_thankyou');
    }
}
