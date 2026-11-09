/* Training applications (admin/training-applications): two charts on the console tokens,
   and a "Saving..." note while a decision select submits its row form. */
(function () {
    'use strict';

    // Status selects submit through pcg.submit-form (form.submit(), no submit event):
    // show that the row is saving. The select stays enabled so its value is sent.
    document.addEventListener('change', function (e) {
        var sel = e.target.closest ? e.target.closest('.ta-decision__select') : null;
        if (!sel || !sel.form) return;
        sel.form.setAttribute('aria-busy', 'true');
        var note = sel.form.querySelector('.ta-decision__saving');
        if (note) note.hidden = false;
    }, true);
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.ta-decision[aria-busy="true"]').forEach(function (f) {
            f.removeAttribute('aria-busy');
            var note = f.querySelector('.ta-decision__saving');
            if (note) note.hidden = true;
        });
    });

    var dataEl = document.getElementById('ta-data');
    if (!dataEl) return;
    var D = {};
    try { D = JSON.parse(dataEl.textContent || '{}'); } catch (err) { D = {}; }

    function token(name) { return getComputedStyle(document.body).getPropertyValue(name).trim(); }

    var charts = [];
    function build() {
        if (typeof Chart === 'undefined') return;
        charts.forEach(function (c) { c.destroy(); });
        charts = [];
        var text = token('--c-text'), muted = token('--c-muted'), line = token('--c-line-soft'),
            surface = token('--c-surface'), accent = token('--c-accent');
        Chart.defaults.color = muted;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        var tooltip = { backgroundColor: surface, titleColor: text, bodyColor: text, borderColor: token('--c-line'), borderWidth: 1, padding: 10 };

        var status = D.status || { labels: [], data: [] };
        var sc = document.getElementById('statusChart');
        if (sc) {
            charts.push(new Chart(sc, {
                type: 'doughnut',
                data: {
                    labels: status.labels,
                    datasets: [{
                        data: status.data,
                        // pending, approved, rejected, completed
                        backgroundColor: [token('--c-warn'), token('--c-ok'), token('--c-danger'), accent],
                        borderColor: surface,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '66%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: text, usePointStyle: true, boxWidth: 8, padding: 16 } },
                        tooltip: tooltip
                    }
                }
            }));
        }

        var track = D.track || { labels: [], data: [] };
        var tc = document.getElementById('trackChart');
        if (tc) {
            charts.push(new Chart(tc, {
                type: 'bar',
                data: { labels: track.labels, datasets: [{ label: 'Applications', data: track.data, backgroundColor: accent, borderRadius: 4, maxBarThickness: 40 }] },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: line } },
                        y: { ticks: { color: text }, grid: { display: false } }
                    },
                    plugins: { legend: { display: false }, tooltip: tooltip }
                }
            }));
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build); else build();
    document.addEventListener('pcg:theme', build);
})();
