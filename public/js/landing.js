/*
 * Landing page motion (resources/views/pages/home.blade.php):
 * - the fixed boat backdrop darkens gradually as the page scrolls (--lp-dim);
 * - the pinned hero banner fades back as the first card covers it (--lp-hero-p);
 * - the header turns solid once the hero has been covered;
 * - [data-reveal] blocks fade up as they enter the viewport.
 * Without JavaScript the page is fully readable (nothing is hidden).
 * "Reduce motion" users get no reveal animation.
 */
(function () {
    'use strict';
    var root = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var header = document.querySelector('.lp-top');
    var hero = document.querySelector('.lp-hero');
    var firstCard = document.querySelector('.lp-band');

    root.classList.add('lp-js');

    // ---- scroll: backdrop dimming + solid header
    var ticking = false;
    function onScroll() {
        var y = window.scrollY || window.pageYOffset;
        var vh = window.innerHeight || 800;
        root.style.setProperty('--lp-dim', Math.min(y / (vh * 1.6), 1) * 0.55);
        // pinned banner recedes while the first card covers it
        if (hero && !reduce) {
            root.style.setProperty('--lp-hero-p', Math.min(y / Math.max(hero.offsetHeight, 1), 1).toFixed(3));
        }
        if (header) {
            // the hero is pinned (sticky), so measure the first card that covers it
            var solid = firstCard ? firstCard.getBoundingClientRect().top <= header.offsetHeight + 8 : y > 80;
            header.classList.toggle('is-solid', solid);
        }
        ticking = false;
    }
    window.addEventListener('scroll', function () {
        if (!ticking) { ticking = true; window.requestAnimationFrame(onScroll); }
    }, { passive: true });
    window.addEventListener('resize', onScroll);
    onScroll();

    // ---- reveal on scroll
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
    if (reduce || !('IntersectionObserver' in window)) {
        items.forEach(function (el) { el.classList.add('is-in'); });
        return;
    }
    // anything already on screen shows at once (no flash on load)
    items.forEach(function (el) {
        var r = el.getBoundingClientRect();
        if (r.top < window.innerHeight && r.bottom > 0) { el.classList.add('is-in'); }
    });
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-in');
                io.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    items.forEach(function (el) { if (!el.classList.contains('is-in')) { io.observe(el); } });
})();
