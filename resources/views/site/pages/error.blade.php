@extends('site.layouts.website')

@include('site.elements.meta_data', [
    'meta_title' =>         __("site/errors.{$status}.title"),
    'meta_description' =>   __("site/errors.{$status}.message"),
    'noindex' => true,
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1>{{ __("site/errors.{$status}.title") }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}

        <div id="our_services" class="wrap content">

            <div class="container our_services">
                <div class="row">
                    <div class="col-md-8 section-text">
                        <p>{{ __("site/errors.{$status}.message") }}</p>

                        <p class="d-flex flex-wrap gap-2 mt-4">
                            @if ($status === 419)
                                <a href="javascript:history.back()" class="btn btn-yellow">{{ __('site/errors.back') }}</a>
                            @endif
                            <a href="{{ $site->route('home') }}" class="btn {{ $status === 419 ? 'btn-black' : 'btn-yellow' }}">{{ __('site/errors.home') }}</a>
                            @if ($status === 404)
                                <a href="{{ $site->route('job-openings') }}" class="btn btn-black">{{ __('site/errors.jobs') }}</a>
                                <a href="{{ $site->route('contact') }}" class="btn btn-black">{{ __('site/errors.contact') }}</a>
                            @endif
                        </p>

                        <p class="mt-4">{!! __('site/errors.help', ['phone' => '<a href="tel:214-271-5595">214-271-5595</a>']) !!}</p>
                    </div>
                </div>
            </div>

        </div>

    </section>

@endsection
