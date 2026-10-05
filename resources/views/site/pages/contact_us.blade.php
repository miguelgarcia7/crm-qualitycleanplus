@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/contact.meta.title'),
    'meta_description' =>   __('site/contact.meta.description'),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">
            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/contact.title') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row">
                    <div class="col-md-10 section-text ui_animate" data-animate-delay=".2">
                        <h2 title="{{ __('site/contact.details') }}">{{ __('site/contact.get_in_touch') }}</h2>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 col-lg-4 ui_diviter mb-5 ui_animate" data-animate-delay=".3">
                        <h3>{{ __('site/contact.job_seekers.title') }}</h3>

                        <p>
                            <a href="{{ $site->route('job-openings') }}">{{ __('site/contact.job_seekers.openings') }}</a><br />
                        </p>
                        <p>
                            <a href="{{ $site->route('contact.job-seekers') }}">{{ __('site/contact.job_seekers.contact') }}</a><br />
                        </p>

                    </div>

                    <div class="col-md-12 col-lg-4 ui_diviter mb-5 ui_animate" data-animate-delay=".4">
                        <h3>{{ __('site/contact.businesses.title') }}</h3>
                        <p><a href="{{ $site->route('contact.business') }}">{{ __('site/contact.businesses.inquiries') }}</a></p>

                    </div>

                    <div class="col-md-12 col-lg-4 ui_animate" data-animate-delay=".3">
                        <h3>{{ __('site/contact.general.title') }}</h3>
                        <p>
                            <span class="small">{{ __('site/layout.footer.phone') }}:</span><br /><a href="tel:214-271-5595">214-271-5595</a><br />
                            <span class="small">{{ __('site/layout.footer.email') }}:</span><br /><a rel="nofollow" href="javascript:uix_con_todo('d.aguilar')">{{ __('site/layout.footer.email_link') }}</a><br />
                            <span class="small">{{ __('site/contact.general.address') }}:</span><br /><a href="https://goo.gl/maps/cPtH6aCdfyP2Baxo7" target="_blank" rel="nofollow">1720 Regal Row, Suite 126<br />Dallas, Texas 75235</a><br /><br />
                            <a href="https://goo.gl/maps/cPtH6aCdfyP2Baxo7" target="_blank" rel="nofollow">{{ __('site/contact.general.directions') }}</a><br /><br />
                        </p>

                        <h4>{{ __('site/layout.footer.hours_title') }}</h4>
                        <p>
                            @foreach (__('site/layout.footer.hours') as $line)
                                {{ $line }}<br />
                            @endforeach
                            <br />
                        </p>

                    </div>

                </div>


                @include('site.components/cta_contact')


            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
