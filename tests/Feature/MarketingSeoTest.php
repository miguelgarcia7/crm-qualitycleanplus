<?php

use App\Domain\Recruiting\Models\JobPosting;
use Illuminate\Support\Facades\DB;

/** Act as the real public site (production, not demo). */
function asProductionSite(): void
{
    app()->instance('env', 'production');
    config(['demo.enabled' => false]);
}

it('invites crawlers to the public site, minus the back office, and points them at the sitemap', function () {
    asProductionSite();

    $this->get(main('/robots.txt'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /login')
        ->assertSee('Sitemap: '.main('/sitemap.xml'))
        ->assertDontSee("Disallow: /\n", false);
});

it('keeps crawlers out of anything that is not the public site', function (Closure $setup) {
    $setup();

    $this->get(main('/robots.txt'))->assertOk()->assertSee("Disallow: /\n", false);
    $this->get(main('/sitemap.xml'))->assertNotFound();
})->with([
    'local / testing' => fn () => fn () => null,
    'demo in production' => fn () => function () {
        asProductionSite();
        config(['demo.enabled' => true]);
    },
]);

it('keeps crawlers out of QC Minute entirely', function () {
    asProductionSite();

    $this->get(qcminute('/robots.txt'))->assertOk()->assertSee("Disallow: /\n", false);
});

it('lists every page in both languages, linked to each other, plus open postings', function () {
    asProductionSite();
    $open = JobPosting::factory()->published()->create();
    $draft = JobPosting::factory()->create();

    $xml = $this->get(main('/sitemap.xml'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->getContent();

    expect(simplexml_load_string($xml))->not->toBeFalse()
        ->and($xml)->toContain('<loc>'.main('/services').'</loc>')
        ->toContain('<loc>'.main('/es/servicios').'</loc>')
        ->toContain('<loc>'.main('/privacy-policy').'</loc>')
        ->toContain('<loc>'.main('/es/terminos-de-uso').'</loc>')
        ->toContain('hreflang="es" href="'.main('/es/quienes_somos').'"')
        ->toContain('hreflang="x-default" href="'.main('/about-us').'"')
        ->toContain('<loc>'.main('/application/'.$open->slug).'</loc>')
        ->toContain('<loc>'.main('/es/solicitud/'.$open->slug).'</loc>')
        ->not->toContain($draft->slug)
        // Thank-you pages are noindex and stay out.
        ->not->toContain('thank-you')
        ->not->toContain('gracias');
});

it('sends the www host to the site, keeping the path and query', function () {
    $this->get('http://www.'.config('domains.main').'/es/servicios?utm_source=x')
        ->assertStatus(301)
        ->assertRedirect('http://'.config('domains.main').'/es/servicios?utm_source=x');
});

it('redirects legacy URLs that no longer exist', function (string $from, string $to) {
    $this->get(main($from))->assertStatus(301)->assertRedirect(main($to));
})->with([
    'baseball partner page' => ['/partners/baseball', ''],
    'numeric posting link' => ['/application/12', '/application'],
    'numeric Spanish posting link' => ['/es/solicitud/12', '/es/solicitud'],
]);

it('loads analytics only on the real public site', function () {
    $this->get(main('/'))->assertDontSee('googletagmanager.com', false);

    asProductionSite();
    $this->get(main('/'))->assertSee('googletagmanager.com/gtag/js?id=G-ZQSBDK2ZRN', false);
});

it('describes the business for search results on the home page', function () {
    $html = $this->get(main('/es'))->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $data = json_decode($m[1] ?? '', true);

    expect($data['@type'])->toBe('EmploymentAgency')
        ->and($data['telephone'])->toBe('+1-214-271-5595')
        ->and($data['address']['postalCode'])->toBe('75235')
        ->and($data['url'])->toBe(main('/es'));
});

it('keeps the thank-you page out of the index and shares images from this host', function () {
    $this->get(main('/application/thank-you'))->assertSee('<meta name="robots" content="noindex, follow">', false);
    $this->get(main('/job-openings'))
        ->assertSee('<meta property="og:image" content="'.main('/images/social-media-website-work.png').'" />', false)
        ->assertDontSee('<meta name="robots"', false);
});

it('serves the sitemap from cache until a posting changes', function () {
    asProductionSite();
    $this->get(main('/sitemap.xml'))->assertOk();

    // Written behind the model's back: no event, so the cached copy stands.
    DB::table('job_postings')->insert([
        'status' => 'published', 'title' => 'Silent Posting', 'slug' => 'silent-posting',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->get(main('/sitemap.xml'))->assertDontSee('silent-posting');

    // Any posting change through the app rebuilds it.
    $posting = JobPosting::factory()->published()->create();
    $this->get(main('/sitemap.xml'))->assertSee($posting->slug)->assertSee('silent-posting');

    $posting->update(['status' => 'closed']);
    $this->get(main('/sitemap.xml'))->assertDontSee($posting->slug);
});

it('lets crawlers and caches reuse robots and the sitemap, without a session', function () {
    asProductionSite();

    foreach (['/robots.txt' => 'max-age=86400, public', '/sitemap.xml' => 'max-age=3600, public'] as $path => $cache) {
        $response = $this->get(main($path))->assertOk()->assertHeader('Cache-Control', $cache);
        expect($response->headers->getCookies())->toBe([]);
    }
});
