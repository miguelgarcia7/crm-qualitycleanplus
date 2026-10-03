<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the demo environment out of search results. Laravel Cloud only adds
 * this header on *.laravel.cloud hosts, and the demo's QC Minute surface runs
 * on a custom subdomain — which would otherwise index a copy of the site.
 */
class NoIndexInDemoMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (config('demo.enabled')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
