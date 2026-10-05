

        <div id="footer" class="footer wrap">
            <div class="container">
                <div class="row">
                    <div class="col-md-12">
                        <img src="/images/footer-logo.png" class="logo-footer mb-4" alt="Quality Cleaning Plus">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12 col-sm-6 col-md-6 col-lg-4 mb-3">
                        <h4>{{ __('site/layout.footer.company') }}</h4>
                        <p>
                            1720 Regal Row, Suite 126<br />
                            Dallas, Texas 75235<br />
                            {{ __('site/layout.footer.email') }}: <a rel="nofollow" href="javascript:uix_con_todo('d.aguilar')">{{ __('site/layout.footer.email_link') }}</a>
                            <br />
                            {{ __('site/layout.footer.phone') }}: <a href="tel:214-271-5595">214-271-5595</a><br />
                        </p>

                    </div>
                    <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-3">
                        <h4>{{ __('site/layout.footer.hours_title') }}</h4>
                        <p>
                            @foreach (__('site/layout.footer.hours') as $line)
                                {{ $line }}<br />
                            @endforeach
                        </p>
                    </div>
                    <div class="col-12 col-sm-6 col-md-6 col-lg-3 mb-3">
                        <h4>{{ __('site/layout.footer.about_title') }}</h4>
                        <ul class="footer-nav">
                            <li><a href="{{ $site->route('services') }}">{{ __('site/layout.nav.services_title') }}</a></li>
                            <li><a href="{{ $site->route('job-openings') }}">{{ __('site/layout.nav.job_openings') }}</a></li>
                            <li><a href="{{ $site->route('about') }}">{{ __('site/layout.nav.about') }}</a></li>
                            <li><a href="{{ $site->route('contact') }}">{{ __('site/layout.nav.contact') }}</a></li>
                        </ul>
                    </div>
                    <div class="col-12 col-sm-6 col-md-6 col-lg-2 mb-3">
                        <h4>{{ __('site/layout.footer.follow') }}</h4>
                        <a class="d-inline me-1" href="https://www.facebook.com/profile.php?id=100071842145369" target="_blank" rel="noopener"><img src="/images/icons/facebook.svg" width="30" height="30" alt="Facebook"></a>
                        <a class="d-inline me-1" href="https://www.instagram.com/qualitycleaningplus/" target="_blank" rel="noopener"><img src="/images/icons/instagram.svg" width="30" height="30" alt="Instagram"></a>
                        <a class="d-inline" href="https://www.tiktok.com/@qualitycleaningplus" target="_blank" rel="noopener"><img src="/images/icons/tiktok.svg" width="28" height="28" alt="TikTok"></a>
                    </div>
                </div>
            </div>
        </div>  <!-- #footer .wrap  -->

        <div class="sub-footer">
            <div class="container">
                <div class="row">
                    <div class="col-md-9">
                        <p>&copy; {{ now()->year }} {{ __('site/layout.footer.rights') }}</p>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <p>{{ __('site/layout.footer.site_by') }}: <a href="https://www.studiomex.com?ref=qualitycleanplus.com" target="_blank" rel="noopener">StudioMex</a></p>
                    </div>
                </div>
            </div>
        </div>
