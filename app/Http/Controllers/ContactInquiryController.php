<?php

namespace App\Http\Controllers;

use App\Domain\Marketing\Models\ContactInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Back-office inbox for the marketing site's contact forms (Job Seekers and
 * Business). Every lead is also emailed; this is where they can all be seen,
 * marked handled once someone has followed up, and deleted when they're spam.
 * Gated `marketing.inquiries.manage`.
 */
class ContactInquiryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ContactInquiry::class);

        $inquiries = ContactInquiry::query()
            ->with('handledBy:id,name')
            ->latest()
            ->latest('id')
            ->get()
            ->map(fn (ContactInquiry $i): array => [
                'id' => $i->id,
                'type' => $i->type,
                'name' => $i->name(),
                'email' => $i->email,
                'phone' => $i->phone,
                'company' => $i->company,
                'address' => $i->address,
                'city' => $i->city,
                'state' => $i->state,
                'zip' => $i->zip,
                'inquiry_type' => $i->inquiry_type,
                'call_back_time' => $i->call_back_time,
                'message' => $i->message,
                'spanish' => $i->locale === 'es',
                'spam_check' => $i->spam_check,
                // The lead was let through without a verdict (see RecaptchaAssessment).
                'unverified' => str_starts_with((string) $i->spam_check, 'Not verified'),
                'received_at' => $i->created_at?->toIso8601String(),
                'handled_at' => $i->handled_at?->toIso8601String(),
                'handled_by' => $i->handledBy?->name,
            ]);

        return Inertia::render('admin/inquiries/index', [
            'inquiries' => $inquiries,
            // The lead email links here with ?open={id} to show that inquiry.
            'open' => $request->integer('open') ?: null,
        ]);
    }

    public function update(Request $request, ContactInquiry $inquiry): RedirectResponse
    {
        $this->authorize('update', $inquiry);

        $handled = $request->validate(['handled' => ['required', 'boolean']])['handled'];

        $inquiry->forceFill([
            'handled_at' => $handled ? now() : null,
            'handled_by_id' => $handled ? $request->user()?->id : null,
        ])->save();

        return back()->with('success', $handled ? 'Marked as handled.' : 'Marked as new.');
    }

    public function destroy(ContactInquiry $inquiry): RedirectResponse
    {
        $this->authorize('delete', $inquiry);

        $inquiry->delete();

        return back()->with('success', 'Inquiry deleted.');
    }
}
