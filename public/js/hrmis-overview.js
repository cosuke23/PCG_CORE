/*
 * HRMIS overview: cross-filtering over the personnel rows the server sent
 * (already limited to the admin's scope). Every number is a count of those
 * rows; nothing is estimated. Server text is inserted with textContent only.
 *
 * Filters: within one dimension (several ranks, several commands) a person
 * matches ANY selected value; across dimensions and flags they must match ALL.
 * Each chart counts with every filter except its own, so its other bars stay
 * visible and selectable.
 */
(function () {
    'use strict';

    var root = document.getElementById('hx');
    var dataEl = document.getElementById('hx-data');
    if (!root || !dataEl) return;

    var D = JSON.parse(dataEl.textContent || '{}');
    var rows = (D.rows || []).map(function (r) {
        r.cls = r.cls || 'unclassified';
        r.inactive = !!(r.status && r.status !== 'active');   // same rule as the server: NULL is not "inactive"
        r.incomplete = r.missing.length > 0;
        r.unassigned = !r.unit;
        r.hay = [r.name, r.serial, r.rank, r.rankName, r.unit, r.bucket].join(' ').toLowerCase();
        return r;
    });
    var movements = D.movements || [];
    var PAGE = 50;

    var CLS = { officer: 'Officers', non_officer: 'Non-officers', unclassified: 'Not classified' };
    var CLS_ONE = { officer: 'Officer', non_officer: 'Non-officer', unclassified: 'Not classified' };
    function statusLabel(st) { return st ? st.charAt(0).toUpperCase() + st.slice(1) : null; }
    var FLAGS = { incomplete: 'Needs attention', inactive: 'Not active', unassigned: 'Without assignment', moved: 'Moved in last 30 days' };
    var MISSING = ['rank', 'birth date', 'service entry date'];
    var NO_UNIT = '__none__';

    var state = { cls: new Set(), bucket: new Set(), rank: new Set(), missing: new Set(), flags: new Set(), q: '', sort: 'name', dir: 1, shown: PAGE };

    var $ = function (id) { return document.getElementById(id); };
    var fmt = function (n) { return Number(n).toLocaleString('en-US'); };
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined && text !== null) e.textContent = text; return e; }

    // ---------------------------------------------------------------- filtering
    function matches(r, skip) {
        if (skip !== 'cls' && state.cls.size && !state.cls.has(r.cls)) return false;
        if (skip !== 'bucket' && state.bucket.size && !state.bucket.has(r.bucket || NO_UNIT)) return false;
        if (skip !== 'rank' && state.rank.size && !state.rank.has(r.rank || '')) return false;
        if (skip !== 'missing' && state.missing.size && !r.missing.some(function (m) { return state.missing.has(m); })) return false;
        var ok = true;
        state.flags.forEach(function (f) { if (skip !== 'flag:' + f && !r[f]) ok = false; });
        if (!ok) return false;
        if (skip !== 'q' && state.q && r.hay.indexOf(state.q) === -1) return false;
        return true;
    }
    function filtered(skip) { return rows.filter(function (r) { return matches(r, skip); }); }
    function anyFilter() { return state.cls.size || state.bucket.size || state.rank.size || state.missing.size || state.flags.size || state.q; }

    // ---------------------------------------------------------------- render: KPIs
    function setV(key, value) { root.querySelectorAll('[data-v="' + key + '"]').forEach(function (n) { n.textContent = value; }); }

    function renderKpis(current) {
        setV('all', fmt(current.length));
        setV('all-of', anyFilter() ? ' of ' + fmt(rows.length) : '');
        ['officer', 'non_officer', 'unclassified'].forEach(function (c) {
            var n = filtered('cls').filter(function (r) { return r.cls === c; }).length;
            setV('cls:' + c, fmt(n));
            var seg = root.querySelector('[data-split="' + c + '"]');
            if (seg) seg.style.flexGrow = String(current.filter(function (r) { return r.cls === c; }).length);
        });
        Object.keys(FLAGS).forEach(function (f) {
            setV('flag:' + f, fmt(filtered('flag:' + f).filter(function (r) { return r[f]; }).length));
        });
        var ids = new Set(current.map(function (r) { return r.id; }));
        setV('movements', fmt(movements.filter(function (m) { return ids.has(m.pid); }).length));

        root.querySelectorAll('[data-kpi]').forEach(function (b) {
            var k = b.getAttribute('data-kpi');
            var on = k === 'all' ? !anyFilter()
                : k.indexOf('cls:') === 0 ? state.cls.has(k.slice(4))
                : state.flags.has(k.slice(5));
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    // ---------------------------------------------------------------- render: bar charts
    function renderBars(container, items, selected, onToggle, emptyText) {
        container.textContent = '';
        var max = items.reduce(function (m, i) { return Math.max(m, i.count); }, 0);
        if (!items.length || (max === 0 && !selected.size)) { container.appendChild(el('p', 'pcg-empty', emptyText)); return; }
        items.forEach(function (it) {
            var on = selected.has(it.key);
            var b = el('button', 'hx-bar' + (on ? ' is-on' : '') + (it.muted ? ' is-muted' : '') + (selected.size && !on ? ' is-dim' : ''));
            b.type = 'button';
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.title = it.title || it.label;
            b.setAttribute('aria-label', (it.title || it.label) + ': ' + fmt(it.count) + (on ? ', selected' : ''));
            var lab = el('span', 'hx-bar__label', it.label);
            var track = el('span', 'hx-bar__track');
            var fill = el('span', 'hx-bar__fill');
            fill.style.width = (max ? (it.count / max) * 100 : 0) + '%';
            track.appendChild(fill);
            b.appendChild(lab); b.appendChild(track); b.appendChild(el('span', 'hx-bar__num', fmt(it.count)));
            b.addEventListener('click', function () { onToggle(it.key); });
            container.appendChild(b);
        });
    }
    function toggle(set, key) { if (set.has(key)) set.delete(key); else set.add(key); state.shown = PAGE; update(); }

    function renderCharts() {
        // by command / unit
        var base = filtered('bucket');
        var unitItems = (D.buckets || []).map(function (b) {
            return { key: b.code, label: b.code, title: b.name, count: base.filter(function (r) { return r.bucket === b.code; }).length };
        });
        var noUnit = base.filter(function (r) { return !r.bucket; }).length;
        if (D.orgWide && (noUnit || state.bucket.has(NO_UNIT))) unitItems.push({ key: NO_UNIT, label: 'No current assignment', title: 'Not assigned to any unit', count: noUnit, muted: true });
        renderBars($('hx-bars-unit'), unitItems, state.bucket, function (k) { toggle(state.bucket, k); }, 'No ' + (D.unitHeading === 'unit' ? 'subordinate units' : 'commands') + ' yet. Add them under Organization.');

        // by rank (server order: officers first, most senior first)
        var rbase = filtered('rank');
        var rankItems = (D.ranks || []).map(function (rk) {
            var key = rk.code === 'No rank' ? '' : rk.code;
            return { key: key, label: rk.code, title: rk.name, count: rbase.filter(function (r) { return (r.rank || '') === key; }).length, muted: key === '' };
        });
        renderBars($('hx-bars-rank'), rankItems, state.rank, function (k) { toggle(state.rank, k); }, 'No personnel yet.');

        // missing items
        var mbase = filtered('missing');
        var mItems = MISSING.map(function (m) {
            return { key: m, label: m.charAt(0).toUpperCase() + m.slice(1), title: 'Missing ' + m, count: mbase.filter(function (r) { return r.missing.indexOf(m) !== -1; }).length };
        });
        renderBars($('hx-bars-missing'), mItems, state.missing, function (k) { toggle(state.missing, k); }, 'No record is missing a rank, birth date or service entry date.');
    }

    // ---------------------------------------------------------------- render: 30-day columns
    function renderDays(current) {
        var box = $('hx-days');
        box.textContent = '';
        var ids = new Set(current.map(function (r) { return r.id; }));
        var start = new Date((D.since || '') + 'T00:00:00');
        if (isNaN(start)) return;
        var days = [], byDay = {};
        movements.forEach(function (m) { if (ids.has(m.pid) && m.date) byDay[m.date] = (byDay[m.date] || 0) + 1; });
        for (var d = new Date(start); days.length < 31; d.setDate(d.getDate() + 1)) {
            var key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            days.push({ key: key, n: byDay[key] || 0 });
        }
        var max = days.reduce(function (m, x) { return Math.max(m, x.n); }, 0);
        var total = days.reduce(function (s, x) { return s + x.n; }, 0);
        days.forEach(function (x) {
            var col = el('span', 'hx-day' + (x.n ? '' : ' is-zero'));
            col.style.height = (max ? Math.max(4, (x.n / max) * 100) : 4) + '%';
            col.title = x.key + ': ' + x.n + ' started';
            box.appendChild(col);
        });
        $('hx-days-from').textContent = days[0].key;
        $('hx-days-to').textContent = days[days.length - 1].key;
        $('hx-days-note').textContent = total
            ? fmt(total) + ' assignment' + (total === 1 ? '' : 's') + ' started in this period for the people shown; the busiest day had ' + fmt(max) + '.'
            : 'No assignments started in the last 30 days for the people shown.';
    }

    // ---------------------------------------------------------------- render: table, chips, movements
    function sortRows(list) {
        var k = state.sort, dir = state.dir;
        var rankIdx = {};
        (D.ranks || []).forEach(function (rk, i) { rankIdx[rk.code === 'No rank' ? '' : rk.code] = i; });
        return list.slice().sort(function (a, b) {
            var x, y;
            if (k === 'rank') { x = rankIdx[a.rank || ''] ?? 999; y = rankIdx[b.rank || ''] ?? 999; }
            else { x = String(a[k] || '').toLowerCase(); y = String(b[k] || '').toLowerCase(); }
            if (x < y) return -dir; if (x > y) return dir;
            return String(a.name).localeCompare(String(b.name));
        });
    }

    function renderTable(current) {
        var body = $('hx-tbody');
        body.textContent = '';
        var list = sortRows(current);
        list.slice(0, state.shown).forEach(function (r) {
            var tr = document.createElement('tr');
            tr.appendChild(el('td', null, r.rank || '—'));
            var td = el('td');
            var a = el('button', 'hx-name', r.name);
            a.type = 'button';
            a.addEventListener('click', function () { openDrawer(r); });
            td.appendChild(a);
            tr.appendChild(td);
            tr.appendChild(el('td', 'hx-mono', r.serial || '—'));
            var tdu = el('td', null, r.unit || '—');
            if (r.bucket && r.bucket !== r.unit) tdu.appendChild(el('span', 'pcg-table__muted', r.bucket));
            tr.appendChild(tdu);
            tr.appendChild(el('td', null, statusLabel(r.status) || '—'));
            var notes = el('td', 'hx-notes');
            if (r.incomplete) notes.appendChild(el('span', 'pcg-badge pcg-badge--warning', 'Missing ' + r.missing.join(', ')));
            if (r.unassigned && D.orgWide) notes.appendChild(el('span', 'pcg-badge', 'No assignment'));
            if (r.moved) notes.appendChild(el('span', 'pcg-badge pcg-badge--info', 'Moved'));
            tr.appendChild(notes);
            body.appendChild(tr);
        });
        $('hx-none').hidden = list.length !== 0 || rows.length === 0;
        var more = $('hx-more');
        more.hidden = list.length <= state.shown;
        more.textContent = 'Show ' + fmt(Math.min(PAGE, list.length - state.shown)) + ' more (' + fmt(list.length - state.shown) + ' not shown)';
        root.querySelectorAll('#hx-table th[aria-sort]').forEach(function (th) {
            var key = th.querySelector('button').getAttribute('data-sort');
            th.setAttribute('aria-sort', key === state.sort ? (state.dir === 1 ? 'ascending' : 'descending') : 'none');
        });
    }

    function chipList() {
        var chips = [];
        state.cls.forEach(function (c) { chips.push({ label: CLS[c], off: function () { state.cls.delete(c); } }); });
        state.bucket.forEach(function (b) { chips.push({ label: (D.unitHeading === 'unit' ? 'Unit: ' : 'Command: ') + (b === NO_UNIT ? 'none' : b), off: function () { state.bucket.delete(b); } }); });
        state.rank.forEach(function (k) { chips.push({ label: 'Rank: ' + (k || 'none'), off: function () { state.rank.delete(k); } }); });
        state.missing.forEach(function (m) { chips.push({ label: 'Missing ' + m, off: function () { state.missing.delete(m); } }); });
        state.flags.forEach(function (f) { chips.push({ label: FLAGS[f], off: function () { state.flags.delete(f); } }); });
        if (state.q) chips.push({ label: 'Search: “' + state.q + '”', off: function () { state.q = ''; $('hx-q').value = ''; } });
        return chips;
    }

    function renderChips(current) {
        var ul = $('hx-chips');
        ul.textContent = '';
        var chips = chipList();
        chips.forEach(function (c) {
            var li = el('li');
            var b = el('button', 'hx-chip');
            b.type = 'button';
            b.appendChild(el('span', null, c.label));
            var x = el('i', 'fa-solid fa-xmark'); x.setAttribute('aria-hidden', 'true'); b.appendChild(x);
            b.setAttribute('aria-label', 'Remove filter ' + c.label);
            b.addEventListener('click', function () { c.off(); state.shown = PAGE; update(); });
            li.appendChild(b); ul.appendChild(li);
        });
        $('hx-clear').hidden = chips.length === 0;
        $('hx-count').textContent = fmt(current.length) + ' of ' + fmt(rows.length) + ' ' + (rows.length === 1 ? 'person' : 'people');
        var exp = $('hx-export');
        if (exp) { exp.disabled = current.length === 0; $('hx-export-n').textContent = fmt(current.length); }
    }

    function renderMoves(current) {
        var ids = {}, byId = {};
        current.forEach(function (r) { ids[r.id] = true; byId[r.id] = r; });
        var body = $('hx-moves');
        body.textContent = '';
        var list = movements.filter(function (m) { return ids[m.pid]; });
        list.slice(0, 12).forEach(function (m) {
            var r = byId[m.pid], tr = document.createElement('tr');
            var td = el('td'), a = el('button', 'hx-name', (r.rank ? r.rank + ' ' : '') + r.name);
            a.type = 'button'; a.addEventListener('click', function () { openDrawer(r); });
            td.appendChild(a); tr.appendChild(td);
            var to = el('td', null, m.unit || '—');
            if (m.position) to.appendChild(el('span', 'pcg-table__muted', m.position));
            tr.appendChild(to);
            tr.appendChild(el('td', 'hx-mono', m.date || '—'));
            tr.appendChild(el('td', null, m.ref || '—'));
            body.appendChild(tr);
        });
        $('hx-moves-none').hidden = list.length !== 0;
    }

    // ---------------------------------------------------------------- drawer
    var drawer = $('hx-drawer'), lastFocus = null;
    function openDrawer(r) {
        lastFocus = document.activeElement;
        $('hx-d-rank').textContent = r.rank ? r.rank + (r.rankName ? ' · ' + r.rankName : '') : 'Rank not recorded';
        $('hx-d-name').textContent = r.name;
        var dl = $('hx-d-list'); dl.textContent = '';
        [['Serial number', r.serial], ['Classification', CLS_ONE[r.cls]], ['Service status', statusLabel(r.status) || 'Not recorded'],
         ['Current unit', r.unit ? r.unit + (r.bucket && r.bucket !== r.unit ? ' (under ' + r.bucket + ')' : '') : 'No current assignment'],
         ['Position', r.position], ['Assigned since', r.since]].forEach(function (p) {
            if (!p[1]) return;
            var div = el('div'); div.appendChild(el('dt', null, p[0])); div.appendChild(el('dd', null, p[1])); dl.appendChild(div);
        });
        var flags = $('hx-d-flags'); flags.textContent = '';
        flags.appendChild(el('p', 'hx-d-ok', r.incomplete ? 'Record is missing: ' + r.missing.join(', ') + '.' : 'Rank, birth date and service entry date are all recorded.'));
        flags.firstChild.className = r.incomplete ? 'hx-d-warn' : 'hx-d-ok';
        var ul = $('hx-d-moves'); ul.textContent = '';
        var ms = movements.filter(function (m) { return m.pid === r.id; });
        if (!ms.length) ul.appendChild(el('li', 'pcg-table__muted', 'None.'));
        ms.forEach(function (m) { ul.appendChild(el('li', null, m.date + ' · to ' + (m.unit || '—') + (m.position ? ', ' + m.position : '') + (m.ref ? ' · ' + m.ref : ''))); });
        $('hx-d-open').href = root.getAttribute('data-person-url').replace('__ID__', encodeURIComponent(r.id));
        if (typeof drawer.showModal === 'function') drawer.showModal(); else drawer.setAttribute('open', '');
        $('hx-d-close').focus();
    }
    function closeDrawer() { if (drawer.open) { if (drawer.close) drawer.close(); else drawer.removeAttribute('open'); } }
    $('hx-d-close').addEventListener('click', closeDrawer);
    drawer.addEventListener('click', function (e) { if (e.target === drawer) closeDrawer(); });
    drawer.addEventListener('close', function () { if (lastFocus) lastFocus.focus(); });

    // ---------------------------------------------------------------- wiring
    function update() {
        var current = filtered();
        renderKpis(current);
        renderCharts();
        renderDays(current);
        renderTable(current);
        renderChips(current);
        renderMoves(current);
    }

    root.querySelectorAll('[data-kpi]').forEach(function (b) {
        b.addEventListener('click', function () {
            var k = b.getAttribute('data-kpi');
            if (k === 'all') { clearAll(); return; }
            if (k.indexOf('cls:') === 0) toggle(state.cls, k.slice(4));
            else toggle(state.flags, k.slice(5));
        });
    });
    function clearAll() {
        state.cls.clear(); state.bucket.clear(); state.rank.clear(); state.missing.clear(); state.flags.clear();
        state.q = ''; $('hx-q').value = ''; state.shown = PAGE; update();
    }
    $('hx-clear').addEventListener('click', clearAll);

    var timer = null;
    $('hx-q').addEventListener('input', function (e) {
        clearTimeout(timer);
        timer = setTimeout(function () { state.q = e.target.value.trim().toLowerCase(); state.shown = PAGE; update(); }, 150);
    });
    $('hx-q').addEventListener('keydown', function (e) { if (e.key === 'Escape' && e.target.value) { e.target.value = ''; state.q = ''; update(); } });

    root.querySelectorAll('#hx-table [data-sort]').forEach(function (b) {
        b.addEventListener('click', function () {
            var k = b.getAttribute('data-sort');
            if (state.sort === k) state.dir = -state.dir; else { state.sort = k; state.dir = 1; }
            update();
        });
    });
    $('hx-more').addEventListener('click', function () { state.shown += PAGE; update(); });

    var exp = $('hx-export');
    if (exp) exp.addEventListener('click', function () {
        var current = filtered();
        if (!current.length) return;
        $('hx-export-ids').value = current.map(function (r) { return r.id; }).join(',');
        $('hx-export-filters').value = chipList().map(function (c) { return c.label; }).join('; ').slice(0, 500);
        $('hx-export-form').submit();
    });

    update();
})();
