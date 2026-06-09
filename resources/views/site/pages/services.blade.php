@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         'Our Services',
    'meta_description' =>   'We offer a wide range of services and solutions for your business in the areas of hospitality, cleaning and custruction and more.',
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">Services</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row ui_animate" data-animate-delay=".2">
                    <div class="col">
                        <div class="eyebrow">Your one stop shop</div>
                        <h2 title="Our Services">Our Company Services</h2>
                        <p>We offer a wide range of services for your business in the areas of hospitality, cleaning, construction and more.</p>
                        <br />
                    </div>
                </div>
                <div class="row ui_animate" data-animate-delay=".3">
                    <div class="col-md-4 mb-md-5 text-md-end">
                        <h3>Hospitality</h3>
                        <ul class="uix-list-none">
                            <li>Managers</li>
                            <li>Front Desk</li>
                            <li>Housekeeping Supervisors</li>
                            <li>Housekeeping</li>
                            <li>Houseman</li>
                            <li>Public Area Attendant</li>
                            <li>Laundry Attendant</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 offset-md-1">
                        <img src="/images/services-hospitality.jpg?v=20222203" alt="Hospitality Services" class="img-fluid">
                    </div>
                </div>
                <div class="row ui_animate" data-animate-delay=".2">
                    <div class="col-md-4 mb-md-5 offset-md-1 order-md-2">
                        <h3>Food and Beverage/Banquets</h3>
                        <ul class="uix-list-none">
                            <li>Stewarding</li>
                            <li>Dishwashing</li>
                            <li>Servers</li>
                            <li>Cooks</li>
                            <li>Set Up</li>
                            <li>Bartenders</li>
                            <li>Bussers</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 order-md-1">
                        <img src="/images/services-banquets.jpg?v=20222203" alt="Banquet Services" class="img-fluid">
                    </div>
                </div>
                <div class="row ui_animate">
                    <div class="col-md-4 mb-md-5 text-md-end">
                        <h3>Light Industrial</h3>
                        <ul class="uix-list-none">
                            <li>Packaging</li>
                            <li>Day Porter</li>
                            <li>Assembly</li>
                            <li>Quality Control</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 offset-md-1">
                        <img src="/images/services-light-industrial.jpg?v=20222203" alt="Light Industrial Services" class="img-fluid">
                    </div>
                </div>
                <div class="row ui_animate">
                    <div class="col-md-4 mb-md-5 offset-md-1 order-md-2">
                        <h3>Remodeling</h3>
                        <ul class="uix-list-none">
                            <li>Remodeling Clean Up</li>
                            <li>Painters</li>
                            <li>Maintenance</li>
                            <li>Carpet Cleaning</li>
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 order-md-1">
                        <img src="/images/services-remodeling.jpg?v=20222203" alt="Remodeling Services" class="img-fluid">
                    </div>
                </div>

                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
