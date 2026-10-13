<?php

namespace App\Http\Controllers\Auth;

use App\Domain\People\Models\Person;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Controllers\NewPasswordController;

/**
 * Fortify's set-a-new-password form, aware of invitations: someone who has
 * never set a password is checked against the `invites` broker, whose links
 * last 3 days; everyone else keeps the 60-minute reset link. Bound in place
 * of Fortify's controller in AppServiceProvider.
 */
class InviteAwareNewPasswordController extends NewPasswordController
{
    protected function broker(): PasswordBroker
    {
        $email = request()->string('email')->value();
        $neverSignedIn = $email !== '' && Person::query()->where('email', $email)->whereNull('password')->exists();

        return Password::broker($neverSignedIn ? 'invites' : config('fortify.passwords'));
    }
}
