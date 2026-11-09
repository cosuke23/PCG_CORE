/* HR analytics (admin/hr-analytics): charts drawn with the console tokens, one accent
   plus neutrals; status colours only for approved / pending / rejected. Redrawn on theme change. */
(function () {
    'use strict';

    var dataEl = document.getElementById('hra-data');
    if (!dataEl) return;
    var D = {};
    try { D = JSON.parse(dataEl.textContent || '{}'); } catch (err) { D = {}; }

    document.querySelectorAll('[data-hra-height]').forEach(function (box) {
        var h = parseInt(box.getAttribute('data-hra-height'), 10);
        if (h > 0) box.style.height = h + 'px';
    });

    function token(name) { return getComputedStyle(document.body).getPropertyValue(name).trim(); }
    function set(key) { var s = D[key] || {}; return { labels: s.labels || [], data: s.data || [], keys: s.keys || [] }; }

    var charts = [];
    function build() {
        if (typeof Chart === 'undefined') return;
        charts.forEach(function (c) { c.destroy(); });
        charts = [];
        var text = token('--c-text'), muted = token('--c-muted'), faint = token('--c-faint'), grid = token('--c-line-soft'),
            surface = token('--c-surface'), accent = token('--c-accent'), accentSoft = token('--c-accent-soft');
        Chart.defaults.color = muted;
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        var tooltip = { backgroundColor: surface, titleColor: text, bodyColor: text, borderColor: token('--c-line'), borderWidth: 1, padding: 10 };
        var legend = { position: 'bottom', labels: { color: text, usePointStyle: true, boxWidth: 8, padding: 16 } };

        function doughnut(id, s, colours) {
            var el = document.getElementById(id);
            if (!el) return;
            charts.push(new Chart(el, {
                type: 'doughnut',
                data: { labels: s.labels, datasets: [{ data: s.data, backgroundColor: colours, borderColor: surface, borderWidth: 3 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: legend, tooltip: tooltip } }
            }));
        }
        function bars(id, s, horizontal) {
            var el = document.getElementById(id);
            if (!el) return;
            var valueAxis = { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: grid } };
            var catAxis = { ticks: { color: text, autoSkip: false }, grid: { display: false } };
            charts.push(new Chart(el, {
                type: 'bar',
                data: { labels: s.labels, datasets: [{ label: 'Personnel', data: s.data, backgroundColor: accent, borderRadius: 4, maxBarThickness: 28 }] },
                options: {
                    indexAxis: horizontal ? 'y' : 'x', responsive: true, maintainAspectRatio: false,
                    scales: horizontal ? { x: valueAxis, y: catAxis } : { x: catAxis, y: valueAxis },
                    plugins: { legend: { display: false }, tooltip: tooltip }
                }
            }));
        }

        var statusColour = { approved: token('--c-ok'), pending: token('--c-warn'), rejected: token('--c-danger') };
        var status = set('status');
        doughnut('statusChart', status, status.keys.map(function (k) { return statusColour[k] || faint; }));

        var type = set('type');
        doughnut('registrationChart', type, type.labels.map(function (l) {
            return l === 'Officer' ? accent : (l === 'Non-officer' ? faint : grid);
        }));

        bars('unitChart', set('unit'), true);
        bars('rankChart', set('rank'), true);
        bars('academicChart', set('course'), true);

        var yearly = set('yearly');
        var yc = document.getElementById('yearlyChart');
        if (yc) {
            charts.push(new Chart(yc, {
                type: 'line',
                data: { labels: yearly.labels, datasets: [{ label: 'Registrations', data: yearly.data, borderColor: accent, backgroundColor: accentSoft, pointBackgroundColor: accent, tension: 0.3, fill: true }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: text }, grid: { display: false } },
                        y: { beginAtZero: true, ticks: { precision: 0, color: muted }, grid: { color: grid } }
                    },
                    plugins: { legend: { display: false }, tooltip: tooltip }
                }
            }));
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', build); else build();
    document.addEventListener('pcg:theme', build);
})();
