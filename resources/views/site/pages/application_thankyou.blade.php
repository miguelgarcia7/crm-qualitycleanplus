@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         'Thank You',
    'meta_description' =>   '',
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">Thank You</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">

            <div class="container our_services">
                <div class="row">
                    <div class="col-md-8 section-text ui_animate" data-animate-delay=".2">
                        <h2>Your application has been submitted.</h2>
                        <p>We will contact you soon.</p>
                        <p>Have any questions? Call us 214-271-5595</p>

                    </div>

                </div>

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->

    </section>

@endsection
