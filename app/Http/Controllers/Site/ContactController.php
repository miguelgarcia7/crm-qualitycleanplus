<?php

namespace App\Http\Controllers\Site;

use App\Domain\Marketing\Models\ContactInquiry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\StoreContactInquiryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Public marketing contact forms (Phase 08b-i): "Job Seekers" general inquiries
 * and "Business" staffing/services inquiries. Submissions are stored as
 * {@see ContactInquiry} rows.
 */
class ContactController extends Controller
{
    public function jobSeekers(): View
    {
        return view('site.pages.contact_us_employees');
    }

    public function businessInquiries(): View
    {
        return view('site.pages.contact_us_business');
    }

    public function storeJobSeeker(StoreContactInquiryRequest $request): RedirectResponse
    {
        $this->record($request, 'job_seeker');

        return back()->with('message', 'Thank you! Your information has been submitted successfully.');
    }

    public function storeBusinessInquiry(StoreContactInquiryRequest $request): RedirectResponse
    {
        $this->record($request, 'business');

        return back()->with('message', 'Thank you! Your information has been submitted successfully.');
    }

    private function record(StoreContactInquiryRequest $request, string $type): void
    {
        $data = $request->validated();

        ContactInquiry::create([
            'type' => $type,
            'first_name' => $data['contact_first_name'],
            'last_name' => $data['contact_last_name'],
            'email' => $data['contact_email'],
            'phone' => $data['contact_phone'] ?? null,
            'company' => $data['contact_company'] ?? null,
            'address' => $data['contact_address'] ?? null,
            'city' => $data['contact_city'] ?? null,
            'state' => $data['contact_state'] ?? null,
            'zip' => $data['contact_zip'] ?? null,
            'inquiry_type' => $data['contact_inquiry_type'] ?? null,
            'call_back_time' => $data['contact_call_back_time'] ?? null,
            'message' => $data['contact_message'] ?? null,
        ]);
    }
}
