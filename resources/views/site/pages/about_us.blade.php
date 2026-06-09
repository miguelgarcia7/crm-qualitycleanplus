@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         'About Us',
    'meta_description' =>   'We are centered on integrity and passion and to always go above and beyond what’s expected.',
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">About Us</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">

            <div class="container our_services">
                <div class="row">
                    <div class="col-md-6 section-text ui_animate" data-animate-delay=".2">

                        <h2 title="About Quality Cleaning Plus">Quality Cleaning Plus</h2>
                        <p>Quality Cleaning Plus is centered on integrity and passion to go about and beyond the foreseeable. Our mission is to deliver the best service in the industry with the best quality of people. With years of experience in the staffing and recruitment industry, we have developed a deep understanding of various sectors and their unique demands. Our knowledgeable team is well-versed in identifying the right talent for specific roles, ensuring a perfect match between candidates and employers. Our clients have come to rely on our commitment to the basics of delivering an honest day's work for an honest day's wage. We believe in the power of personalized service. Our team takes the time to understand the specific needs and requirements of both employers and job seekers, enabling us to deliver tailored solutions that foster long-term success.
Expect nothing but the best if you partner with us.</p>
                        <br /><br />
                    </div>
                    <div class="col-md-5 offset-md-1 section-text ui_animate" data-animate-delay=".4">
                        <img src="/images/about_us-1.jpg" alt="Dallas Services" class="img-fluid">
                    </div>
                </div>

                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
