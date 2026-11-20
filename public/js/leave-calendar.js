/*
 * Leave calendar (partials/leave-calendar): open a day to list everyone on leave;
 * on the member page ([data-selectable]) the first click picks the first day and
 * the second the last day, filling the form's date inputs (which also work alone).
 * Content is copied from the server-rendered list (cloneNode), never built from strings.
 */
(function () {
    'use strict';

    function parse(d) { var p = d.split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); }
    // calendar days, or working days (Mon-Fri minus the configured holidays) when the page says so
    function days(a, b, mode, off) {
        if (mode !== 'working') return Math.round((parse(b) - parse(a)) / 86400000) + 1;
        var n = 0;
        for (var d = parse(a); d <= parse(b); d.setDate(d.getDate() + 1)) {
            var key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            if (d.getDay() !== 0 && d.getDay() !== 6 && off.indexOf(key) === -1) n++;
        }
        return n;
    }
    function pretty(d) {
        return parse(d).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    document.querySelectorAll('[data-leave-calendar]').forEach(function (root) {
        var cells = Array.prototype.slice.call(root.querySelectorAll('.lc-day[data-date]'));
        var panel = root.querySelector('[data-lc-panel]');
        var panelTitle = root.querySelector('[data-lc-panel-title]');
        var panelList = root.querySelector('[data-lc-panel-list]');
        var panelEmpty = root.querySelector('[data-lc-panel-empty]');
        var selectable = root.hasAttribute('data-selectable');
        var startInput = document.querySelector('[data-lc-start]');
        var endInput = document.querySelector('[data-lc-end]');
        var summary = document.querySelector('[data-lc-summary]');
        var mode = root.getAttribute('data-count') || 'calendar';
        var off = (root.getAttribute('data-holidays') || '').split(',').filter(Boolean);
        var start = startInput ? startInput.value : '';
        var end = endInput ? endInput.value : '';

        function openDay(cell) {
            cells.forEach(function (c) { c.classList.remove('is-open'); });
            cell.classList.add('is-open');
            panelTitle.textContent = pretty(cell.getAttribute('data-date'));
            while (panelList.firstChild) panelList.removeChild(panelList.firstChild);
            var all = cell.querySelector('.lc-day__all');
            var items = all ? all.children : [];
            for (var i = 0; i < items.length; i++) panelList.appendChild(items[i].cloneNode(true));
            panelEmpty.hidden = items.length > 0;
            panel.hidden = false;
        }

        function maxDays() {
            var checked = document.querySelector('input[name="leave_type"]:checked');
            return checked ? parseInt(checked.getAttribute('data-max'), 10) || 0 : 0;
        }

        function paint() {
            cells.forEach(function (c) {
                var d = c.getAttribute('data-date');
                var inRange = start && end && d >= start && d <= end;
                c.classList.toggle('is-selected', !!inRange);
                c.classList.toggle('is-edge', d === start || d === end);
            });
            if (!summary) return;
            summary.classList.remove('is-over');
            if (start && end && end >= start) {
                var n = days(start, end, mode, off), max = maxDays();
                summary.textContent = pretty(start) + (start === end ? '' : ' to ' + pretty(end)) + ' · ' + n + (mode === 'working' ? ' working' : '') + (n === 1 ? ' day' : ' days')
                    + (max && n > max ? ' (this type allows ' + max + ')' : '');
                if (max && n > max) summary.classList.add('is-over');
            } else if (start) {
                summary.textContent = 'First day: ' + pretty(start) + '. Now click the last day.';
            } else {
                summary.textContent = 'No dates picked yet.';
            }
        }

        function pick(d) {
            if (!start || (start && end)) { start = d; end = ''; }
            else if (d < start) { start = d; }
            else { end = d; }
            if (startInput) startInput.value = start;
            if (endInput) endInput.value = end;
            paint();
        }

        function activate(cell) {
            if (cell.classList.contains('is-disabled')) return;
            openDay(cell);
            if (selectable) pick(cell.getAttribute('data-date'));
        }

        cells.forEach(function (cell) {
            cell.addEventListener('click', function () { activate(cell); });
            cell.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); activate(cell); }
            });
        });

        var close = root.querySelector('[data-lc-panel-close]');
        if (close) close.addEventListener('click', function () {
            panel.hidden = true;
            cells.forEach(function (c) { c.classList.remove('is-open'); });
        });

        if (selectable) {
            [startInput, endInput].forEach(function (input) {
                if (!input) return;
                input.addEventListener('change', function () {
                    start = startInput.value; end = endInput.value;
                    if (start && end && end < start) { end = ''; endInput.value = ''; }
                    paint();
                });
            });
            document.querySelectorAll('input[name="leave_type"]').forEach(function (r) { r.addEventListener('change', paint); });
            paint();
        }
    });
})();
