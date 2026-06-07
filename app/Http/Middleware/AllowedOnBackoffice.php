<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the back office (qualitycleanplus.com/admin) to staff roles.
 * See 10-architecture/domain-routing.md and the permissions matrix.
 */
class AllowedOnBackoffice
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->hasAnyRole([
                'super_admin', 'admin', 'office_manager', 'front_desk',
                'hr', 'payroll', 'recruiter', 'w2_employee',
            ]),
            403,
            'This account cannot access the back office.',
        );

        return $next($request);
    }
}
