<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points the marketing surface (qualitycleanplus.com "/", ADR-0023) at its own
 * Vite bundle — build dir `public/site-build` (a sibling of `public/build`, so
 * neither build wipes the other's manifest) + hot file `public/site.hot` —
 * fully decoupled from the admin app's bundle. Applied to the marketing route
 * group; admin requests keep the default `public/build` + `public/hot`.
 */
class UseMarketingVite
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useBuildDirectory('site-build')
            ->useHotFile(public_path('site.hot'));

        return $next($request);
    }
}
