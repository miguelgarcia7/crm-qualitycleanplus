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
                            <h1 class="ui_animate">Job Seekers</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row">
                    <div class="col-md-10 section-text ui_animate" data-animate-delay=".2">
                        <h2 title="General Inquiries">General Inquiries</h2>

                    </div>
                </div>

                <div class="row">

                    <div class="col-md-12 col-lg-5 col-xl-4 ui_animate" data-animate-delay=".3" style="overflow: hidden;">

                        @if(session('message'))
                            <div class="row">
                                <div class="col">
                                    <p><span class="text-success">{{ session('message') }}</span><br />
                                        If you need immediate assistance, please call us at <a href="tel:214-271-5595">214-271-5595</a>
                                    </p>
                                </div>
                            </div>
                        @endif

                        <form action="/contact-us/job-seekers" method="post" id="contact_us_form">

                            @csrf

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_first_name" class="form-label">First Name <sup>*</sup></label>
                                    <input type="text" name="contact_first_name" class="form-control" id="contact_first_name" value="{{ old('contact_first_name') }}" required>
                                    @error('contact_first_name')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_last_name" class="form-label">Last Name <sup>*</sup></label>
                                    <input type="text" name="contact_last_name" class="form-control" id="contact_last_name" value="{{ old('contact_last_name') }}" required>
                                    @error('contact_last_name')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <label for="contact_email" class="form-label">Email address <sup>*</sup></label>
                                    <input type="email" name="contact_email" class="form-control" id="contact_email" value="{{ old('contact_email') }}" required>
                                    @error('contact_email')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_phone" class="form-label">Phone #</label>
                                    <input type="tel" name="contact_phone" class="form-control" id="contact_phone" value="{{ old('contact_phone') }}">
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_call_back_time" class="form-label">Best Time To Call You</label>
                                    <select name="contact_call_back_time" class="form-select" aria-label="Call Back Time">
                                        <option {{ old('contact_call_back_time') == 'Daytime' ? "selected" : "" }} value="Daytime">Daytime</option>
                                        <option {{ old('contact_call_back_time') == 'Mornings' ? "selected" : "" }} value="Mornings">Mornings</option>
                                        <option {{ old('contact_call_back_time') == 'Afternoons' ? "selected" : "" }} value="Afternoons">Afternoons</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <label for="contact_message" class="form-label">Message</label>
                                    <textarea name="contact_message" class="form-control" id="contact_message" rows="3">{{ old('contact_message') }}</textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <button type="submit" id="form_submit" class="btn btn-yellow">Submit
                                        <span id="form_submit_spinner" style="display:none;" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                        <span class="visually-hidden">Loading...</span>
                                    </button>

                                    {{-- <button class="g-recaptcha btn btn-yellow"
                                    data-sitekey="6LdE5PciAAAAAGFlwFTrkwn8ehePF6bpaAg16hKt"
                                    data-callback='onSubmit'
                                    data-action='submit'>Submit</button> --}}

                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-12 col-lg-6 offset-lg-1 ui_animate" data-animate-delay=".4">
                        <img src="/images/job-seekers-1.jpg" alt="Job Seekers" class="img-fluid">
                    </div>
                </div>


                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->

    </section>

@endsection
