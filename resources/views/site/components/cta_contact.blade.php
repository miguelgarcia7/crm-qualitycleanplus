



                <div class="row pt-5 ui_animate">
                    <div class="col-md-4">
                        <h3>{{ __('site/layout.cta.quote_title') }}<br />{{ __('site/layout.cta.quote_call') }}</h3>
                        <p><a href="tel:214-271-5595">214-271-5595</a></p>

                    </div>
                    <div class="col-md-4">
                        <h3 class="mb-2">{{ __('site/layout.cta.job_seekers') }}</h3>
                        <p>{{ __('site/layout.cta.job_seekers_text') }}</p>
                        <p><a href="{{ $site->route('application') }}" class="btn btn-yellow">{{ __('site/layout.cta.apply_online') }}</a></p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="mb-2">{{ __('site/layout.cta.employers') }}</h3>
                        <p>{{ __('site/layout.cta.employers_text') }}</p>
                        <p><a href="{{ $site->route('contact.business') }}" class="btn btn-black">{{ __('site/layout.cta.contact_us') }}</a></p>
                    </div>
                </div> <!-- /.row -->
