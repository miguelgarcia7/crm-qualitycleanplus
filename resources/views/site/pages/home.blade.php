@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         'Quality Cleaning Experts for Residential and Commercial',
    'meta_description' =>   'Our mission is to deliver the best service in the industry with the best people and quality.',
    ])

@section('css_after')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
@endsection

@section('content')

    <section id="home">

        <div class="header-wrap">

            <div class="hero container">
                <div class="row circle">
                    <div class="col-md-7 col-lg-6">
                        <div class="hero-intro ui_animate" data-animate-delay=".1">
                            <h1>The Hospitality Experts</h1>
                            <p>We offer a wide range of services and strive to deliver reliable, fast, and top of the line quality.</p>
                            <a href="/contact-us" class="btn btn-lg btn-yellow me-4">Contact Us</a> <a href="/job-openings" class="btn btn-lg btn-yellow">Job Openings</a>
                        </div>
                    </div>
                    <div class="col-md-5 col-lg-5 offset-lg-1 ui_animate" data-animate-delay=".2">
                        <div class="video-hero">
                            <div class="video-wrap">
                                <video src="/media/downtown_dallas.mp4" autoplay loop playsinline muted></video>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div> {{-- /.header-wrap --}}



        <div id="our_services" class="wrap home-content">
            <div class="container our_services">
                <div class="row">
                    <div class="col section-text text-center ui_animate" data-animate-delay=".3">
                        <div class="eyebrow">Your one stop shop</div>
                        <h2 title="Our Services">Featured Services</h2>
                        <p>We offer a wide range of services for your business in the areas of hospitality, cleaning, construction and more.</p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-hospitality.jpg');" data-animate-delay=".3">
                            <div class="cta-text">
                                <h5>Hospitality</h5>
                                <p>Managers, Front Desk, Housekeeping, and much more.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-banquets.jpg');" data-animate-delay=".4">

                            <div class="cta-text">
                                <h5>Food and Beverage/Banquets</h5>
                                <p>Stewarding, Dishwashing, Servers, and much more.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-remodeling.jpg');" data-animate-delay=".5">
                            <div class="cta-text">
                                <h5>Remodeling</h5>
                                <p>Remodeling, Painters, Maintenance, and much more.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-12 col-md-6 col-lg-3 mb-3">
                        <div class="cta-box ui_animate" style="background-image: url('/images/home-light-industrial.jpg');" data-animate-delay=".6">
                            <div class="cta-text">
                                <h5>Light Industrial</h5>
                                <p>Packaging, Day Porter, Assembly and much more.</p>
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

@section('js_after')
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        var swiper = new Swiper(".qc-swiper", {
                slidesPerView: 1,
                spaceBetween: 10,
                breakpoints: {
                    // when window width is >= 320px
                    320: {
                        slidesPerView: 1,
                        spaceBetween: 10,
                    },
                    // when window width is >= 480px
                    768: {
                        slidesPerView: 2,
                        spaceBetween: 10,
                    },
                    // when window width is >= 640px
                    992: {
                        slidesPerView: 3,
                        spaceBetween: 30,
                    }
                },
                autoplay: {
                    delay: 7000,
                },
                pagination: {
                    el: ".swiper-pagination",
                },
                navigation: {
                    nextEl: ".swiper-button-next",
                    prevEl: ".swiper-button-prev",
                },
            });
    </script>
@endsection
