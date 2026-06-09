<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Points the marketing surface (qualitycleanplus.com "/", ADR-0023) at its own
 * Vite bundle — build dir `public/build/site` + hot file `public/site.hot` —
 * so it is fully decoupled from the admin app's bundle/manifest. Applied to the
 * marketing route group; admin requests keep the default `public/build` + `public/hot`.
 */
class UseMarketingVite
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useBuildDirectory('build/site')
            ->useHotFile(public_path('site.hot'));

        return $next($request);
    }
}
