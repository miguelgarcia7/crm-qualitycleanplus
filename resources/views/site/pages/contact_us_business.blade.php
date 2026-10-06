@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/business.meta.title'),
    'meta_description' =>   __('site/business.meta.description'),
    ])

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">
            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/business.title') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_services" class="wrap content">
            <div class="container our_services">
                <div class="row">
                    <div class="col-md-10 section-text ui_animate" data-animate-delay=".2">
                        <h2 title="{{ __('site/business.heading') }}">{{ __('site/business.heading') }}</h2>

                    </div>
                </div>

                <div class="row">

                    <div class="col-md-12 col-lg-5 ui_animate" data-animate-delay=".3">

                        @if(session('message'))
                            <div class="row">
                                <div class="col">
                                    <p><span class="text-success">{{ session('message') }}</span><br />
                                        {!! __('site/forms.immediate_help', ['phone' => '<a href="tel:214-271-5595">214-271-5595</a>']) !!}
                                    </p>
                                </div>
                            </div>
                        @endif

                        <form action="{{ $site->route('contact.business.store') }}" method="post" id="contact_us_form">

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
                                    <label for="contact_phone" class="form-label">{{ __('site/business.phone') }} <sup>*</sup></label>
                                    <input type="tel" name="contact_phone" class="form-control" id="contact_phone" value="{{ old('contact_phone') }}" required>
                                    @error('contact_phone')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_company" class="form-label">{{ __('site/business.company') }}</label>
                                    <input type="text" name="contact_company" class="form-control" id="contact_company" value="{{ old('contact_company') }}">
                                    @error('contact_company')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_address" class="form-label">{{ __('site/business.address') }}</label>
                                    <input type="text" name="contact_address" class="form-control" id="contact_address" value="{{ old('contact_address') }}">
                                    @error('contact_address')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_city" class="form-label">{{ __('site/business.city') }}</label>
                                    <input type="text" name="contact_city" class="form-control" id="contact_city" value="{{ old('contact_city') }}">
                                    @error('contact_city')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_state" class="form-label">{{ __('site/business.state') }}</label>
                                    <input type="text" name="contact_state" class="form-control" id="contact_state" value="{{ old('contact_state') }}">
                                    @error('contact_state')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="contact_zip" class="form-label">{{ __('site/business.zip') }}</label>
                                    <input type="text" name="contact_zip" class="form-control" id="contact_zip" value="{{ old('contact_zip') }}">
                                    @error('contact_zip')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    <label for="contact_inquiry_type" class="form-label">{{ __('site/business.inquiry_type') }}</label>
                                    <select name="contact_inquiry_type" class="form-select" aria-label="{{ __('site/business.inquiry_type') }}">
                                        <option {{ old('contact_inquiry_type') == 'Looking to Hire for Team' ? "selected" : "" }} value="Looking to Hire for Team">{{ __('site/business.inquiry_types.hire_team') }}</option>
                                        <option {{ old('contact_inquiry_type') == 'Carpet Cleaning' ? "selected" : "" }} value="Carpet Cleaning">{{ __('site/business.inquiry_types.carpet_cleaning') }}</option>
                                        <option {{ old('contact_inquiry_type') == 'Renovations' ? "selected" : "" }} value="Renovations">{{ __('site/business.inquiry_types.renovations') }}</option>
                                        <option {{ old('contact_inquiry_type') == 'Looking for a Business Solution' ? "selected" : "" }} value="Looking for a Business Solution">{{ __('site/business.inquiry_types.business_solution') }}</option>
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
                        <img src="/images/business-inquiries-1.jpg" alt="{{ __('site/business.image_alt') }}" class="img-fluid">
                    </div>
                </div>

                @include('site.components/cta_contact')

            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->

    </section>

@endsection

