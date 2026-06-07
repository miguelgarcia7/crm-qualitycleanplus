<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

/**
 * After login, send the user to the dashboard for the surface they logged in
 * on: the main domain → back office (/admin), the qcminute domain → "/".
 * The allowed_on_* middleware then enforces role access ("wrong door" → 403).
 */
class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        return redirect()->intended($this->home($request));
    }

    private function home(Request $request): string
    {
        return $request->getHost() === config('domains.main') ? '/admin' : '/';
    }
}
