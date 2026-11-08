/* Admin console navbar: sidebar collapse (remembered), account menu and "Go to a page" search. */
(function () {
    'use strict';
    var root = document.documentElement;

    /* ---------------- sidebar collapse (desktop) ---------------- */
    var collapseBtn = document.querySelector('[data-pcg-sidebar-toggle]');
    var links = Array.prototype.slice.call(document.querySelectorAll('#sidebar .pcg-nav__link'));

    function applySidebar() {
        var collapsed = root.getAttribute('data-sidebar') === 'collapsed';
        if (collapseBtn) {
            collapseBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            var label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
            collapseBtn.setAttribute('aria-label', label);
            collapseBtn.setAttribute('title', label);
        }
        // icon-only rail: show each page name as a tooltip
        links.forEach(function (a) {
            var text = (a.querySelector('span') || a).textContent.trim();
            if (collapsed) a.setAttribute('title', text); else a.removeAttribute('title');
        });
    }
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            var next = root.getAttribute('data-sidebar') === 'collapsed' ? 'expanded' : 'collapsed';
            if (next === 'collapsed') root.setAttribute('data-sidebar', 'collapsed'); else root.removeAttribute('data-sidebar');
            try { localStorage.setItem('pcgSidebar', next); } catch (e) { /* storage off: this page only */ }
            applySidebar();
            window.dispatchEvent(new Event('resize')); // charts and tables re-measure
        });
    }
    applySidebar();

    /* ---------------- account menu ---------------- */
    var meBtn = document.querySelector('[data-pcg-user-toggle]');
    var menu = document.getElementById('pcg-user-menu');
    function setMenu(open) {
        if (!meBtn || !menu) return;
        menu.hidden = !open;
        meBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    if (meBtn && menu) {
        meBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            setMenu(menu.hidden);
            if (!menu.hidden) { var first = menu.querySelector('button, a'); if (first) first.focus(); }
        });
        document.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target)) setMenu(false); });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !menu.hidden) { setMenu(false); meBtn.focus(); }
        });
    }

    /* ---------------- go to a page ---------------- */
    var input = document.getElementById('pcg-jump');
    var list = document.getElementById('pcg-jump-list');
    if (!input || !list) return;

    var pages = links.map(function (a) {
        var section = '';
        for (var el = a.previousElementSibling; el; el = el.previousElementSibling) {
            if (el.classList.contains('pcg-nav__section')) { section = el.textContent.trim(); break; }
        }
        var icon = a.querySelector('i');
        return { label: (a.querySelector('span') || a).textContent.trim(), section: section, href: a.getAttribute('href'), icon: icon ? icon.className : '' };
    });
    var matches = [], active = -1;

    function render() {
        var q = input.value.trim().toLowerCase();
        matches = pages.filter(function (p) {
            return !q || (p.label + ' ' + p.section).toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);
        list.textContent = '';
        if (!matches.length) {
            var none = document.createElement('li');
            none.className = 'pcg-navbar__none';
            none.textContent = 'No page matches “' + input.value.trim() + '”';
            list.appendChild(none);
        }
        matches.forEach(function (p, i) {
            var li = document.createElement('li');
            li.id = 'pcg-jump-' + i;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', i === active ? 'true' : 'false');
            var a = document.createElement('a');
            a.href = p.href;
            a.tabIndex = -1;
            var ic = document.createElement('i');
            ic.className = p.icon;
            ic.setAttribute('aria-hidden', 'true');
            var name = document.createElement('span');
            name.textContent = p.label;
            var sec = document.createElement('small');
            sec.textContent = p.section;
            a.appendChild(ic); a.appendChild(name); a.appendChild(sec);
            li.appendChild(a);
            list.appendChild(li);
        });
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        input.setAttribute('aria-activedescendant', active >= 0 ? 'pcg-jump-' + active : '');
    }
    function close() {
        list.hidden = true;
        active = -1;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
    }

    input.addEventListener('focus', function () { active = -1; render(); });
    input.addEventListener('input', function () { active = 0; render(); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, matches.length - 1); render(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
        else if (e.key === 'Enter') {
            var pick = matches[active >= 0 ? active : 0];
            if (pick) { e.preventDefault(); window.location.href = pick.href; }
        } else if (e.key === 'Escape') { close(); input.blur(); }
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('.pcg-navbar__search')) close(); });

    // "/" focuses the search unless typing somewhere else
    document.addEventListener('keydown', function (e) {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName))) return;
        e.preventDefault();
        input.focus();
    });
})();
