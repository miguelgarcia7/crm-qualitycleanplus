@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/job_seekers.meta.title'),
    'meta_description' =>   __('site/job_seekers.meta.description'),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">
            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/job_seekers.title') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row">
                    <div class="col-md-10 section-text ui_animate" data-animate-delay=".2">
                        <h2 title="{{ __('site/job_seekers.heading') }}">{{ __('site/job_seekers.heading') }}</h2>

                    </div>
                </div>

                <div class="row">

                    <div class="col-md-12 col-lg-5 col-xl-4 ui_animate" data-animate-delay=".3" style="overflow: hidden;">

                        @if(session('message'))
                            <div class="row">
                                <div class="col">
                                    <p><span class="text-success">{{ session('message') }}</span><br />
                                        {!! __('site/forms.immediate_help', ['phone' => '<a href="tel:214-271-5595">214-271-5595</a>']) !!}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <form action="{{ $site->route('contact.job-seekers.store') }}" method="post" id="contact_us_form">

                            @csrf

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_first_name" class="form-label">{{ __('site/forms.first_name') }} <sup>*</sup></label>
                                    <input type="text" name="contact_first_name" class="form-control" id="contact_first_name" value="{{ old('contact_first_name') }}" required>
                                    @error('contact_first_name')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_last_name" class="form-label">{{ __('site/forms.last_name') }} <sup>*</sup></label>
                                    <input type="text" name="contact_last_name" class="form-control" id="contact_last_name" value="{{ old('contact_last_name') }}" required>
                                    @error('contact_last_name')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <label for="contact_email" class="form-label">{{ __('site/forms.email') }} <sup>*</sup></label>
                                    <input type="email" name="contact_email" class="form-control" id="contact_email" value="{{ old('contact_email') }}" required>
                                    @error('contact_email')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_phone" class="form-label">{{ __('site/forms.phone') }}</label>
                                    <input type="tel" name="contact_phone" class="form-control" id="contact_phone" value="{{ old('contact_phone') }}">
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_call_back_time" class="form-label">{{ __('site/job_seekers.call_back_time') }}</label>
                                    <select name="contact_call_back_time" class="form-select" aria-label="{{ __('site/job_seekers.call_back_time_aria') }}">
                                        <option {{ old('contact_call_back_time') == 'Daytime' ? "selected" : "" }} value="Daytime">{{ __('site/job_seekers.call_back_times.daytime') }}</option>
                                        <option {{ old('contact_call_back_time') == 'Mornings' ? "selected" : "" }} value="Mornings">{{ __('site/job_seekers.call_back_times.mornings') }}</option>
                                        <option {{ old('contact_call_back_time') == 'Afternoons' ? "selected" : "" }} value="Afternoons">{{ __('site/job_seekers.call_back_times.afternoons') }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <label for="contact_message" class="form-label">{{ __('site/forms.message') }}</label>
                                    <textarea name="contact_message" class="form-control" id="contact_message" rows="3">{{ old('contact_message') }}</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    @include('site.elements.recaptcha', ['action' => 'contact'])

                                    <button type="submit" id="form_submit" class="btn btn-yellow">{{ __('site/forms.submit') }}
                                        <span id="form_submit_spinner" style="display:none;" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        <span class="visually-hidden">{{ __('site/forms.loading') }}</span>
                                    </button>
                                    @include('site.elements.recaptcha_notice')
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 ui_animate" data-animate-delay=".4">
                        <img src="/images/job-seekers-1.jpg" alt="{{ __('site/job_seekers.image_alt') }}" class="img-fluid">
                    </div>
                </div>


                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->

    </section>

@endsection
