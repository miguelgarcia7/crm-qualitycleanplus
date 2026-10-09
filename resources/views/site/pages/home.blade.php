@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/home.meta.title'),
    'meta_description' =>   __('site/home.meta.description'),
    ])
@include('site.elements.structured_data')

@section('content')

    <section id="home">

        <div class="header-wrap">

            <div class="hero container">
                <div class="row circle">
                    <div class="col-md-7 col-lg-6">
                        <div class="hero-intro ui_animate" data-animate-delay=".1">
                            <h1>{{ __('site/home.hero.title') }}</h1>
                            <p>{{ __('site/home.hero.text') }}</p>
                            <a href="{{ $site->route('contact') }}" class="btn btn-lg btn-yellow me-4">{{ __('site/layout.nav.contact') }}</a> <a href="{{ $site->route('job-openings') }}" class="btn btn-lg btn-yellow">{{ __('site/layout.nav.job_openings') }}</a>
                        </div>
                    </div>
                    <div class="col-md-5 col-lg-5 offset-lg-1 ui_animate" data-animate-delay=".2">
                        <div class="hero-circle">
                            <img src="/images/home-hero-dallas.jpg" width="1080" height="1080" alt="{{ __('site/home.hero.image_alt') }}">
                        </div>
                    </div>
                </div>
            </div>


        </div> {{-- /.header-wrap --}}



        <div id="our_services" class="wrap home-content">
            <div class="container our_services">
                <div class="row">
                    <div class="col section-text text-center ui_animate" data-animate-delay=".3">
                        <div class="eyebrow">{{ __('site/home.services.eyebrow') }}</div>
                        <h2 title="{{ __('site/layout.nav.services_title') }}">{{ __('site/home.services.title') }}</h2>
                        <p>{{ __('site/home.services.text') }}</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-hospitality.jpg');" data-animate-delay=".3">
                            <div class="cta-text">
                                <h5>{{ __('site/home.services.hospitality.title') }}</h5>
                                <p>{{ __('site/home.services.hospitality.text') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-banquets.jpg');" data-animate-delay=".4">

                            <div class="cta-text">
                                <h5>{{ __('site/home.services.banquets.title') }}</h5>
                                <p>{{ __('site/home.services.banquets.text') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-remodeling.jpg');" data-animate-delay=".5">
                            <div class="cta-text">
                                <h5>{{ __('site/home.services.remodeling.title') }}</h5>
                                <p>{{ __('site/home.services.remodeling.text') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-light-industrial.jpg');" data-animate-delay=".6">
                            <div class="cta-text">
                                <h5>{{ __('site/home.services.light_industrial.title') }}</h5>
                                <p>{{ __('site/home.services.light_industrial.text') }}</p>
                            </div>
                        </div>
                    </div>

                </div> <!-- /.row -->

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->


        @if(!empty($testimonials) && count($testimonials))
        <div id="testimonials" class="">
            <div class="container">
                @include('site.components/testimonials')
            </div>
        </div>
        @endif

        <div class="container mb-5">
            @include('site.components/cta_contact')
        </div>

    </section>

@endsection
