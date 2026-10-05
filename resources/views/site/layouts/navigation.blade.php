            <div class="main-navigation">
                <div class="container">
                    <div class="col-sm-12">
                        <nav class="navbar navbar-expand-lg navbar-dark">
                            <div class="container-fluid">
                                <a class="navbar-brand" href="{{ $site->route('home') }}"><img src="/images/quality-cleaning-plus-logo.png" alt="Quality Cleaning Plus, Inc."></a>

                                {{-- The same page in the other language (SiteLocale::alternate). --}}
                                @php($other = $site->isSpanish() ? 'en' : 'es')
                                @if ($otherUrl = $site->alternate($other))
                                    <div class="nav_language"><a href="{{ $otherUrl }}" hreflang="{{ $other }}" lang="{{ $other }}">{{ \App\Domain\Marketing\Support\SiteLocale::LOCALES[$other] }}</a></div>
                                @endif

                                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('site/layout.nav.toggle') }}">
                                  <span class="navbar-toggler-icon"></span>
                                </button>
                                <div class="collapse navbar-collapse mt-2" id="navbarSupportedContent">
                                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                                        <li class="nav-item"><a href="{{ $site->route('home') }}" class="nav-link {{ $site->is('home') ? 'active' : '' }}">{{ __('site/layout.nav.home') }}</a></li>
                                        <li class="nav-item"><a href="{{ $site->route('about') }}" class="nav-link {{ $site->is('about') ? 'active' : '' }}">{{ __('site/layout.nav.about') }}</a></li>
                                        <li class="nav-item"><a href="{{ $site->route('services') }}" class="nav-link {{ $site->is('services') ? 'active' : '' }}" title="{{ __('site/layout.nav.services_title') }}">{{ __('site/layout.nav.services') }}</a></li>
                                        <li class="nav-item"><a href="{{ $site->route('job-openings') }}" class="nav-link {{ $site->is('job-openings') || $site->is('application') ? 'active' : '' }}">{{ __('site/layout.nav.job_openings') }}</a></li>
                                        <li class="nav-item"><a href="{{ $site->route('contact') }}" class="nav-link {{ $site->is('contact') ? 'active' : '' }}">{{ __('site/layout.nav.contact') }}</a></li>
                                    </ul>

                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div> {{-- /.main-navigation --}}
