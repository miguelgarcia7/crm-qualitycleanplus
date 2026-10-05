{{--
    reCAPTCHA v3 for a public form — include inside the <form> with the action the
    server expects: @include('site.elements.recaptcha', ['action' => 'contact']).
    resources/js/site/app.js fetches the token on submit; the form request's
    ChecksRecaptcha concern scores it. Renders only the error slot while the keys
    are unset.
--}}
@if (\App\Domain\Marketing\Support\Recaptcha::enabled())
    <input type="hidden" name="g-recaptcha-response" data-recaptcha-action="{{ $action }}">
    @once
        @push('scripts')
            <script>window.recaptchaSiteKey = @json(config('services.recaptcha.site_key'));</script>
            <script src="https://www.google.com/recaptcha/api.js?render={{ config('services.recaptcha.site_key') }}"></script>
        @endpush
    @endonce
@endif
@error('g-recaptcha-response')
    <div class="form-text text-danger mb-2">{{ $message }}</div>
@enderror
{{-- Set by SiteErrorPage when a form is sent after its session expired. --}}
@error('form')
    <div class="form-text text-danger mb-2">{{ $message }}</div>
@enderror
