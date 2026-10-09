// Home page testimonials slider (site/components/testimonials.blade.php). Loaded
// by app.js only on pages that have one, as its own chunk with Swiper's CSS, so the
// other pages don't download it.

import Swiper from 'swiper';
import { Autoplay, Navigation, Pagination } from 'swiper/modules';
import 'swiper/css';
import 'swiper/css/navigation';
import 'swiper/css/pagination';

export default function initTestimonials(el) {
    return new Swiper(el, {
        modules: [Autoplay, Navigation, Pagination],
        slidesPerView: 1,
        spaceBetween: 10,
        breakpoints: {
            768: { slidesPerView: 2, spaceBetween: 10 },
            992: { slidesPerView: 3, spaceBetween: 30 },
        },
        autoplay: { delay: 7000 },
        pagination: { el: el.querySelector('.swiper-pagination') },
        navigation: {
            nextEl: el.querySelector('.swiper-button-next'),
            prevEl: el.querySelector('.swiper-button-prev'),
        },
    });
}
