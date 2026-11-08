/* Member training tracks: course-package details and the apply form. Text only via textContent. */
(function () {
    'use strict';
    var dialog = document.getElementById('tt-dialog');
    if (!dialog) return;
    var $ = function (id) { return document.getElementById(id); };
    var last = null;

    function open(card) {
        var d;
        try { d = JSON.parse(card.getAttribute('data-track')); } catch (e) { return; }
        last = card;
        $('tt-d-eyebrow').textContent = d.type + ' · ' + (d.rank || '');
        $('tt-d-title').textContent = (d.unit ? d.unit.split('/')[0].trim() + ' course package' : 'Course package');
        var badge = $('tt-d-status');
        badge.textContent = d.statusLabel;
        badge.className = 'tt-badge tt-badge--' + d.status;
        var ol = $('tt-d-courses'); ol.textContent = '';
        (d.courses || []).forEach(function (c) { var li = document.createElement('li'); li.textContent = c; ol.appendChild(li); });
        var dl = $('tt-d-meta'); dl.textContent = '';
        [['Track rank', d.rank], ['Division / unit', d.unit], ['Year in grade', d.year], ['Provider', d.provider]].forEach(function (row) {
            if (!row[1]) return;
            var div = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
            dt.textContent = row[0]; dd.textContent = row[1]; div.appendChild(dt); div.appendChild(dd); dl.appendChild(div);
        });
        var note = $('tt-d-remarks');
        note.hidden = !d.remarks;
        note.textContent = d.remarks ? 'Note: ' + d.remarks : '';
        // only a package you have not applied for yet can be applied for
        $('tt-d-form').hidden = d.status !== 'open';
        $('tt-d-id').value = d.id;
        $('tt-d-type').value = d.type;
        if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
        $('tt-d-close').focus();
    }
    function close() { if (dialog.open) { if (dialog.close) dialog.close(); else dialog.removeAttribute('open'); } }

    document.querySelectorAll('.tt-card[data-track]').forEach(function (card) {
        card.addEventListener('click', function () { open(card); });
        card.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(card); } });
    });
    $('tt-d-close').addEventListener('click', close);
    $('tt-d-cancel').addEventListener('click', close);
    dialog.addEventListener('click', function (e) { if (e.target === dialog) close(); });
    dialog.addEventListener('close', function () { if (last) last.focus(); });
    $('tt-d-form').addEventListener('submit', function () {
        var b = this.querySelector('[type=submit]'); b.disabled = true; b.textContent = 'Sending application…';
    });
})();
