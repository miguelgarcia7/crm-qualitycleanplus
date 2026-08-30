{{--
    Overrides Laravel's mail message layout for ONE reason: the stock header
    links to config('app.url'), which is always the back office. Mail sent to
    property managers and contractors must point at QC Minute instead, or the
    recipient lands on a surface they cannot sign in to.

    Notifications set `headerUrl` / `headerName` via MailMessage::$viewData;
    vendor/notifications/email.blade.php forwards them here as props, because
    anonymous components do not inherit the parent view's data. Both fall back
    to the stock config values, so callers that do not care are unaffected.

    Only this file, its text/ counterpart, and the notification view are
    overridden — the rest of the mail views still come from the framework.
--}}
@props(['headerUrl' => null, 'headerName' => null])
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="$headerUrl ?? config('app.url')">
{{ $headerName ?? config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ $headerName ?? config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
