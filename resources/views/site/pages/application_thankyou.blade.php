@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/thanks.meta.title'),
    'meta_description' =>   __('site/thanks.meta.description'),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/thanks.title') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">

            <div class="container our_services">
                <div class="row">
                    <div class="col-md-8 section-text ui_animate" data-animate-delay=".2">
                        <h2>{{ __('site/thanks.submitted') }}</h2>
                        <p>{{ __('site/thanks.contact_soon') }}</p>
                        <p>{{ __('site/thanks.questions') }}</p>

                    </div>

                </div>

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->

    </section>

@endsection
