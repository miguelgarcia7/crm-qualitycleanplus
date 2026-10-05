@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/jobs.meta.title'),
    'meta_description' =>   __('site/jobs.meta.description'),
    'meta_image' => '/images/social-media-website-work.png',
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/jobs.title') }}</h1>

                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row">
                    <div class="col-md-12 section-text">
                        <div class="ui_animate" data-animate-delay=".2">
                            <h2 title="{{ __('site/jobs.join_hover') }}">{{ __('site/jobs.join_title') }}</h2>
                            <p>{{ __('site/jobs.intro') }} <a href="{{ $site->route('application') }}">{{ __('site/layout.cta.apply_online') }}</a></p>
                        </div>

                        <table class="table table-striped table-hover ui_animate" data-animate-delay=".3">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('site/jobs.table.job_title') }}</th>
                                    <th scope="col">{{ __('site/jobs.table.hours') }}</th>
                                    <th scope="col">{{ __('site/jobs.table.location') }}</th>
                                    <th scope="col">{{ __('site/jobs.table.apply') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($positions as $position)
                                <tr>
                                    <th scope="row">{{ $position->title }}</th>
                                    <td>
                                        @if($position->hour_start && $position->hour_end)
                                            {{ date("g:i a", strtotime($position->hour_start)) }} - {{ date("g:i a", strtotime($position->hour_end)) }}
                                        @else
                                            &mdash;
                                        @endif
                                    </td>
                                    <td>{{ $position->locationName() ?? __('site/jobs.table.various_locations') }}</td>
                                    <td><a href="{{ $site->route('application.apply', $position) }}">{{ __('site/jobs.table.apply_today') }}</a></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4">{!! __('site/jobs.table.empty', ['apply' => '<a href="'.e($site->route('application')).'">'.e(__('site/jobs.table.empty_apply')).'</a>']) !!}</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>

                    </div>

                </div>


                @include('site.components/cta_contact')



            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection
