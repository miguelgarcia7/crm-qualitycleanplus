@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         'Job Openings',
    'meta_description' =>   'Join our team. We are a company that is currently growing that is focus on our people to expand our solutions and services.',
    'meta_image' => 'https://www.qualitycleanplus.com/images/social-media-website-work.png',
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">Jobs Openings</h1>

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
                            <h2 title="See Our Job Opportunities">Join Our Team</h2>
                            <p>Join our team and find out about jobs available. <a href="/application">Apply Online</a></p>
                        </div>

                        <table class="table table-striped table-hover ui_animate" data-animate-delay=".3">
                            <thead>
                                <tr>
                                    <th scope="col">Job Title</th>
                                    <th scope="col">Hours</th>
                                    <th scope="col">Location</th>
                                    <th scope="col">Apply</th>
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
                                    <td>{{ $position->locationName() ?? 'Various locations' }}</td>
                                    <td><a href="/application/{{ $position->slug }}">Apply Today</a></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4">There are no open positions listed right now &mdash; <a href="/application">apply online</a> and we'll keep you in mind.</td>
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
