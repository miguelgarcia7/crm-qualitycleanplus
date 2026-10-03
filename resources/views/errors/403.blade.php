{{--
    Laravel's stock 403, plus a way out for a signed-in account. Signing in on the
    wrong surface (a back-office account on QC Minute, or the reverse) lands here,
    and logout is POST-only, so without this button the only escape was clearing
    the site's cookies. Sessions are per-host, so this signs out of this host only.
--}}
@extends('errors::minimal')

@section('title', __('Forbidden'))
@section('code', '403')
@section('message')
    {{ __($exception->getMessage() ?: 'Forbidden') }}
    @auth
        <form method="POST" action="/logout" style="margin-top: 0.75rem">
            @csrf
            <button type="submit" style="font-size: 0.875rem; text-decoration: underline; cursor: pointer; background: none; border: 0; padding: 0; color: inherit">
                Sign out and use a different account
            </button>
        </form>
    @endauth
@endsection
