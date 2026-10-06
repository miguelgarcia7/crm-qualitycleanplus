<?php

use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\Recruiting\Models\JobPosting;

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

it('requires a phone number on business inquiries but not from job seekers', function () {
    $lead = ['contact_first_name' => 'Dana', 'contact_last_name' => 'Okafor', 'contact_email' => 'dana@hotelgroup.com'];

    $this->post(main('/contact-us/business-inquiries'), $lead)->assertSessionHasErrors('contact_phone');
    $this->post(main('/es/contactenos/consultas-para-negocios'), $lead)->assertSessionHasErrors('contact_phone');
    $this->post(main('/contact-us/job-seekers'), $lead)->assertSessionHasNoErrors();

    expect(ContactInquiry::query()->pluck('type')->all())->toBe(['job_seeker']);
});

it('posts the application position once, prefilled from the posting', function () {
    $posting = JobPosting::factory()->published()->create(['title' => 'Night Auditor']);

    $html = $this->get(main('/application/'.$posting->slug))->assertOk()->getContent();

    expect(substr_count($html, 'name="position"'))->toBe(1)
        ->and($html)->toContain('value="Night Auditor"')
        ->not->toContain('name="application_date"')
        ->not->toContain('name="status"');
});

it('shows a static hero image instead of the legacy video', function () {
    $this->get(main('/'))->assertOk()
        ->assertSee('/images/home-hero-dallas.jpg', false)
        ->assertDontSee('<video', false);
});

it('shows the privacy policy and terms, linked from the footer and the forms', function () {
    $this->get(main('/privacy-policy'))->assertOk()
        ->assertSee('<h1 class="ui_animate">Privacy Policy</h1>', false)
        ->assertSee('We do not sell your personal information')
        ->assertSee('href="/terms-of-use"', false);

    $this->get(main('/terms-of-use'))->assertOk()
        ->assertSee('<h1 class="ui_animate">Terms of Use</h1>', false)
        ->assertSee('Dallas County, Texas');

    $this->get(main('/'))->assertSee('<a href="/privacy-policy">Privacy Policy</a> &middot; <a href="/terms-of-use">Terms of Use</a>', false);

    foreach (['/application', '/contact-us/job-seekers', '/contact-us/business-inquiries'] as $form) {
        $this->get(main($form))->assertSee('as described in our <a href="/privacy-policy">Privacy Policy</a>', false);
    }
    $this->get(main('/es/solicitud'))->assertSee('<a href="/es/politica-de-privacidad">Política de Privacidad</a>', false);
});
