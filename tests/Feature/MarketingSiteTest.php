<?php

use App\Domain\Marketing\Models\ContactInquiry;

it('renders the public marketing pages', function (string $path) {
    $this->get(main($path))->assertOk();
})->with([
    'home' => '/',
    'services' => '/services',
    'about' => '/about-us',
    'contact' => '/contact-us',
    'job seekers' => '/contact-us/job-seekers',
    'business inquiries' => '/contact-us/business-inquiries',
]);

it('shows the company name on the home page', function () {
    $this->get(main('/'))->assertOk()->assertSee('Quality Cleaning Plus', false);
});

it('records a job-seeker contact inquiry', function () {
    $this->post(main('/contact-us/job-seekers'), [
        'contact_first_name' => 'Jamie',
        'contact_last_name' => 'Rivera',
        'contact_email' => 'jamie@example.com',
        'contact_phone' => '214-555-0100',
        'contact_call_back_time' => 'Mornings',
        'contact_message' => 'Looking for housekeeping work.',
    ])->assertRedirect();

    $inquiry = ContactInquiry::query()->firstOrFail();
    expect($inquiry->type)->toBe('job_seeker')
        ->and($inquiry->first_name)->toBe('Jamie')
        ->and($inquiry->call_back_time)->toBe('Mornings');
});

it('records a business contact inquiry', function () {
    $this->post(main('/contact-us/business-inquiries'), [
        'contact_first_name' => 'Dana',
        'contact_last_name' => 'Okafor',
        'contact_email' => 'dana@hotelgroup.com',
        'contact_phone' => '214-555-0199',
        'contact_company' => 'Hotel Group LLC',
        'contact_inquiry_type' => 'Looking to Hire for Team',
        'contact_message' => 'Need 5 housekeepers.',
    ])->assertRedirect();

    $inquiry = ContactInquiry::query()->firstOrFail();
    expect($inquiry->type)->toBe('business')
        ->and($inquiry->company)->toBe('Hotel Group LLC');
});

it('rejects a contact inquiry missing required fields', function () {
    $this->post(main('/contact-us/job-seekers'), [
        'contact_first_name' => 'NoEmail',
    ])->assertSessionHasErrors(['contact_last_name', 'contact_email']);

    expect(ContactInquiry::query()->count())->toBe(0);
});
