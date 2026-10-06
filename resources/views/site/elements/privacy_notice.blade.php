{{-- Under each form that collects personal information: where to read how it's used. --}}
<p class="form-text mt-2 mb-0">
    {!! __('site/forms.privacy_notice', [
        'privacy' => '<a href="'.e($site->route('privacy')).'">'.e(__('site/layout.footer.privacy')).'</a>',
    ]) !!}
</p>
