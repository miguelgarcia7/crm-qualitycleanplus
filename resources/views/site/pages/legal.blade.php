@extends('site.layouts.website')

{{-- Privacy Policy and Terms of Use: $doc is 'privacy' or 'terms' (lang/{en,es}/site/{doc}.php). --}}
@php
    $links = ['contact_url' => $site->route('contact'), 'privacy_url' => $site->route('privacy'), 'terms_url' => $site->route('terms')];
@endphp

@include('site.elements.meta_data',[
    'meta_title' =>         __("site/{$doc}.meta.title"),
    'meta_description' =>   __("site/{$doc}.meta.description"),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __("site/{$doc}.title") }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div class="wrap content legal">

            <div class="container">
                <div class="row">
                    <div class="col-lg-9 col-xl-8">

                        <p class="legal-updated">{{ __("site/{$doc}.updated") }}</p>

                        {{-- Trusted copy from our own lang files; it carries simple links and emphasis. --}}
                        @foreach (__("site/{$doc}.intro", $links) as $paragraph)
                            <p>{!! $paragraph !!}</p>
                        @endforeach

                        @foreach (__("site/{$doc}.sections", $links) as $section)
                            <h2>{{ $section['heading'] }}</h2>
                            @foreach ($section['blocks'] as $block)
                                @if (is_array($block))
                                    <ul>
                                        @foreach ($block as $item)
                                            <li>{!! $item !!}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <p>{!! $block !!}</p>
                                @endif
                            @endforeach
                        @endforeach

                    </div>
                </div>
            </div>

        </div>

    </section>

@endsection
