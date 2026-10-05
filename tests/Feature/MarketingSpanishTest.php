<?php

use App\Domain\Marketing\Models\ContactInquiry;
use App\Domain\Recruiting\Models\JobApplication;
use App\Domain\Recruiting\Models\JobPosting;
use App\Notifications\ContactInquiryReceived;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/** English path => its Spanish counterpart (legacy slugs kept). */
function spanishPages(): array
{
    return [
        'home' => ['/', '/es'],
        'services' => ['/services', '/es/servicios'],
        'about' => ['/about-us', '/es/quienes_somos'],
        'contact' => ['/contact-us', '/es/contactenos'],
        'job openings' => ['/job-openings', '/es/ofertas-de-trabajo'],
        'application' => ['/application', '/es/solicitud'],
        'thank you' => ['/application/thank-you', '/es/solicitud/gracias'],
        'job seekers' => ['/contact-us/job-seekers', '/es/contactenos/solicitantes-de-empleo'],
        'business' => ['/contact-us/business-inquiries', '/es/contactenos/consultas-para-negocios'],
    ];
}

it('serves every page in both languages, each marked with its own', function (string $en, string $es) {
    $this->get(main($en))->assertOk()->assertSee('<html class="no-js" lang="en">', false);
    $this->get(main($es))->assertOk()->assertSee('<html class="no-js" lang="es">', false);
})->with(spanishPages());

it('links each page to its other-language version for search engines and visitors', function (string $en, string $es) {
    $enUrl = main($en === '/' ? '' : $en);
    $esUrl = main($es);

    $this->get(main($es))
        ->assertSee('<link rel="canonical" href="'.$esUrl.'" />', false)
        ->assertSee('hreflang="en" href="'.$enUrl.'"', false)
        ->assertSee('hreflang="es" href="'.$esUrl.'"', false)
        ->assertSee('hreflang="x-default" href="'.$enUrl.'"', false)
        // The switcher goes to the same page in English, not the home page.
        ->assertSee('<a href="'.$enUrl.'" hreflang="en" lang="en">English</a>', false);

    $this->get(main($en))->assertSee('<a href="'.$esUrl.'" hreflang="es" lang="es">Español</a>', false);
})->with(spanishPages());

it('keeps a Spanish visitor on Spanish pages', function () {
    $this->get(main('/es'))
        ->assertSee('href="/es/servicios"', false)
        ->assertSee('href="/es/solicitud"', false)
        ->assertDontSee('href="/services"', false);
});

it('links Spanish job postings to the Spanish application', function () {
    $posting = JobPosting::factory()->published()->create();

    $this->get(main('/es/ofertas-de-trabajo'))->assertSee('href="/es/solicitud/'.$posting->slug.'"', false);
    $this->get(main('/es/solicitud/'.$posting->slug))->assertOk()->assertSee($posting->title);
});

it('answers a Spanish form in Spanish', function () {
    $this->from(main('/es/contactenos/solicitantes-de-empleo'))
        ->post(main('/es/contactenos/solicitantes-de-empleo'), ['contact_last_name' => 'Rivera', 'contact_email' => 'jamie@example.com'])
        ->assertRedirect(main('/es/contactenos/solicitantes-de-empleo'))
        ->assertSessionHasErrors(['contact_first_name' => 'El campo nombre es obligatorio.']);

    $this->from(main('/es/contactenos/solicitantes-de-empleo'))
        ->post(main('/es/contactenos/solicitantes-de-empleo'), ['contact_first_name' => 'Jamie', 'contact_last_name' => 'Rivera', 'contact_email' => 'jamie@example.com'])
        ->assertSessionHas('message', '¡Gracias! Su información se envió correctamente.');
});

it('sends a Spanish applicant to the Spanish thank-you page', function () {
    $this->post(main('/es/solicitud'), applicationPayload())->assertRedirect(main('/es/solicitud/gracias'));

    expect(JobApplication::query()->count())->toBe(1);
});

it('emails staff a Spanish-site lead in English, flagged as Spanish', function () {
    Notification::fake();
    config(['qcp.marketing.contact_recipients.business' => ['sales@example.com']]);

    $this->post(main('/es/contactenos/consultas-para-negocios'), [
        'contact_first_name' => 'Dana', 'contact_last_name' => 'Cole', 'contact_email' => 'dana@hotel.example',
    ])->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(ContactInquiryReceived::class, function (ContactInquiryReceived $n): bool {
        $mail = $n->toMail(new AnonymousNotifiable);

        return $n->locale === 'en'
            && str_starts_with((string) $mail->subject, 'Business inquiry from Dana Cole')
            && in_array('**Language:** Spanish — sent from the Spanish site.', $mail->introLines, true);
    });
    expect(ContactInquiry::query()->value('type'))->toBe('business');
});

it('does not flag an English lead as Spanish', function () {
    Notification::fake();
    config(['qcp.marketing.contact_recipients.job_seeker' => ['jobs@example.com']]);

    $this->post(main('/contact-us/job-seekers'), ['contact_first_name' => 'Jamie', 'contact_last_name' => 'Rivera', 'contact_email' => 'jamie@example.com']);

    Notification::assertSentOnDemand(ContactInquiryReceived::class, fn (ContactInquiryReceived $n): bool => collect($n->toMail(new AnonymousNotifiable)->introLines)
        ->doesntContain(fn (string $l): bool => str_contains($l, 'Spanish')));
});
