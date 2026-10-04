<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * After logout, go to the sign-in page of the surface the user was on. Fortify's
 * default is "/", which on the main domain is the marketing site — a plain
 * Blade page, so the Inertia logout request rendered it in a pop-up modal
 * instead of navigating. "/login" is an Inertia page on both domains.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        return redirect('/login');
    }
}
