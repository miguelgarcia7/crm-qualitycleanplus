{{--
    reCAPTCHA v3 for a public form — include inside the <form> with the action the
    server expects: @include('site.elements.recaptcha', ['action' => 'contact']).
    resources/js/site/app.js fetches the token on submit; App\Rules\Recaptcha checks
    it. Renders only the error slot while the keys are unset.
--}}
@if (\App\Rules\Recaptcha::enabled())
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
