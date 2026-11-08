/* Admin console: light / dark theme toggle, remembered per browser. */
(function () {
    'use strict';
    var root = document.documentElement;
    function current() { return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark'; }
    function sync() {
        document.querySelectorAll('[data-pcg-theme-toggle]').forEach(function (b) {
            b.setAttribute('aria-pressed', current() === 'light' ? 'true' : 'false');
            b.setAttribute('aria-label', current() === 'dark' ? 'Switch to light theme' : 'Switch to dark theme');
        });
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pcg-theme-toggle]');
        if (!b) return;
        var next = current() === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('pcgTheme', next); } catch (err) { /* storage off: applies to this page only */ }
        sync();
        document.dispatchEvent(new CustomEvent('pcg:theme', { detail: next }));
    });
    document.addEventListener('DOMContentLoaded', sync);
})();
