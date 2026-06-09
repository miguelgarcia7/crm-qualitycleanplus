@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         'Contact Us',
    'meta_description' =>   'Give us a call and let our team of experts find the right solution for your business.',
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">
            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">Contact Us</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row">
                    <div class="col-md-10 section-text ui_animate" data-animate-delay=".2">
                        <h2 title="Contact Details">Get In Touch</h2>

                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 col-lg-4 ui_diviter mb-5 ui_animate" data-animate-delay=".3">
                        <h3>Job Seekers</h3>

                        <p>
                            <a href="/job-openings">View Open Positions</a><br />
                        </p>
                        <p>
                            <a href="/contact-us/job-seekers">Contact Us</a><br />
                        </p>

                    </div>

                    <div class="col-md-12 col-lg-4 ui_diviter mb-5 ui_animate" data-animate-delay=".4">
                        <h3>Businesses</h3>
                        <p><a href="/contact-us/business-inquiries">Staffing & Services Inquiries</a></p>

                    </div>

                    <div class="col-md-12 col-lg-4 ui_animate" data-animate-delay=".3">
                        <h3>General Information</h3>
                        <p>
                            <span class="small">Phone:</span><br /><a href="tel:214-271-5595">214-271-5595</a><br />
                            <span class="small">Email:</span><br /><a rel="nofollow" href="javascript:uix_con_todo('d.aguilar')">Contact Us</a><br />
                            <span class="small">Address:</span><br /><a href="https://goo.gl/maps/cPtH6aCdfyP2Baxo7" target="_blank" rel="nofollow">1720 Regal Row Suite 126<br />Dallas, Texas 75235</a><br /><br />
                            <a href="https://goo.gl/maps/cPtH6aCdfyP2Baxo7" target="_blank" rel="nofollow">Get Driving Directions</a><br /><br />
                        </p>

                        <h4>Business Hours</h4>
                        <p>
                            Mon-Thur: 9:30am - 5:30pm<br />
                            Friday: 9:30am - 6pm<br />
                            Sat and Sun: Closed<br /><br />
                        </p>

                    </div>

                </div>


                @include('site.components/cta_contact')


            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
