/*
 * PCG HRMIS landing page (resources/views/pages/pcg.blade.php): mobile menu,
 * header shadow once scrolled, reveal-on-scroll. Without JavaScript the page is
 * fully readable and the mobile menu stays reachable through the section links.
 */
(function () {
    'use strict';
    var root = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!reduce && 'IntersectionObserver' in window) root.classList.add('ph-js');

    document.addEventListener('DOMContentLoaded', function () {
        var header = document.querySelector('[data-ph-header]');
        var button = document.querySelector('[data-ph-menu]');
        var menu = document.getElementById('ph-mobile-nav');

        if (button && menu) {
            menu.hidden = true; // without JavaScript the list simply stays open
            button.addEventListener('click', function () {
                var open = button.getAttribute('aria-expanded') !== 'true';
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                menu.hidden = !open;
                button.querySelector('span').textContent = open ? 'Close' : 'Menu';
            });
            menu.addEventListener('click', function (e) {
                if (e.target.closest('a')) { button.setAttribute('aria-expanded', 'false'); menu.hidden = true; button.querySelector('span').textContent = 'Menu'; }
            });
        }

        if (header) {
            var onScroll = function () {
                if ((window.scrollY || 0) > 40) header.setAttribute('data-scrolled', ''); else header.removeAttribute('data-scrolled');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        if (root.classList.contains('ph-js')) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
            }, { rootMargin: '0px 0px -8% 0px' });
            document.querySelectorAll('[data-ph-reveal]').forEach(function (el) { io.observe(el); });
        }
    });
})();
