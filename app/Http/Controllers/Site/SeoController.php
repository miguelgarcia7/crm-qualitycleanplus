<?php

namespace App\Http\Controllers\Site;

use App\Domain\Marketing\Support\Seo;
use App\Domain\Marketing\Support\SiteLocale;
use App\Domain\Recruiting\Models\JobPosting;
use App\Http\Controllers\Controller;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * robots.txt and sitemap.xml for the marketing site. Generated rather than
 * static so they follow the environment ({@see Seo::indexable()}) and the
 * host they're served on.
 */
class SeoController extends Controller
{
    /** Pages listed in the sitemap — the English route names; each has a Spanish twin. */
    private const PAGES = [
        'home', 'services', 'about', 'contact', 'contact.job-seekers', 'contact.business', 'job-openings', 'application',
    ];

    public function robots(Request $request): Response
    {
        if (! Seo::indexable()) {
            return $this->disallowAll();
        }

        $lines = [
            'User-agent: *',
            // The back office and the sign-in screens share this domain.
            'Disallow: /admin',
            'Disallow: /login',
            'Disallow: /forgot-password',
            'Disallow: /reset-password',
            'Disallow: /two-factor-challenge',
            '',
            'Sitemap: '.$request->getSchemeAndHttpHost().'/sitemap.xml',
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** QC Minute's domain is an app for property managers and contractors, not a public site. */
    public function disallowAll(): Response
    {
        return response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(SiteLocale $site): Response
    {
        abort_unless(Seo::indexable(), 404);

        /** @var list<array{page: string, parameters: list<JobPosting>, updated: CarbonInterface|null}> $entries */
        $entries = array_map(fn (string $page): array => ['page' => $page, 'parameters' => [], 'updated' => null], self::PAGES);

        // Each open posting's application page, so postings can surface in search.
        foreach (JobPosting::query()->published()->latest()->get() as $posting) {
            $entries[] = ['page' => 'application.apply', 'parameters' => [$posting], 'updated' => $posting->updated_at];
        }

        return response()
            ->view('site.sitemap', ['entries' => $entries, 'site' => $site, 'locales' => array_keys(SiteLocale::LOCALES)])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** www.qualitycleanplus.com — where legacy links and the search index point — redirects to the site. */
    public function toApex(Request $request): RedirectResponse
    {
        $target = $request->getScheme().'://'.config('domains.main').$request->getRequestUri();

        return redirect()->away($target, 301);
    }
}
