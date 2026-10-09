// QCP marketing site scripts (Phase 08b-i) — server-rendered Blade surface on
// qualitycleanplus.com (ADR-0023). Wires the navbar toggle (Bootstrap's collapse —
// the only Bootstrap plugin the site uses), the scroll-shrink navigation, the
// scroll-reveal animation for `.ui_animate` elements, reCAPTCHA on submit, the home
// page's testimonials slider, and the obfuscated "Contact Us" email helper.
// Everything is bundled here; nothing loads from a CDN, so the JS always matches
// the Bootstrap version the CSS is built from.

import 'bootstrap/js/dist/collapse';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// Shrink the fixed navigation once the page scrolls past the hero.
ScrollTrigger.create({
    start: 'top -80',
    end: 99999,
    toggleClass: { className: 'main-navigation--scrolled', targets: '.main-navigation' },
});

function animateFrom(elem, direction) {
    direction = direction || 1;
    let x = 0;
    let y = direction * 100;
    if (elem.classList.contains('gs_reveal_fromLeft')) {
        x = -100;
        y = 0;
    } else if (elem.classList.contains('gs_reveal_fromRight')) {
        x = 100;
        y = 0;
    }

    const delay = elem.getAttribute('data-animate-delay') || 0;

    elem.style.transform = 'translate(' + x + 'px, ' + y + 'px)';
    elem.style.opacity = '0';
    gsap.fromTo(
        elem,
        { x: x, y: y, autoAlpha: 0 },
        { duration: 1.25, delay: delay, x: 0, y: 0, autoAlpha: 1, ease: 'expo', overwrite: 'auto' },
    );
}

function hide(elem) {
    gsap.set(elem, { autoAlpha: 0 });
}

// `.ui_animate` starts hidden by CSS (only once html has the `js` class). Marking
// the page `ui-animate-ready` hands those elements to GSAP and switches off the
// stylesheet's fallback, which reveals them after 2s if this never runs.
document.addEventListener('DOMContentLoaded', function () {
    const elems = gsap.utils.toArray('.ui_animate');
    elems.forEach(hide); // ensure hidden before they scroll into view
    document.documentElement.classList.add('ui-animate-ready');

    elems.forEach(function (elem) {

        ScrollTrigger.create({
            trigger: elem,
            onEnter: function () { animateFrom(elem); },
            onEnterBack: function () { animateFrom(elem, -1); },
            onLeave: function () { hide(elem); },
        });
    });
});

// reCAPTCHA v3 (resources/views/site/elements/recaptcha.blade.php): fetch a token
// at submit time — tokens expire after two minutes, and the application form takes
// longer than that — then submit. If Google's script never loaded (blocked), submit
// anyway so the server answers with its message instead of the button doing nothing.
document.querySelectorAll('input[data-recaptcha-action]').forEach(function (input) {
    const form = input.form;

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const button = form.querySelector('[type="submit"]');
        if (button) {
            button.disabled = true;
            const spinner = button.querySelector('.spinner-border');
            if (spinner) spinner.style.display = '';
        }

        if (!window.grecaptcha) {
            form.submit();
            return;
        }

        window.grecaptcha.ready(function () {
            window.grecaptcha
                .execute(window.recaptchaSiteKey, { action: input.dataset.recaptchaAction })
                .then(
                    function (token) {
                        input.value = token;
                        form.submit();
                    },
                    function () { form.submit(); },
                );
        });
    });
});

// Testimonials slider, home page only: fetched as its own chunk when the page has one.
const testimonials = document.querySelector('.qc-swiper');
if (testimonials) {
    import('./testimonials').then(function (module) {
        module.default(testimonials);
    });
}

// Obfuscated email helper used by the footer/contact "Contact Us" links
// (href="javascript:uix_con_todo('d.aguilar')") to deter scrapers.
window.uix_con_todo = function (user) {
    window.location.href = 'mailto:' + user + '@qualitycleanplus.com';
};
