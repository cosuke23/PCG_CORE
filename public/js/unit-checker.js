/* Unit checker pages: review queue (filters, search), the review dialog with its
   document viewer, the M1 serial search for disciplinary uploads, and the 201 files
   viewer. Server data is only ever placed with textContent / attributes. */
(function () {
    'use strict';

    function el(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }
    function icon(name) {
        var i = el('i', 'fa-solid fa-' + name);
        i.setAttribute('aria-hidden', 'true');
        return i;
    }

    /* ----------------------------------------------------------- viewer */
    // shows one file (pdf, image, other) in a frame, with a loading skeleton
    function showFile(frame, item, emptyText) {
        frame.textContent = '';
        frame.classList.remove('is-empty');
        if (!item || !item.url) {
            frame.classList.add('is-empty');
            frame.appendChild(icon('file-circle-xmark'));
            frame.appendChild(el('p', null, emptyText || 'Not uploaded yet.'));
            return;
        }
        var skel = el('span', 'pcg-skeleton uc-skeleton');
        frame.appendChild(skel);
        var node;
        if (item.kind === 'image') {
            node = el('img');
            node.alt = item.label;
        } else if (item.kind === 'pdf') {
            node = el('iframe');
            node.title = item.label;
        } else {
            skel.remove();
            var a = el('a', 'pcg-btn pcg-btn--secondary', 'Open the file');
            a.href = item.url; a.target = '_blank'; a.rel = 'noopener';
            frame.classList.add('is-empty');
            frame.appendChild(a);
            return;
        }
        node.addEventListener('load', function () { skel.remove(); node.classList.add('is-loaded'); });
        node.addEventListener('error', function () {
            skel.remove(); frame.classList.add('is-empty');
            frame.textContent = '';
            frame.appendChild(icon('triangle-exclamation'));
            frame.appendChild(el('p', null, 'The file could not be opened. Try “Open in a new tab”.'));
        });
        node.src = item.url;
        frame.appendChild(node);
    }

    /* ------------------------------------------------------ review queue */
    var dataEl = document.getElementById('uc-data');
    if (dataEl) {
        var rows = JSON.parse(dataEl.textContent || '[]');
        var tbody = document.querySelector('.uc-table tbody');
        var search = document.getElementById('uc-search');
        var empty = document.getElementById('uc-empty');
        var filterBtns = document.querySelectorAll('[data-uc-filter]');
        var current = (document.querySelector('[data-uc-filter][aria-pressed="true"]') || {}).getAttribute ?
            document.querySelector('[data-uc-filter][aria-pressed="true"]').getAttribute('data-uc-filter') : 'all';

        function applyFilter() {
            var q = (search.value || '').trim().toLowerCase();
            var shown = 0;
            tbody.querySelectorAll('tr').forEach(function (tr) {
                var ok = (current === 'all' || tr.getAttribute('data-status') === current) &&
                    (!q || tr.getAttribute('data-search').indexOf(q) !== -1);
                tr.hidden = !ok;
                if (ok) shown++;
            });
            if (rows.length) {
                empty.hidden = shown > 0;
                empty.textContent = q ? 'No record matches “' + search.value.trim() + '”.' : 'No record in this list.';
            }
        }
        filterBtns.forEach(function (b) {
            b.addEventListener('click', function () {
                current = b.getAttribute('data-uc-filter');
                filterBtns.forEach(function (o) { o.setAttribute('aria-pressed', o === b ? 'true' : 'false'); });
                applyFilter();
            });
        });
        search.addEventListener('input', applyFilter);
        applyFilter();

        /* -------------------------------------------------- the dialog */
        var dlg = document.getElementById('uc-dialog');
        var form = document.getElementById('uc-form');
        var list = document.getElementById('uc-items');
        var frame = document.getElementById('uc-v-frame');
        var vTitle = document.getElementById('uc-v-title');
        var vOpen = document.getElementById('uc-v-open');
        var returnBox = document.getElementById('uc-return');
        var returnBtn = document.getElementById('uc-return-btn');
        var approveBtn = document.getElementById('uc-approve-btn');
        var summary = document.getElementById('uc-summary');
        var record = null, selected = null;

        function returnedCount() {
            return form.querySelectorAll('.uc-item__return input:checked').length;
        }
        function refreshSummary() {
            var n = returnedCount();
            returnBtn.disabled = n === 0;
            returnBtn.querySelector('span').textContent = n ? 'Return ' + n + (n === 1 ? ' item' : ' items') : 'Return items';
            approveBtn.disabled = n > 0;
            summary.textContent = n
                ? n + (n === 1 ? ' item' : ' items') + ' will go back to the member with your comment. Untick to approve instead.'
                : 'Tick “Return” on any item that must be uploaded again.';
            list.querySelectorAll('.uc-item').forEach(function (li) {
                var cb = li.querySelector('.uc-item__return input');
                li.classList.toggle('is-returned', !!(cb && cb.checked));
            });
            showComment();
        }
        // only the selected item's comment box is visible; all stay in the form
        function showComment() {
            returnBox.querySelectorAll('.uc-return__field').forEach(function (f) {
                var cb = form.querySelector('#uc-ret-' + f.getAttribute('data-field'));
                f.hidden = !(selected && f.getAttribute('data-field') === selected.field && cb && cb.checked);
            });
        }

        function select(item, li) {
            selected = item;
            list.querySelectorAll('.uc-item').forEach(function (x) { x.classList.toggle('is-current', x === li); });
            list.querySelectorAll('.uc-item__pick').forEach(function (b) { b.setAttribute('aria-current', b.parentNode === li ? 'true' : 'false'); });
            vTitle.textContent = item.label;
            if (item.field === 'location') {
                vOpen.hidden = true;
                frame.textContent = '';
                frame.classList.add('is-empty');
                frame.appendChild(icon('location-dot'));
                frame.appendChild(el('p', 'pcg-num', 'Pinned at ' + item.pin.lat.toFixed(6) + ', ' + item.pin.lng.toFixed(6)));
                var g = el('a', 'pcg-btn pcg-btn--secondary', 'Open in Google Maps');
                g.href = 'https://www.google.com/maps?q=' + encodeURIComponent(item.pin.lat) + ',' + encodeURIComponent(item.pin.lng);
                g.target = '_blank'; g.rel = 'noopener';
                frame.appendChild(g);
            } else {
                vOpen.hidden = !item.url;
                if (item.url) vOpen.href = item.url;
                showFile(frame, item);
            }
            showComment();
        }

        function build(r) {
            record = r;
            list.textContent = '';
            returnBox.textContent = '';
            document.getElementById('uc-d-name').textContent = r.name;
            document.getElementById('uc-d-serial').textContent = r.serial;
            document.getElementById('uc-d-serial-input').value = r.serial;
            document.getElementById('uc-d-unit').textContent = r.unit ? r.unit : '';
            var st = document.getElementById('uc-d-status');
            st.className = 'uc-status uc-status--' + r.status;
            st.textContent = { waiting: 'Waiting', returned: 'Returned', approved: 'Approved' }[r.status];

            var items = r.items.slice();
            if (r.pin) items.push({ field: 'location', label: 'Residence location', pin: r.pin, url: true });
            else items.push({ field: 'location-none', label: 'Residence location', url: null });

            var first = null;
            items.forEach(function (it) {
                var li = el('li', 'uc-item');
                var pick = el('button', 'uc-item__pick');
                pick.type = 'button';
                pick.appendChild(icon(it.field === 'location' || it.field === 'location-none' ? 'location-dot'
                    : !it.url ? 'circle-minus' : it.kind === 'image' ? 'image' : 'file-pdf'));
                var txt = el('span', 'uc-item__text');
                txt.appendChild(el('strong', null, it.label));
                txt.appendChild(el('small', null, !it.url ? (it.field === 'location-none' ? 'No pin set' : 'Not uploaded')
                    : it.expires ? 'Valid until ' + it.expires : it.field === 'location' ? 'Pinned on the map' : 'Uploaded'));
                pick.appendChild(txt);
                li.appendChild(pick);
                if (!it.url) li.classList.add('is-missing');

                if (it.url && it.field !== 'location-none') {
                    var lab = el('label', 'uc-item__return');
                    var cb = el('input');
                    cb.type = 'checkbox';
                    cb.id = 'uc-ret-' + it.field;
                    cb.name = 'docs[' + it.field + '][checked]';
                    cb.value = '1';
                    cb.addEventListener('change', function () {
                        if (cb.checked) { select(it, li); }
                        refreshSummary();
                        if (cb.checked) { var ta = returnBox.querySelector('[data-field="' + it.field + '"] textarea'); if (ta) ta.focus(); }
                    });
                    lab.appendChild(cb);
                    lab.appendChild(el('span', null, 'Return'));
                    li.appendChild(lab);

                    var f = el('div', 'uc-return__field');
                    f.setAttribute('data-field', it.field);
                    f.hidden = true;
                    var l = el('label', 'pcg-label', 'Why it goes back: ' + it.label);
                    l.htmlFor = 'uc-com-' + it.field;
                    var ta = el('textarea', 'pcg-input');
                    ta.id = 'uc-com-' + it.field;
                    ta.name = 'docs[' + it.field + '][comment]';
                    ta.rows = 2;
                    ta.maxLength = 2000;
                    ta.placeholder = it.field === 'location' ? 'e.g. Pin your house, not the barangay hall' : 'e.g. The scan is blurred; upload a clear copy';
                    f.appendChild(l); f.appendChild(ta);
                    returnBox.appendChild(f);
                }
                pick.addEventListener('click', function () {
                    select(it, li);
                    // phones: the preview sits under the list
                    if (window.matchMedia('(max-width: 860px)').matches) frame.closest('.uc-viewer').scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                list.appendChild(li);
                if (!first && it.url && it.field !== 'location') first = [it, li];
            });
            var start = first || [items[0], list.firstChild];
            select(start[0], start[1]);
            refreshSummary();
        }

        function open(i) {
            build(rows[i]);
            if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
            document.body.classList.add('uc-locked');
        }
        dlg.addEventListener('close', function () { document.body.classList.remove('uc-locked'); frame.textContent = ''; });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); }); // backdrop
        dlg.querySelector('[data-uc-close]').addEventListener('click', function () { dlg.close(); });
        document.querySelectorAll('[data-uc-open]').forEach(function (b) {
            b.addEventListener('click', function (e) { e.stopPropagation(); open(+b.getAttribute('data-uc-open')); });
        });
        tbody.querySelectorAll('tr').forEach(function (tr) {
            tr.addEventListener('click', function (e) { if (!e.target.closest('button,a')) open(+tr.getAttribute('data-uc-row')); });
        });
        // returning with an empty comment: ask for one (the member needs to know what to fix)
        form.addEventListener('submit', function (e) {
            if (!e.submitter || e.submitter.value !== 'rejected') return;
            var missing = Array.prototype.find.call(form.querySelectorAll('.uc-item__return input:checked'), function (cb) {
                var ta = form.querySelector('#uc-com-' + cb.id.replace('uc-ret-', ''));
                return ta && !ta.value.trim();
            });
            if (missing) {
                e.preventDefault();
                var it = record.items.concat(record.pin ? [{ field: 'location', label: 'Residence location', pin: record.pin, url: true }] : [])
                    .filter(function (x) { return 'uc-ret-' + x.field === missing.id; })[0];
                var li = missing.closest('.uc-item');
                if (it) select(it, li);
                var ta = form.querySelector('#uc-com-' + missing.id.replace('uc-ret-', ''));
                ta.setCustomValidity('Tell the member what to fix.');
                ta.reportValidity();
                ta.addEventListener('input', function () { ta.setCustomValidity(''); }, { once: true });
            }
        }, true);
    }

    /* ------------------------------------- M1: serial search for disciplinary uploads */
    var sForm = document.getElementById('uc-serial-form');
    if (sForm) {
        var sInput = document.getElementById('uc-serial-input');
        var sOut = document.getElementById('uc-serial-result');
        var dForm = document.getElementById('uc-disc-form');
        sForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = sForm.querySelector('[type=submit]');
            var serial = sInput.value.trim();
            if (!serial) { sInput.focus(); return; }
            window.pcgBusy && window.pcgBusy(btn, true);
            sOut.textContent = '';
            dForm.hidden = true;
            fetch('/unit/search-serial/' + encodeURIComponent(serial), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d.success) {
                        var who = el('p', 'uc-found');
                        who.appendChild(icon('user-check'));
                        who.appendChild(el('strong', null, d.fullname || 'Name not given'));
                        who.appendChild(el('span', 'pcg-mono', d.serial_number));
                        sOut.appendChild(who);
                        document.getElementById('uc-disc-serial').value = d.serial_number;
                        dForm.hidden = false;
                    } else {
                        sOut.appendChild(el('p', 'uc-notfound', 'No member with serial number ' + serial + '.'));
                    }
                })
                .catch(function () { sOut.appendChild(el('p', 'uc-notfound', 'The search did not go through. Check the connection and try again.')); })
                .then(function () { window.pcgBusy && window.pcgBusy(btn, false); });
        });
        document.getElementById('uc-disc-add').addEventListener('click', function () {
            var wrap = document.getElementById('uc-disc-files');
            var row = wrap.firstElementChild.cloneNode(true);
            row.querySelector('input').value = '';
            wrap.appendChild(row);
        });
        document.getElementById('uc-disc-files').addEventListener('click', function (e) {
            var b = e.target.closest('[data-uc-remove]');
            if (!b) return;
            var wrap = document.getElementById('uc-disc-files');
            if (wrap.children.length > 1) b.closest('.uc-disc__row').remove();
            else b.closest('.uc-disc__row').querySelector('input').value = '';
        });
    }

    /* ---------------------------------------------------- 201 files viewer */
    var fDlg = document.getElementById('uf-dialog');
    if (fDlg) {
        var labels = { soi: 'SOI', appointment: 'Appointment', leave: 'Leave', awards: 'Awards', disciplinary: 'Disciplinary',
            performance: 'Performance', legal: 'Legal', academic: 'Academic', financial: 'Financial', retirement: 'Retirement', body_picture: 'Body picture' };
        var base = fDlg.getAttribute('data-base');
        var fList = document.getElementById('uf-items');
        var fFrame = document.getElementById('uf-frame');
        var fTitle = document.getElementById('uf-title');
        var fOpen = document.getElementById('uf-open');
        var fSearch = document.getElementById('uf-search');
        var fEmpty = document.getElementById('uf-empty');

        fSearch.addEventListener('input', function () {
            var q = fSearch.value.trim().toLowerCase(), shown = 0;
            document.querySelectorAll('.uf-table tbody tr').forEach(function (tr) {
                var ok = !q || tr.getAttribute('data-search').indexOf(q) !== -1;
                tr.hidden = !ok; if (ok) shown++;
            });
            fEmpty.hidden = shown > 0;
        });

        function fShow(item, li) {
            fList.querySelectorAll('.uc-item').forEach(function (x) { x.classList.toggle('is-current', x === li); });
            fTitle.textContent = item.label;
            fOpen.hidden = !item.url;
            if (item.url) fOpen.href = item.url;
            showFile(fFrame, item, 'Not uploaded yet.');
        }
        function fOpenRecord(serial, btn) {
            window.pcgBusy && window.pcgBusy(btn, true);
            fetch('/unit/201-files/' + encodeURIComponent(serial), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var p = d.personnel || {}, docs = d.documents || {};
                    document.getElementById('uf-name').textContent = [p.last_name, p.first_name].filter(Boolean).join(', ') || 'Name not given';
                    document.getElementById('uf-serial').textContent = p.serial_number || serial;
                    fList.textContent = '';
                    var first = null;
                    Object.keys(labels).forEach(function (k) {
                        var path = docs[k] ? String(docs[k]).replace(/^\/+/, '') : null;
                        var item = { label: labels[k], url: path ? base + path : null,
                            kind: !path ? null : /\.pdf$/i.test(path) ? 'pdf' : /\.(jpe?g|png|gif|webp)$/i.test(path) ? 'image' : 'file' };
                        var li = el('li', 'uc-item' + (path ? '' : ' is-missing'));
                        var pick = el('button', 'uc-item__pick'); pick.type = 'button';
                        pick.appendChild(icon(!path ? 'circle-minus' : item.kind === 'image' ? 'image' : 'file-pdf'));
                        var t = el('span', 'uc-item__text');
                        t.appendChild(el('strong', null, item.label));
                        t.appendChild(el('small', null, path ? 'Uploaded' : 'Not uploaded'));
                        pick.appendChild(t);
                        pick.addEventListener('click', function () { fShow(item, li); });
                        li.appendChild(pick);
                        fList.appendChild(li);
                        if (!first && path) first = [item, li];
                    });
                    var start = first || [{ label: labels.soi, url: null }, fList.firstChild];
                    fShow(start[0], start[1]);
                    fDlg.showModal();
                })
                .catch(function () { alert('The 201 file could not be loaded. Try again.'); })
                .then(function () { window.pcgBusy && window.pcgBusy(btn, false); });
        }
        document.querySelectorAll('[data-uf-open]').forEach(function (b) {
            b.addEventListener('click', function () { fOpenRecord(b.getAttribute('data-uf-open'), b); });
        });
        fDlg.addEventListener('click', function (e) { if (e.target === fDlg) fDlg.close(); });
        fDlg.querySelector('[data-uc-close]').addEventListener('click', function () { fDlg.close(); });
        fDlg.addEventListener('close', function () { fFrame.textContent = ''; });
    }
})();
