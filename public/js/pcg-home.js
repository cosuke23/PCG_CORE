/*
 * PCG HRMIS landing page (resources/views/pages/pcg.blade.php): mobile menu,
 * header shadow once scrolled, and the entrance motion (css/pcg-home.css):
 * - [data-ph-words]: the text is split into words that rise one after another;
 * - [data-ph-stagger]: its children arrive one after another;
 * - [data-ph-reveal]: the block (and what is inside) plays when it scrolls in;
 *   anything already on screen plays at once.
 * Without JavaScript, or with "reduce motion", everything is simply shown.
 */
(function () {
    'use strict';
    var root = document.documentElement;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var motion = !reduce && 'IntersectionObserver' in window;
    if (motion) root.classList.add('ph-js');

    // split the text nodes of el into <span class="ph-w"> words (text only, never HTML)
    function splitWords(el) {
        var n = 0;
        (function walk(node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                if (child.nodeType === 3) {
                    var parts = child.textContent.split(/(\s+)/);
                    var frag = document.createDocumentFragment();
                    parts.forEach(function (part) {
                        if (!part) return;
                        if (/^\s+$/.test(part)) { frag.appendChild(document.createTextNode(part)); return; }
                        var w = document.createElement('span');
                        w.className = 'ph-w';
                        w.textContent = part;
                        w.style.setProperty('--i', String(n++));
                        frag.appendChild(w);
                    });
                    child.parentNode.replaceChild(frag, child);
                } else if (child.nodeType === 1) {
                    walk(child);
                }
            });
        })(el);
    }

    document.addEventListener('DOMContentLoaded', function () {
        var header = document.querySelector('[data-ph-header]');
        var button = document.querySelector('[data-ph-menu]');
        var menu = document.getElementById('ph-mobile-nav');

        if (button && menu) {
            menu.hidden = true; // without JavaScript the list simply stays open
            var label = button.querySelector('span');
            var setOpen = function (open) {
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                menu.hidden = !open;
                label.textContent = open ? 'Close' : 'Menu';
            };
            button.addEventListener('click', function () { setOpen(button.getAttribute('aria-expanded') !== 'true'); });
            menu.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !menu.hidden) { setOpen(false); button.focus(); } });
        }

        if (header) {
            var onScroll = function () {
                if ((window.scrollY || 0) > 40) header.setAttribute('data-scrolled', ''); else header.removeAttribute('data-scrolled');
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        }

        if (!motion) return;

        document.querySelectorAll('[data-ph-words]').forEach(splitWords);
        document.querySelectorAll('[data-ph-stagger]').forEach(function (list) {
            Array.prototype.forEach.call(list.children, function (child, i) { child.style.setProperty('--i', String(i)); });
        });

        var items = Array.prototype.slice.call(document.querySelectorAll('[data-ph-reveal]'));
        var show = function (el) {
            el.classList.add('is-in');
            var here = el.querySelector('.ph-org__cmd--here');
            if (here) setTimeout(function () { here.classList.add('is-pulse'); }, 900);
        };
        // let the hidden state paint once, so the entrance plays on load too
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                var io = new IntersectionObserver(function (entries) {
                    entries.forEach(function (en) { if (en.isIntersecting) { show(en.target); io.unobserve(en.target); } });
                }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
                items.forEach(function (el) {
                    var r = el.getBoundingClientRect();
                    if (r.top < window.innerHeight && r.bottom > 0) show(el); else io.observe(el);
                });
            });
        });
    });
})();
