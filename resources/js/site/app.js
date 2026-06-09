// QCP marketing site scripts (Phase 08b-i) — server-rendered Blade surface on
// qualitycleanplus.com (ADR-0023). GSAP, ScrollTrigger, jQuery, and Bootstrap's
// JS bundle load via CDN in the website layout (matching the legacy site); this
// module wires the scroll-shrink navigation, the scroll-reveal animation for
// `.ui_animate` elements (which start at opacity:0 in the stylesheet), and the
// obfuscated "Contact Us" email helper.

/* global gsap, ScrollTrigger */

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

document.addEventListener('DOMContentLoaded', function () {
    gsap.registerPlugin(ScrollTrigger);

    gsap.utils.toArray('.ui_animate').forEach(function (elem) {
        hide(elem); // ensure hidden before it scrolls into view

        ScrollTrigger.create({
            trigger: elem,
            onEnter: function () { animateFrom(elem); },
            onEnterBack: function () { animateFrom(elem, -1); },
            onLeave: function () { hide(elem); },
        });
    });
});

// Obfuscated email helper used by the footer/contact "Contact Us" links
// (href="javascript:uix_con_todo('d.aguilar')") to deter scrapers.
window.uix_con_todo = function (user) {
    window.location.href = 'mailto:' + user + '@qualitycleanplus.com';
};
