{{-- Google's required wording, shown because the floating reCAPTCHA badge is hidden (site app.scss). --}}
@if (\App\Domain\Marketing\Support\Recaptcha::enabled())
    <p class="form-text mt-2 mb-0">
        This site is protected by reCAPTCHA and the Google
        <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy Policy</a> and
        <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms of Service</a> apply.
    </p>
@endif
