<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts QC Minute (qcpstaffing.com) to property managers, contractors,
 * and ownership. See 10-architecture/domain-routing.md.
 */
class AllowedOnQcMinute
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->hasAnyRole([
                'property_manager', 'contractor', 'admin', 'super_admin',
            ]),
            403,
            'This account cannot access QC Minute.',
        );

        return $next($request);
    }
}
