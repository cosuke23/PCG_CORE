/*
 * PCG_CORE UI behaviours (no dependencies). Loaded with `defer`.
 *
 *  - <button data-pcg-toggle-password="inputId">  show / hide a password field
 *  - <form data-pcg-busy>                          disable submit while sending
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-pcg-toggle-password]').forEach(function (button) {
        var input = document.getElementById(button.getAttribute('data-pcg-toggle-password'));
        if (!input) return;

        button.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.textContent = show ? 'Hide' : 'Show';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        });
    });

    document.querySelectorAll('form[data-pcg-busy]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var submit = form.querySelector('[type="submit"]');
            if (!submit) return;
            submit.setAttribute('aria-busy', 'true');
            submit.disabled = true;
            submit.dataset.label = submit.textContent;
            submit.textContent = submit.getAttribute('data-busy-label') || 'Signing in…';
        });
    });

    // App shell: open/close the sidebar on small screens.
    var shell = document.querySelector('.pcg-console');
    if (shell) {
        var toggle = shell.querySelector('[data-pcg-nav-toggle]');
        var setOpen = function (open) {
            shell.setAttribute('data-nav-open', open ? 'true' : 'false');
            if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        };
        if (toggle) toggle.addEventListener('click', function () {
            setOpen(shell.getAttribute('data-nav-open') !== 'true');
        });
        shell.querySelectorAll('[data-pcg-nav-close]').forEach(function (el) {
            el.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setOpen(false);
        });
    }

    // QR code for authenticator setup (qrcode.js is loaded by that page only).
    window.addEventListener('load', function () {
        document.querySelectorAll('[data-pcg-qr]').forEach(function (el) {
            if (typeof window.QRCode === 'undefined') return;
            new window.QRCode(el, { text: el.getAttribute('data-pcg-qr'), width: 196, height: 196, correctLevel: window.QRCode.CorrectLevel.M });
        });
    });

    /*
     * Event delegation for markup that used inline handlers (onclick="..."),
     * which the Content-Security-Policy blocks:
     *   data-on-click="functionName" data-args='["a", 1, "$this"]'
     * calls window.functionName("a", 1, element). "$this" is the element.
     * Names starting with "pcg." are the built-ins below. Never evaluates
     * code: only named global functions are called.
     */
    var builtins = {
        'pcg.print': function () { window.print(); },
        'pcg.close': function () { window.close(); },
        'pcg.show': function (el, sel) { document.querySelector(sel).classList.remove('hidden'); },
        'pcg.hide': function (el, sel) { document.querySelector(sel).classList.add('hidden'); },
        'pcg.submit-form': function (el, sel) { (sel ? document.querySelector(sel) : el.form).submit(); },
        'pcg.open-src': function (el) { window.open(el.currentSrc || el.src, '_blank', 'noopener'); },
        'pcg.img-fallback': function (el) {
            el.style.display = 'none';
            if (el.nextElementSibling) el.nextElementSibling.style.display = 'block';
        },
        'pcg.file-types': function (el) {
            var types = Array.prototype.slice.call(arguments, 1);
            var file = el.files && el.files[0];
            if (file && types.indexOf(file.type) === -1) {
                alert(types.length === 1 && types[0] === 'application/pdf' ? 'PDF files only' : 'Only JPG and PNG images are allowed');
                el.value = '';
            }
        }
    };

    ['click', 'dblclick', 'change', 'error'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var attr = 'data-on-' + type;
            var el = e.target && e.target.closest ? e.target.closest('[' + attr + ']') : null;
            if (!el) return;
            var name = el.getAttribute(attr);
            var args = [];
            try { args = JSON.parse(el.getAttribute('data-args') || '[]'); } catch (err) { args = []; }
            args = args.map(function (a) { return a === '$this' ? el : a; });
            if (el.hasAttribute('data-prevent')) e.preventDefault();
            if (builtins[name]) {
                builtins[name].apply(el, [el].concat(args));
            } else if (typeof window[name] === 'function') {
                window[name].apply(el, args);
            }
        }, true); // capture: "error" does not bubble
    });

    // Restore the button when the page is shown again from the back/forward cache.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-pcg-busy] [type="submit"][aria-busy="true"]').forEach(function (submit) {
            submit.disabled = false;
            submit.removeAttribute('aria-busy');
            if (submit.dataset.label) submit.textContent = submit.dataset.label;
        });
    });
})();
