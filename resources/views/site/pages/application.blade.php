@extends('site.layouts.website')

@include('site.elements.meta_data',[
    'meta_title' =>         __('site/application.meta.title'),
    'meta_description' =>   __('site/application.meta.description'),
    'meta_image' => 'https://www.qualitycleanplus.com/images/social-media-website-work.png',
    ])

@php
    $state_list = [
      'AL'=>"Alabama",
      'AK'=>"Alaska",
      'AZ'=>"Arizona",
      'AR'=>"Arkansas",
      'CA'=>"California",
      'CO'=>"Colorado",
      'CT'=>"Connecticut",
      'DE'=>"Delaware",
      'DC'=>"District Of Columbia",
      'FL'=>"Florida",
      'GA'=>"Georgia",
      'HI'=>"Hawaii",
      'ID'=>"Idaho",
      'IL'=>"Illinois",
      'IN'=>"Indiana",
      'IA'=>"Iowa",
      'KS'=>"Kansas",
      'KY'=>"Kentucky",
      'LA'=>"Louisiana",
      'ME'=>"Maine",
      'MD'=>"Maryland",
      'MA'=>"Massachusetts",
      'MI'=>"Michigan",
      'MN'=>"Minnesota",
      'MS'=>"Mississippi",
      'MO'=>"Missouri",
      'MT'=>"Montana",
      'NE'=>"Nebraska",
      'NV'=>"Nevada",
      'NH'=>"New Hampshire",
      'NJ'=>"New Jersey",
      'NM'=>"New Mexico",
      'NY'=>"New York",
      'NC'=>"North Carolina",
      'ND'=>"North Dakota",
      'OH'=>"Ohio",
      'OK'=>"Oklahoma",
      'OR'=>"Oregon",
      'PA'=>"Pennsylvania",
      'RI'=>"Rhode Island",
      'SC'=>"South Carolina",
      'SD'=>"South Dakota",
      'TN'=>"Tennessee",
      'TX'=>"Texas",
      'UT'=>"Utah",
      'VT'=>"Vermont",
      'VA'=>"Virginia",
      'WA'=>"Washington",
      'WV'=>"West Virginia",
      'WI'=>"Wisconsin",
      'WY'=>"Wyoming"
    ];
@endphp

@section('css_after')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
{{--    @vite(['resources/assets/vendor/libs/flatpickr/flatpickr.scss'])--}}

@endsection

@section('content')

    <section id="sub">

        <div class="header-wrap" style="overflow: hidden;">

            <div class="sub-hero container">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="hero-intro">
                            <h1 class="ui_animate">{{ __('site/application.heading') }}</h1>
                        </div>
                    </div>
                </div>
            </div>

        </div> {{-- /.header-wrap --}}


        <div id="our_application" class="wrap content">

            <div class="container applicaton">
                <div class="row">
                    <div class="col-md-7 section-text ui_animate" data-animate-delay=".2">

                        <form action="{{ $site->route('application.store') }}" method="post">
                            @csrf

                            @if(!empty($job))
                            <input type="hidden" name="job_id" value="{{ $job->id }}">
                            <input type="hidden" name="position" value="{{ $job->title }}">
                            @endif

                            <input type="hidden" name="application_date" value="{{ date('Y-m-d') }}">
                            <input type="hidden" name="status" value="1">


                            <h3>{{ __('site/application.employment.title') }}</h3>
                            <p>{{ __('site/application.employment.confidential') }}</p>
                            <div class="row">
                                <div class="col-sm-6 mb-3">
                                    <label for="first_name" class="form-label">{{ __('site/forms.first_name') }} <sup>*</sup></label>
                                    <input type="text" name="first_name" class="form-control" id="first_name" value="{{ old('first_name') }}" required>
                                    @error('first_name')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-sm-6 mb-3">
                                    <label for="last_name" class="form-label">{{ __('site/forms.last_name') }} <sup>*</sup></label>
                                    <input type="text" name="last_name" class="form-control" id="last_name" value="{{ old('last_name') }}" required>
                                    @error('last_name')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                              <div class="col-lg-6 mb-4">
                                <label class="form-label" for="email">{{ __('site/forms.email') }}</label>
                                <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" placeholder="{{ __('site/application.employment.email_placeholder') }}">
                                  @error('email')
                                      <span class="invalid-feedback" role="alert">
                                          <strong>{{ $message }}</strong>
                                      </span>
                                  @enderror
                              </div>
                              <div class="col-lg-6 mb-4">
                                <label class="form-label" for="phone">{{ __('site/forms.phone') }} <sup>*</sup></label>

                                <input type="text" class="phone-number-mask form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" placeholder="999-999-9999" required>
                                  @error('phone')
                                      <span class="invalid-feedback" role="alert">
                                          <strong>{{ $message }}</strong>
                                      </span>
                                  @enderror
                              </div>
                            </div>

                            <div class="row">
                                <div class="col-md-8 mb-4">
                                    <label class="form-label" for="address">{{ __('site/application.employment.address') }} <sup>*</sup></label>
                                    <input type="text" class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address') }}" placeholder="{{ __('site/application.employment.address') }}" required>
                                    @error('address')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="apartment_number">{{ __('site/application.employment.apartment') }}</label>
                                    <input type="text" class="form-control @error('apartment_number') is-invalid @enderror" id="apartment_number" name="apartment_number" value="{{ old('apartment_number') }}" placeholder="123">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-4">
                                    <label class="form-label" for="city">{{ __('site/application.employment.city') }} <sup>*</sup></label>
                                    <input type="text" class="form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city') }}" placeholder="{{ __('site/application.employment.city') }}" required>
                                    @error('city')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                              <div class="col-md-4 mb-4">
                                <label class="form-label" for="state">{{ __('site/application.employment.state') }} <sup>*</sup></label>
                                <select class="form-select @error('state') is-invalid @enderror" id="state" name="state" required>
                                  <option value="" @if (blank(old('state'))) selected @endif>{{ __('site/application.employment.state_placeholder') }}</option>
                                  @foreach ($state_list as $key => $value)
                                      <option value="{{ $key }}"
                                      @if ($key == old('state'))
                                          selected="selected"
                                      @endif
                                      >{{ $value }}</option>
                                  @endforeach
                                </select>
                                  @error('state')
                                      <span class="invalid-feedback" role="alert">
                                          <strong>{{ $message }}</strong>
                                      </span>
                                  @enderror
                              </div>
                              <div class="col-md-4 mb-4">
                                <label class="form-label" for="zip">{{ __('site/application.employment.zip') }} <sup>*</sup></label>
                                <input type="text" class="form-control @error('zip') is-invalid @enderror" id="zip" name="zip" value="{{ old('zip') }}" placeholder="{{ __('site/application.employment.zip') }}" required>
                                  @error('zip')
                                      <span class="invalid-feedback" role="alert">
                                          <strong>{{ $message }}</strong>
                                      </span>
                                  @enderror
                              </div>
                            </div>

                            <div class="p-3 mb-4" style="background-color: #f7f7f7;">

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="position" class="form-label">{{ __('site/application.position.position') }} <sup>*</sup></label>
                                    @if(!empty($job))
                                    <input type="text" name="position" class="form-control" id="position" value="{{ old('position', $job->title) }}" required>
                                    @else
                                    <input type="text" name="position" class="form-control" id="position" value="{{ old('position') }}" required>
                                    @endif
                                    @error('position')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="desired_salary" class="form-label">{{ __('site/application.position.desired_salary') }} <sup>*</sup></label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="basic-addon1">$</span>
                                        <input type="text" name="desired_salary" class="form-control" id="desired_salary" value="{{ old('desired_salary') }}" required>
                                        @error('desired_salary')
                                            <div class="form-text text-danger">{{ $message }}</div>
                                        @enderror
                                    </div>

                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label" for="start_date">{{ __('site/application.position.start_date') }} <sup>*</sup></label>
                                    <input type="text" class="js-flatpickr form-control @error('start_date') is-invalid @enderror" id="start_date" name="start_date" placeholder="{{ __('site/application.position.start_date_placeholder') }}" data-alt-input="true" data-date-format="Y-m-d" data-alt-format="{{ __('site/application.position.date_picker_format') }}" value="{{ old('start_date') }}" required>
                                    @error('start_date')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ __('site/application.errors.start_date') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="form-label" for="dob">{{ __('site/application.position.dob') }} <sup>*</sup></label>
                                    <input type="text" class="js-flatpickr form-control @error('dob') is-invalid @enderror" id="dob" name="dob" placeholder="{{ __('site/application.position.dob') }}" data-alt-input="true" data-date-format="Y-m-d" data-alt-format="{{ __('site/application.position.date_picker_format') }}" value="{{ old('dob') }}" required>
                                    @error('dob')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ __('site/application.errors.dob') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <hr />
                            <div class="row">
                                <div class="col-md-12 ">
                                    <label for="transportation" class="form-label">{{ __('site/application.position.transportation') }} <sup>*</sup></label>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="transportation" value="1" required id="transportation1"
                                      @if( old('transportation') === '1' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="transportation1">
                                        {{ __('site/forms.yes') }}
                                      </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="transportation" value="0" id="transportation2"
                                      @if( old('transportation') === '0' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="transportation2">
                                        {{ __('site/forms.no') }}
                                      </label>
                                    </div>
                                    @error('transportation')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ __('site/application.errors.selection') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <hr />
                            <div class="row">
                                <div class="col mb-4">
                                    <label for="work_at_qcp" class="form-label">{{ __('site/application.position.work_at_qcp') }} <sup>*</sup></label><br />
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="work_at_qcp" value="1" required id="work_at_qcp1"
                                      @if( old('work_at_qcp') === '1' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="work_at_qcp1">
                                        {{ __('site/forms.yes') }}
                                      </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="work_at_qcp" value="0" id="work_at_qcp2"
                                      @if( old('work_at_qcp') === '0' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="work_at_qcp2">
                                        {{ __('site/forms.no') }}
                                      </label>
                                    </div>
                                    @error('work_at_qcp')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ __('site/application.errors.selection') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col">
                                    <label class="form-label" for="work_at_qcp_explain">{{ __('site/application.position.work_at_qcp_explain') }}</label>
                                    <input type="text" class="form-control @error('work_at_qcp_explain') is-invalid @enderror" id="work_at_qcp_explain" name="work_at_qcp_explain" value="{{ old('work_at_qcp_explain') }}" placeholder="{{ __('site/application.position.work_at_qcp_explain_placeholder') }}">
                                </div>
                            </div>

                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="usa_citizen" class="form-label">{{ __('site/application.eligibility.usa_citizen') }} <sup>*</sup></label><br />
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="usa_citizen" value="1" required id="usa_citizen1"
                                      @if( old('usa_citizen') === '1' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="usa_citizen1">
                                        {{ __('site/forms.yes') }}
                                      </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="usa_citizen" value="0" id="usa_citizen2"
                                      @if( old('usa_citizen') === '0' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="usa_citizen2">
                                        {{ __('site/forms.no') }}
                                      </label>
                                    </div>
                                    @error('usa_citizen')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ __('site/application.errors.selection') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="eligible_to_work" class="form-label">{{ __('site/application.eligibility.eligible_to_work') }}</label>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="eligible_to_work" value="1" id="eligible_to_work1"
                                      @if( old('eligible_to_work') === '1' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="eligible_to_work1">
                                        {{ __('site/forms.yes') }}
                                      </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="eligible_to_work" value="0" id="eligible_to_work2"
                                      @if( old('eligible_to_work') === '0' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="eligible_to_work2">
                                        {{ __('site/forms.no') }}
                                      </label>
                                    </div>
                                    @error('eligible_to_work')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ __('site/application.errors.selection') }}</strong>
                                        </span>
                                    @enderror
                                </div>

                            </div>
                            <hr />
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="another_staff_agency" class="form-label">{{ __('site/application.eligibility.another_staff_agency') }} <sup>*</sup></label>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="another_staff_agency" value="1" required id="another_staff_agency1"
                                      @if( old('another_staff_agency') === '1' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="another_staff_agency1">
                                        {{ __('site/forms.yes') }}
                                      </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="another_staff_agency" value="0" id="another_staff_agency2"
                                      @if( old('another_staff_agency') === '0' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="another_staff_agency2">
                                        {{ __('site/forms.no') }}
                                      </label>
                                    </div>
                                    @error('another_staff_agency')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ __('site/application.errors.selection') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="non_complete" class="form-label">{!! __('site/application.eligibility.non_compete', ['term' => '<span style="white-space: nowrap;">'.e(__('site/application.eligibility.non_compete_term')).'</span>']) !!}</label>
                                    <input type="text" name="non_complete" class="form-control" id="non_complete" value="{{ old('non_complete') }}">
                                    @error('non_complete')
                                        <div class="form-text text-danger">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <hr />

                            <div class="row">
                                <div class="col mb-4">
                                    <label for="convicted_felon" class="form-label">{{ __('site/application.eligibility.convicted_felon') }} <sup>*</sup></label><br />
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="convicted_felon" value="1" required id="convicted_felon1"
                                      @if( old('convicted_felon') === '1' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="convicted_felon1">
                                        {{ __('site/forms.yes') }}
                                      </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                      <input class="form-check-input" type="radio" name="convicted_felon" value="0" id="convicted_felon2"
                                      @if( old('convicted_felon') === '0' )
                                        checked
                                      @endif
                                      >
                                      <label class="form-check-label" for="convicted_felon2">
                                        {{ __('site/forms.no') }}
                                      </label>
                                    </div>
                                    @error('convicted_felon')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ __('site/application.errors.selection') }}</strong>
                                        </span>
                                    @enderror
                                </div>
                                <div class="col-md-12 mb-4">
                                    <label class="form-label" for="felony_conviction">{{ __('site/application.eligibility.felony_conviction') }}</label>
                                    <textarea class="form-control @error('felony_conviction') is-invalid @enderror" id="felony_conviction" rows="3" name="felony_conviction" placeholder="{{ __('site/application.eligibility.felony_conviction_placeholder') }}">{{ old('felony_conviction') }}</textarea>
                                </div>
                            </div>
                            <br />
                            <div class="row">
                                <h3>{{ __('site/application.emergency.title') }}</h3>
                                <div class="col-md-12 mb-4">
                                    <label for="full_name" class="form-label">{{ __('site/application.emergency.full_name') }}</label>
                                    <input type="text" name="full_name" class="form-control" id="full_name" value="{{ old('full_name') }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="emergency_phone" class="form-label">{{ __('site/forms.phone') }}</label>
                                    <input type="text" name="emergency_phone" class="form-control" id="emergency_phone" value="{{ old('emergency_phone') }}">
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="relationship" class="form-label">{{ __('site/application.emergency.relationship') }}</label>
                                    <input type="text" name="relationship" class="form-control" id="relationship" value="{{ old('relationship') }}">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <label for="full_address" class="form-label">{{ __('site/application.emergency.address') }}</label>
                                    <input type="text" name="full_address" class="form-control" id="full_address" value="{{ old('full_address') }}">
                                </div>
                            </div>
                            <hr />
                            <div class="row">
                                <div class="col-md-12 mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="acknowledgement" value="1" id="acknowledgement" required>
                                        <label class="form-check-label" for="acknowledgement">
                                            {{ __('site/application.acknowledgement') }}
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-sm-12 mb-3">
                                    @include('site.elements.recaptcha', ['action' => 'application'])

                                    <button type="submit" class="btn btn-yellow">{{ __('site/application.submit') }}</button>
                                    @include('site.elements.recaptcha_notice')
                                </div>
                            </div>

                        </form>

                    </div>
                    <div class="col-md-4 offset-md-1 section-text ui_animate" data-animate-delay=".4">
                        <p><b>{{ __('site/application.sidebar.application_date') }}</b> {{ now()->translatedFormat(__('site/application.sidebar.date_format')) }}</p>
                        @if(!empty($job))
                        <p>
                            <b>{{ __('site/application.sidebar.position') }} {{ $job->title }}</b><br />
                            <b>{{ __('site/application.sidebar.location') }}</b> {{ $job->locationName() ?? __('site/application.sidebar.various_locations') }}<br />
                            @if($job->hour_start && $job->hour_end)
                            <b>{{ __('site/application.sidebar.hours') }}</b> {{ date("g:i a", strtotime($job->hour_start)) }} - {{ date("g:i a", strtotime($job->hour_end)) }}
                            @endif
                        </p>
                        @endif
                    </div>
                </div>


            </div> <!-- /.container .our_services -->

        </div> <!-- /#our_services .wrap  -->



    </section>

@endsection

@section('js_after')



<!-- Page JS Plugins -->
{{--<script src="/cms/js/plugins/flatpickr.min.js"></script>--}}
{{--@vite(['resources/assets/vendor/libs/moment/moment.js'])--}}
{{--@vite(['resources/assets/vendor/libs/flatpickr/flatpickr.js'])--}}
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
@if ($site->isSpanish())
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<script>flatpickr('.js-flatpickr', { locale: 'es' });</script>
@else
<script>flatpickr('.js-flatpickr');</script>
@endif

@endsection
