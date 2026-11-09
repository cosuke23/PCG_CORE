/*
 * PCG_CORE UI behaviours (no dependencies). Loaded with `defer`.
 *
 *  - <button data-pcg-toggle-password="inputId">  show / hide a password field
 *  - <form data-pcg-busy>                          disable submit while sending ("Signing in…")
 *  - every other form: the sending button shows a spinner (opt out: data-pcg-no-busy)
 *  - wide <table>s get a horizontal scroll box on small screens
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
        // Only the element's own form, or a form that opts in with
        // data-pcg-submittable (e.g. the logout form), so injected markup
        // cannot submit an approve/delete form elsewhere on the page.
        'pcg.submit-form': function (el, sel) {
            var form = sel ? document.querySelector(sel) : el.form;
            if (form && (form === el.form || form.hasAttribute('data-pcg-submittable'))) form.submit();
        },
        'pcg.open-src': function (el) { window.open(el.currentSrc || el.src, '_blank', 'noopener'); },
        'pcg.img-fallback': function (el) {
            el.style.display = 'none';
            if (el.nextElementSibling) el.nextElementSibling.style.display = 'block';
        },
        'pcg.file-types': function (el) {
            var types = Array.prototype.slice.call(arguments, 1);
            var file = el.files && el.files[0];
            if (file && types.indexOf(file.type) === -1) {
                var pdf = types.indexOf('application/pdf') !== -1, img = types.length > (pdf ? 1 : 0);
                alert(pdf && img ? 'PDF, JPG or PNG files only' : pdf ? 'PDF files only' : 'Only JPG and PNG images are allowed');
                el.value = '';
            }
        }
    };

    // Only plain global functions defined by our own page scripts may be
    // called. Built-in browser functions (eval, setTimeout, fetch, open, ...)
    // are refused, so injected markup cannot turn data-on-* into code.
    // Library globals are never callable from markup either.
    var notCallable = ['$', 'jQuery', 'Swal', 'Sweetalert2', 'Chart', 'QRCode', 'DataTable', 'L', 'Alpine', 'pcg', 'escapeHtml'];

    function isPageFunction(name) {
        if (typeof name !== 'string' || !/^[A-Za-z_$][\w$]*$/.test(name)) return false;
        if (notCallable.indexOf(name) !== -1) return false;
        if (!Object.prototype.hasOwnProperty.call(window, name)) return false;
        var fn = window[name];
        if (typeof fn !== 'function') return false;
        try {
            return Function.prototype.toString.call(fn).indexOf('[native code]') === -1;
        } catch (err) {
            return false;
        }
    }

    ['click', 'dblclick', 'change', 'error'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var attr = 'data-on-' + type;
            var el = e.target && e.target.closest ? e.target.closest('[' + attr + ']') : null;
            if (!el) return;
            var name = el.getAttribute(attr);
            // "error" fires without any click (e.g. a broken <img>): only the
            // image fallback may run on it.
            if (type === 'error' && name !== 'pcg.img-fallback') return;
            var args = [];
            try { args = JSON.parse(el.getAttribute('data-args') || '[]'); } catch (err) { args = []; }
            args = args.map(function (a) { return a === '$this' ? el : a; });
            if (el.hasAttribute('data-prevent')) e.preventDefault();
            if (builtins[name]) {
                builtins[name].apply(el, [el].concat(args));
            } else if (isPageFunction(name)) {
                window[name].apply(el, args);
            }
        }, true); // capture: "error" does not bubble
    });

    // Confirmation for consequential actions: <button data-pcg-confirm="Question?">
    // shown in the page's own dialog (not the browser's "site says" box).
    // Optional: data-pcg-confirm-title, data-pcg-confirm-ok (button label; default:
    // the button's own text), data-pcg-confirm-danger (red confirm button).
    function pcgConfirm(opts) {
        return new Promise(function (resolve) {
            var dlg = document.createElement('dialog');
            dlg.className = 'pcg-confirm';
            dlg.setAttribute('aria-labelledby', 'pcg-confirm-title');
            var box = document.createElement('div');
            box.className = 'pcg-confirm__box';
            var h = document.createElement('h2'); h.id = 'pcg-confirm-title'; h.textContent = opts.title || 'Please confirm';
            var p = document.createElement('p'); p.textContent = opts.text;
            var row = document.createElement('div'); row.className = 'pcg-confirm__actions';
            var no = document.createElement('button'); no.type = 'button'; no.className = 'pcg-btn pcg-btn--secondary'; no.textContent = opts.cancel || 'Cancel';
            var yes = document.createElement('button'); yes.type = 'button';
            yes.className = 'pcg-btn ' + (opts.danger ? 'pcg-btn--danger' : 'pcg-btn--cta');
            yes.textContent = opts.ok || 'Continue';
            row.appendChild(no); row.appendChild(yes);
            box.appendChild(h); box.appendChild(p); box.appendChild(row);
            dlg.appendChild(box);
            (document.querySelector('.pcg-app, body.pcg') || document.body).appendChild(dlg);
            var done = function (v) { if (dlg.open) dlg.close(); dlg.remove(); resolve(v); };
            no.addEventListener('click', function () { done(false); });
            yes.addEventListener('click', function () { done(true); });
            dlg.addEventListener('cancel', function (e) { e.preventDefault(); done(false); }); // Escape
            dlg.addEventListener('click', function (e) { if (e.target === dlg) done(false); }); // backdrop
            if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
            no.focus(); // the safe choice has focus
        });
    }
    window.pcgConfirm = pcgConfirm;

    document.addEventListener('submit', function (e) {
        var form = e.target, submitter = e.submitter;
        var question = submitter && submitter.getAttribute('data-pcg-confirm');
        if (!question || form.dataset.pcgConfirmed === '1') return;
        e.preventDefault();
        e.stopImmediatePropagation();
        pcgConfirm({
            title: submitter.getAttribute('data-pcg-confirm-title'),
            text: question,
            ok: submitter.getAttribute('data-pcg-confirm-ok') || submitter.textContent.trim() || 'Continue',
            danger: submitter.hasAttribute('data-pcg-confirm-danger')
        }).then(function (ok) {
            if (!ok) return;
            form.dataset.pcgConfirmed = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter); else form.submit();
            setTimeout(function () { delete form.dataset.pcgConfirmed; }, 0);
        });
    }, true);

    // Small screens: wide tables scroll inside their own box instead of
    // pushing the whole page sideways. Runs after page scripts (DataTables)
    // have built their tables. Opt out with data-pcg-no-wrap on an ancestor.
    window.addEventListener('load', function () {
        document.querySelectorAll('table').forEach(function (table) {
            if (table.closest('.pcg-table-wrap, [data-pcg-no-wrap]')) return;
            for (var p = table.parentElement; p && p !== document.body; p = p.parentElement) {
                var ox = window.getComputedStyle(p).overflowX;
                if (ox === 'auto' || ox === 'scroll') return;
            }
            var wrap = document.createElement('div');
            wrap.className = 'pcg-table-wrap';
            table.parentNode.insertBefore(wrap, table);
            wrap.appendChild(table);
        });
    });

    // Loading state for every form (design rules 2026-11-09): the button that sent
    // the form shows a spinner and cannot be pressed twice. Runs after the
    // confirmation handler above, and only if nothing cancelled the submit.
    // Opt out with data-pcg-no-busy on the form; forms that already use
    // data-pcg-busy keep their own wording.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-pcg-no-busy') || form.hasAttribute('data-pcg-busy')) return;
        var btn = e.submitter || form.querySelector('[type="submit"]');
        setTimeout(function () { // after the browser has read the submitter's name/value
            if (e.defaultPrevented || !btn || btn.getAttribute('aria-busy') === 'true') return;
            pcgBusy(btn, true);
            // downloads and same-page answers never navigate: give the button back
            setTimeout(function () { pcgBusy(btn, false); }, 8000);
        }, 0);
    });
    function pcgBusy(btn, on) {
        if (on) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; }
        else { btn.removeAttribute('aria-busy'); btn.disabled = false; }
    }
    window.pcgBusy = pcgBusy; // page scripts that submit with form.submit() or fetch use it too

    // Restore the button when the page is shown again from the back/forward cache.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('[aria-busy="true"]').forEach(function (b) { if (!b.closest('form[data-pcg-busy]')) pcgBusy(b, false); });
        document.querySelectorAll('form[data-pcg-busy] [type="submit"][aria-busy="true"]').forEach(function (submit) {
            submit.disabled = false;
            submit.removeAttribute('aria-busy');
            if (submit.dataset.label) submit.textContent = submit.dataset.label;
        });
    });
})();
