/* Admin self-registrations dashboard (admin/dashboard). Glass theme on the console tokens. */
(function () {
    'use strict';

    var dataEl = document.getElementById('ad-data');
    if (!dataEl) return;
    var D = {};
    try { D = JSON.parse(dataEl.textContent || '{}'); } catch (e) { D = {}; }

    // Registration data is user-supplied: escape before inserting as HTML.
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    window.escapeHtml = window.escapeHtml || escapeHtml;

    function token(name) {
        return getComputedStyle(document.body).getPropertyValue(name).trim();
    }
    function photoOf(user) {
        return user && user.selfie_image ? D.filesUrl + user.selfie_image : D.noPhotoUrl;
    }
    function statusKey(s) {
        s = String(s || '').trim().toLowerCase();
        return ['pending', 'approved', 'rejected'].indexOf(s) >= 0 ? s : 'other';
    }

    /* ---------------- swal theme ---------------- */
    var swalClass = {
        popup: 'ad-swal',
        confirmButton: 'pcg-btn pcg-btn--primary',
        denyButton: 'pcg-btn ad-btn-danger',
        cancelButton: 'pcg-btn pcg-btn--secondary'
    };
    function swal(opts) {
        opts.customClass = swalClass;
        opts.buttonsStyling = false;
        return Swal.fire(opts);
    }

    /* ---------------- charts ---------------- */
    var charts = [];
    function palette(n) {
        // cyan-led sequence that reads on both themes
        var dark = document.documentElement.getAttribute('data-theme') !== 'light';
        var base = dark
            ? ['#22d3ee', '#818cf8', '#5ad48f', '#f5b84b', '#f8877c', '#c084fc', '#38bdf8', '#a3e635', '#fb7185', '#94a3b8']
            : ['#0e7490', '#4f46e5', '#15803d', '#b45309', '#b42318', '#7e22ce', '#0369a1', '#4d7c0f', '#be123c', '#475569'];
        var out = [];
        for (var i = 0; i < n; i++) out.push(base[i % base.length]);
        return out;
    }
    function buildCharts() {
        if (typeof Chart === 'undefined') return;
        charts.forEach(function (c) { c.destroy(); });
        charts = [];
        var text = token('--c-text'), muted = token('--c-muted'), line = token('--c-line'), surface = token('--c-surface');
        Chart.defaults.color = muted;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        var tooltip = { backgroundColor: surface, titleColor: text, bodyColor: text, borderColor: line, borderWidth: 1, padding: 10, cornerRadius: 10 };

        var courses = D.courses || { labels: [], data: [] };
        var cc = document.getElementById('courseChart');
        if (cc) {
            charts.push(new Chart(cc, {
                type: 'doughnut',
                data: { labels: courses.labels, datasets: [{ label: 'Personnel', data: courses.data, backgroundColor: palette(courses.labels.length), borderColor: surface, borderWidth: 2, hoverOffset: 6 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '68%',
                    plugins: { legend: { position: window.innerWidth < 640 ? 'bottom' : 'right', labels: { color: text, boxWidth: 10, boxHeight: 10, usePointStyle: true, padding: 12 } }, tooltip: tooltip }
                }
            }));
        }

        var trend = D.trend || { labels: [], registered: [], approved: [] };
        var tc = document.getElementById('trendChart');
        if (tc) {
            var accent = token('--c-accent'), ok = token('--c-ok');
            var ctx = tc.getContext('2d');
            var grad = ctx.createLinearGradient(0, 0, 0, tc.clientHeight || 260);
            grad.addColorStop(0, accent + '59');
            grad.addColorStop(1, accent + '00');
            charts.push(new Chart(tc, {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [
                        { label: 'Registered', data: trend.registered, borderColor: accent, backgroundColor: grad, fill: true, cubicInterpolationMode: 'monotone', borderWidth: 2, pointRadius: 3, pointBackgroundColor: accent },
                        { label: 'Now approved', data: trend.approved, borderColor: ok, backgroundColor: 'transparent', borderDash: [5, 4], cubicInterpolationMode: 'monotone', borderWidth: 2, pointRadius: 2, pointBackgroundColor: ok }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { ticks: { color: muted, maxRotation: 0, autoSkipPadding: 12 }, grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: line }, border: { display: false } }
                    },
                    plugins: { legend: { display: false }, tooltip: tooltip }
                }
            }));
        }
    }

    /* ---------------- details dialog ---------------- */
    var modal = document.getElementById('userModal');
    function setText(id, v) { var el = document.getElementById(id); if (el) el.textContent = v || 'N/A'; }
    function showDetails(user) {
        document.getElementById('modalPhoto').src = photoOf(user);
        setText('modalName', user.name);
        setText('modalEmail', user.email);
        setText('modalSerial', user.serial_number);
        setText('modalRank', user.rank);
        setText('modalCourse', user.course);
        setText('modalUnit', user.unit_assignment);
        setText('modalMother', [user.mother_unit, user.sub_unit].filter(Boolean).join(' · '));
        setText('modalCreated', user.created_at ? String(user.created_at).slice(0, 10) : '');
        var st = document.getElementById('modalStatus');
        st.textContent = user.status || 'Unknown';
        st.className = 'ad-pill ad-pill--' + statusKey(user.status);
        if (typeof modal.showModal === 'function') modal.showModal(); else modal.setAttribute('open', '');
    }
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('.closeModal')) modal.close();
        });
    }

    /* ---------------- approve / reject ---------------- */
    function review(user) {
        var photo = photoOf(user);
        swal({
            title: 'Approve this registration?',
            html:
                '<div class="ad-swal__who">' +
                '<img src="' + escapeHtml(photo) + '" alt="" class="ad-avatar ad-avatar--lg">' +
                '<h3>' + escapeHtml(user.name) + '</h3>' +
                '<dl class="ad-dl ad-dl--compact">' +
                '<div><dt>Serial</dt><dd>' + escapeHtml(user.serial_number || 'N/A') + '</dd></div>' +
                '<div><dt>Rank</dt><dd>' + escapeHtml(user.rank || 'N/A') + '</dd></div>' +
                '<div><dt>Unit</dt><dd>' + escapeHtml(user.unit_assignment || 'N/A') + '</dd></div>' +
                '<div><dt>Email</dt><dd>' + escapeHtml(user.email || 'N/A') + '</dd></div>' +
                '</dl></div>',
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'Approve',
            denyButtonText: 'Reject',
            cancelButtonText: 'Cancel',
            reverseButtons: false
        }).then(function (result) {
            if (!result.isConfirmed && !result.isDenied) return;
            var action = result.isConfirmed ? 'approve' : 'reject';
            var body = new URLSearchParams();
            body.set('_token', D.csrf);
            fetch('/admin/' + action + '/' + encodeURIComponent(user.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                body: body.toString()
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (j) {
                    if (!r.ok || j.ok === false) {
                        var err = new Error('HTTP ' + r.status);
                        err.userMessage = j && j.message;
                        throw err;
                    }
                    return j;
                });
            }).then(function () {
                return swal({
                    icon: 'success',
                    title: action === 'approve' ? 'Approved!' : 'Rejected',
                    text: action === 'approve' ? 'The member can now sign in.' : 'The registration was rejected; it cannot sign in.'
                });
            }).then(function () {
                location.reload();
            }).catch(function (err) {
                swal({ icon: 'error', title: action === 'approve' ? 'Not approved' : 'Not rejected', text: (err && err.userMessage) || ('Unable to ' + action + ' this registration. Reload the page and try again.') });
            });
        });
    }

    function userFromRow(tr) {
        if (!tr) return null;
        try { return JSON.parse(tr.getAttribute('data-user') || 'null'); } catch (e) { return null; }
    }
    function open(tr) {
        var user = userFromRow(tr);
        if (!user) return;
        if (statusKey(user.status) === 'pending') review(user); else showDetails(user);
    }
    // collected before DataTables pages the table (rows on other pages leave the DOM)
    var usersById = {};
    document.querySelectorAll('#personnelTable tbody tr[data-id]').forEach(function (tr) {
        var u = userFromRow(tr);
        if (u) usersById[String(u.id)] = u;
    });
    function userById(id) { return usersById[String(id)] || null; }

    /* ---------------- table + filters ---------------- */
    var table = null;
    var activeFilter = '';
    function setFilter(key) {
        activeFilter = key || '';
        document.querySelectorAll('[data-ad-filter]').forEach(function (b) {
            if (!b.classList.contains('ad-kpi') && !b.closest('.ad-segment')) return;
            b.setAttribute('aria-pressed', b.getAttribute('data-ad-filter') === activeFilter ? 'true' : 'false');
        });
        if (table) table.draw();
    }

    function init() {
        if (window.jQuery && jQuery.fn.dataTable) {
            jQuery.fn.dataTable.ext.search.push(function (settings, data, idx) {
                if (settings.nTable.id !== 'personnelTable' || !activeFilter) return true;
                var row = settings.aoData[idx] && settings.aoData[idx].nTr;
                return row && row.getAttribute('data-status-key') === activeFilter;
            });
            table = jQuery('#personnelTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [[1, 'asc']],
                columnDefs: [{ targets: [0, 6], orderable: false, searchable: false }],
                language: {
                    search: '',
                    searchPlaceholder: 'Search name, course, unit, email…',
                    lengthMenu: 'Show _MENU_',
                    info: '_START_–_END_ of _TOTAL_ personnel',
                    infoEmpty: 'No personnel',
                    infoFiltered: '(filtered from _MAX_)',
                    emptyTable: 'No personnel found',
                    zeroRecords: 'No personnel match this filter'
                }
            });
        }

        var tbody = document.querySelector('#personnelTable tbody');
        if (tbody) {
            tbody.addEventListener('dblclick', function (e) {
                if (e.target.closest('button, a')) return;
                open(e.target.closest('tr'));
            });
        }

        document.addEventListener('click', function (e) {
            var f = e.target.closest('[data-ad-filter]');
            if (f) {
                setFilter(f.getAttribute('data-ad-filter'));
                if (!f.closest('.ad-segment')) {
                    var t = document.getElementById('ad-table-title');
                    if (t) t.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
                return;
            }
            var r = e.target.closest('[data-ad-review]');
            if (r) { var u = userById(r.getAttribute('data-ad-review')); if (u) review(u); return; }
            var v = e.target.closest('[data-ad-view]');
            if (v) { var u2 = userById(v.getAttribute('data-ad-view')); if (u2) showDetails(u2); }
        });

        buildCharts();
        document.addEventListener('pcg:theme', buildCharts);

        if (D.flash) swal({ icon: 'success', title: 'Success!', text: D.flash });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
