<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class AuthController extends Controller
{
    public function cardDeleteAccount()
    {
        return Inertia::render('auth/card/delete-account/index');
    }

    public function cardLockScreen()
    {
        return Inertia::render('auth/card/lock-screen/index');
    }

    public function cardLoginPin()
    {
        return Inertia::render('auth/card/login-pin/index');
    }

    public function cardNewPass()
    {
        return Inertia::render('auth/card/new-pass/index');
    }

    public function cardResetPass()
    {
        return Inertia::render('auth/card/reset-pass/index');
    }

    public function cardSignIn()
    {
        return Inertia::render('auth/card/sign-in/index');
    }

    public function cardSignUp()
    {
        return Inertia::render('auth/card/sign-up/index');
    }

    public function cardSuccessMail()
    {
        return Inertia::render('auth/card/success-mail/index');
    }

    public function cardTwoFactor()
    {
        return Inertia::render('auth/card/two-factor/index');
    }

    public function deleteAccount()
    {
        return Inertia::render('auth/basic/delete-account/index');
    }

    public function lockScreen()
    {
        return Inertia::render('auth/basic/lock-screen/index');
    }

    public function loginPin()
    {
        return Inertia::render('auth/basic/login-pin/index');
    }

    public function newPass()
    {
        return Inertia::render('auth/basic/new-pass/index');
    }

    public function resetPass()
    {
        return Inertia::render('auth/basic/reset-pass/index');
    }

    public function signIn()
    {
        return Inertia::render('auth/basic/sign-in/index');
    }

    public function signUp()
    {
        return Inertia::render('auth/basic/sign-up/index');
    }

    public function splitDeleteAccount()
    {
        return Inertia::render('auth/split/delete-account/index');
    }

    public function splitLockScreen()
    {
        return Inertia::render('auth/split/lock-screen/index');
    }

    public function splitLoginPin()
    {
        return Inertia::render('auth/split/login-pin/index');
    }

    public function splitNewPass()
    {
        return Inertia::render('auth/split/new-pass/index');
    }

    public function splitResetPass()
    {
        return Inertia::render('auth/split/reset-pass/index');
    }

    public function splitSignIn()
    {
        return Inertia::render('auth/split/sign-in/index');
    }

    public function splitSignUp()
    {
        return Inertia::render('auth/split/sign-up/index');
    }

    public function splitSuccessMail()
    {
        return Inertia::render('auth/split/success-mail/index');
    }

    public function splitTwoFactor()
    {
        return Inertia::render('auth/split/two-factor/index');
    }

    public function successMail()
    {
        return Inertia::render('auth/basic/success-mail/index');
    }

    public function twoFactor()
    {
        return Inertia::render('auth/basic/two-factor/index');
    }
}
