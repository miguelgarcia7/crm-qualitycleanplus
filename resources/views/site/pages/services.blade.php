@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/services.meta.title'),
    'meta_description' =>   __('site/services.meta.description'),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/layout.nav.services') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row ui_animate" data-animate-delay=".2">
                    <div class="col">
                        <div class="eyebrow">{{ __('site/services.eyebrow') }}</div>
                        <h2 title="{{ __('site/layout.nav.services_title') }}">{{ __('site/services.title') }}</h2>
                        <p>{{ __('site/services.text') }}</p>
                        <br />
                    </div>
                </div>
                <div class="row ui_animate" data-animate-delay=".3">
                    <div class="col-md-4 mb-md-5 text-md-end">
                        <h3>{{ __('site/services.hospitality.title') }}</h3>
                        <ul class="uix-list-none">
                            @foreach (__('site/services.hospitality.items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 offset-md-1">
                        <img src="/images/services-hospitality.jpg?v=20222203" alt="{{ __('site/services.hospitality.image_alt') }}" class="img-fluid">
                    </div>
                </div>
                <div class="row ui_animate" data-animate-delay=".2">
                    <div class="col-md-4 mb-md-5 offset-md-1 order-md-2">
                        <h3>{{ __('site/services.banquets.title') }}</h3>
                        <ul class="uix-list-none">
                            @foreach (__('site/services.banquets.items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 order-md-1">
                        <img src="/images/services-banquets.jpg?v=20222203" alt="{{ __('site/services.banquets.image_alt') }}" class="img-fluid">
                    </div>
                </div>
                <div class="row ui_animate">
                    <div class="col-md-4 mb-md-5 text-md-end">
                        <h3>{{ __('site/services.light_industrial.title') }}</h3>
                        <ul class="uix-list-none">
                            @foreach (__('site/services.light_industrial.items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 offset-md-1">
                        <img src="/images/services-light-industrial.jpg?v=20222203" alt="{{ __('site/services.light_industrial.image_alt') }}" class="img-fluid">
                    </div>
                </div>
                <div class="row ui_animate">
                    <div class="col-md-4 mb-md-5 offset-md-1 order-md-2">
                        <h3>{{ __('site/services.remodeling.title') }}</h3>
                        <ul class="uix-list-none">
                            @foreach (__('site/services.remodeling.items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="col-md-6 mb-5 order-md-1">
                        <img src="/images/services-remodeling.jpg?v=20222203" alt="{{ __('site/services.remodeling.image_alt') }}" class="img-fluid">
                    </div>
                </div>

                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
