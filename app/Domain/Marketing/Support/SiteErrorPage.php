<?php

namespace App\Domain\Marketing\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Error pages for the public marketing site — the site's own layout, in the
 * visitor's language, instead of Laravel's bare pages (marketing-site-audit.md).
 * Wired as an exception `respond` hook in bootstrap/app.php, so it sees the
 * finished error response and only swaps the ones that are a visitor's page.
 *
 * The back office, QC Minute and the sign-in screens keep their own pages
 * (the 403 there carries a sign-out button). An unknown URL never reaches the
 * marketing route group, so the language and the site's Vite bundle are set
 * here rather than by SetSiteLocale / UseMarketingVite.
 */
final class SiteErrorPage
{
    /** @var list<int> */
    private const STATUSES = [403, 404, 419, 429, 500, 503];

    /** Paths on the main domain that belong to the back office or sign-in, not the site. */
    private const APP_PATHS = [
        'admin', 'admin/*', 'login', 'logout', 'register', 'forgot-password', 'reset-password', 'reset-password/*',
        'two-factor-challenge', 'user/*', 'email/*', 'broadcasting/*', 'sanctum/*', 'up',
    ];

    public static function respond(Response $response, Throwable $e, Request $request): Response
    {
        $status = $response->getStatusCode();

        if (! in_array($status, self::STATUSES, true) || ! self::isSitePage($request)) {
            return $response;
        }

        // Keep the stack-trace page while debugging.
        if ($status === 500 && config('app.debug')) {
            return $response;
        }

        $locale = $request->is('es', 'es/*') ? 'es' : 'en';
        App::setLocale($locale);
        Carbon::setLocale($locale);

        // A form sent after its session expired: back to the form with the
        // answers kept, instead of a dead end that loses a long application.
        if ($status === 419 && $request->isMethod('POST') && $request->hasSession()) {
            return redirect()->back(fallback: (new SiteLocale)->route('home'))
                ->withInput($request->except(['_token', 'g-recaptcha-response']))
                ->withErrors(['form' => __('site/errors.expired_form')]);
        }

        Vite::useBuildDirectory('site-build')->useHotFile(public_path('site.hot'));

        $page = response()->view('site.pages.error', ['status' => $status], $status);

        // Keep what clients act on, e.g. how long to wait after a 429.
        foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining'] as $header) {
            if ($response->headers->has($header)) {
                $page->headers->set($header, $response->headers->get($header));
            }
        }

        return $page;
    }

    private static function isSitePage(Request $request): bool
    {
        return $request->getHost() === config('domains.main')
            && ! $request->is(...self::APP_PATHS)
            && ! $request->expectsJson()
            && ! $request->header('X-Inertia');
    }
}
