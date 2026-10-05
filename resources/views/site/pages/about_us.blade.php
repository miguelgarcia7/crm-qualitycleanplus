@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/about.meta.title'),
    'meta_description' =>   __('site/about.meta.description'),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/layout.nav.about') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">

            <div class="container our_services">
                <div class="row">
                    <div class="col-md-6 section-text ui_animate" data-animate-delay=".2">

                        <h2 title="{{ __('site/about.tooltip') }}">Quality Cleaning Plus</h2>
                        <p>{{ __('site/about.body') }}</p>
                        <br /><br />
                    </div>
                    <div class="col-md-5 offset-md-1 section-text ui_animate" data-animate-delay=".4">
                        <img src="/images/about_us-1.jpg" alt="{{ __('site/about.image_alt') }}" class="img-fluid">
                    </div>
                </div>

                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
