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

    // Restore the button when the page is shown again from the back/forward cache.
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-pcg-busy] [type="submit"][aria-busy="true"]').forEach(function (submit) {
            submit.disabled = false;
            submit.removeAttribute('aria-busy');
            if (submit.dataset.label) submit.textContent = submit.dataset.label;
        });
    });
})();
