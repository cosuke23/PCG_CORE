/* Submitted SOI (App\Support\SoiLock): every step is read-only, blank fields included.
   Looking through the steps still works (step list, Back, Next); typing, choosing,
   adding or removing shows "Unable to edit. Contact your Adjutant or HR Unit Admin."
   The server refuses every save anyway (EnsureSoiOpen). */
(function () {
    'use strict';
    var root = document.getElementById('soi-root');
    if (!root || root.getAttribute('data-soi-locked') !== '1') return;
    var message = root.getAttribute('data-soi-message');

    var shown = 0;
    function refuse(e) {
        if (e) { e.preventDefault(); e.stopImmediatePropagation(); }
        if (Date.now() - shown < 800) return; // one prompt per attempt
        shown = Date.now();
        if (window.Swal) {
            window.Swal.fire({ icon: 'info', title: 'Unable to edit', text: message, confirmButtonText: 'OK' });
        } else {
            alert(message);
        }
    }

    // make every field read-only (Alpine re-renders lists: repeat on changes)
    function lock(scope) {
        scope.querySelectorAll('.soi-form input, .soi-form textarea').forEach(function (el) {
            if (el.type === 'hidden') return;
            el.readOnly = true;
            el.setAttribute('aria-readonly', 'true');
        });
        scope.querySelectorAll('.soi-form select').forEach(function (el) { el.setAttribute('aria-readonly', 'true'); el.tabIndex = -1; });
    }
    lock(root);
    new MutationObserver(function () { lock(root); }).observe(root, { childList: true, subtree: true });

    function data() { return window.Alpine ? window.Alpine.$data(root) : null; }
    function isNav(btn) {
        return btn.closest('.soi-stepper') || (btn.closest('.soi-actions') && (btn.classList.contains('pcg-btn--secondary') || btn.classList.contains('pcg-btn--cta')));
    }

    root.addEventListener('click', function (e) {
        var btn = e.target.closest('button, [role="button"]');
        var field = e.target.closest('.soi-form input, .soi-form select, .soi-form textarea, .soi-form label');
        if (btn && isNav(btn)) {
            // "Next step" would save: in read-only mode it only moves on
            if (btn.classList.contains('pcg-btn--cta')) {
                e.preventDefault(); e.stopImmediatePropagation();
                var d = data();
                if (d && d.step < 6) { d.step++; window.scrollTo({ top: 0, behavior: 'smooth' }); }
            }
            return;
        }
        if (btn && btn.closest('.soi-form')) return refuse(e);   // add, remove, …
        if (field && (field.matches('select, input[type=checkbox], input[type=radio], input[type=date], label') || field.readOnly)) {
            if (field.matches('select, input[type=checkbox], input[type=radio], input[type=date]')) return refuse(e);
            if (field.matches('input, textarea')) refuse(null);
        }
    }, true);

    // keyboard: moving around and copying is fine; anything that would change a field is refused
    var NAV = ['Tab', 'Shift', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown', 'Control', 'Meta', 'Alt'];
    root.addEventListener('keydown', function (e) {
        var t = e.target;
        if (!t.closest || !t.closest('.soi-form') || !t.matches('input, select, textarea')) return;
        if (e.key === 'Tab') return;
        var copying = (e.ctrlKey || e.metaKey) && ['c', 'a'].indexOf(String(e.key).toLowerCase()) !== -1;
        if (!t.matches('select') && (NAV.indexOf(e.key) !== -1 || copying)) return;
        refuse(e);
    }, true);
    root.addEventListener('mousedown', function (e) {
        if (e.target.matches && e.target.matches('.soi-form select')) refuse(e); // stop the option list opening
    }, true);
    root.addEventListener('change', function (e) {
        if (e.target.closest && e.target.closest('.soi-form')) refuse(e);
    }, true);
})();
