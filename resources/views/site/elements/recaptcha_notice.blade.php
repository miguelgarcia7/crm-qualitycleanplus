{{-- Google's required wording, shown because the floating reCAPTCHA badge is hidden (site app.scss). --}}
@if (\App\Domain\Marketing\Support\Recaptcha::enabled())
    <p class="form-text mt-2 mb-0">
        {!! __('site/layout.recaptcha_notice', [
            'privacy' => '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener">'.e(__('site/layout.privacy_policy')).'</a>',
            'terms' => '<a href="https://policies.google.com/terms" target="_blank" rel="noopener">'.e(__('site/layout.terms')).'</a>',
        ]) !!}
    </p>
@endif
