<div class="row">
    <div class="col section-text text-center ui_animate"  data-animate-delay=".2">
        <h2 title="Our Customers">Testimonials</h2>
        <p>See what our staffing and customers have to say.</p>
    </div>
</div>
<div class="row">
    <div class="col">

        <div class="swiper qc-swiper ui_animate"  data-animate-delay=".4">
            <div class="swiper-wrapper">

            @if(count($testimonials))

                @foreach($testimonials as $testimonial)

                    <div class="swiper-slide">

                        <div class="qc-card">
                            <div class="qc-image">
                                @if($testimonial->photo != '')
                                <img src="{{ $testimonial->photo }}" class="img-fluid">
                                @else
                                <img src="/images/ph-profile.png" class="img-fluid">
                                @endif
                            </div>
                            <div class="qc-source">
                                <div class="qc-media"><img src="/images/icons/social-media-{{ strtolower($testimonial->social_media) }}.svg" width="30"></div>
                                <div class="qc-name">{{ $testimonial->name }}</div>
                                <div></div>
                            </div>
                            <div class="qc-stars">
                                @for($i = 0; $i < 5; $i++)
                                    <span class="icon-star-solid @if($testimonial->rating > $i) checked @endif"></span>
                                @endfor
                            </div>
                            <div class="qc-quote icon-quote-left-solid"></div>
                            <div class="qc-text">
                                {{ $testimonial->quote }}
                            </div>
                        </div>

                    </div>

                @endforeach

            @endif

            </div>  <!-- .swiper-wrapper -->
            <div class="swiper-button-next icon-chevron-right-solid"></div>
            <div class="swiper-button-prev icon-chevron-left-solid"></div>
            <div class="swiper-pagination"></div>
        </div> <!-- .swiper -->

    </div>
</div>

