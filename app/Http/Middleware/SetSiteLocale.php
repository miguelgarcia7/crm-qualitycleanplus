<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marketing pages under `/es` render in Spanish, everything else in English
 * (marketing-site-audit.md D1). Language follows the URL, not a cookie or the
 * browser, so every URL has exactly one language — which is what search
 * engines and the hreflang tags expect.
 */
class SetSiteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->is('es', 'es/*') ? 'es' : 'en';

        App::setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
