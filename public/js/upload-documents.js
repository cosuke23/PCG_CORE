/* Member upload pages (Required documents, 201 files). Shows chosen file names, counts them, drag and drop,
   the residence map (Leaflet + address search), and the upload overlay. */
(function () {
    'use strict';
    var form = document.getElementById('uploadForm');
    if (!form) return;
    var files = form.querySelectorAll('.ud-file');
    var MAX = 10 * 1024 * 1024; // the server's limit (10 MB per file)
    var overlay = document.getElementById('ud-overlay');
    var count = document.getElementById('ud-count');
    var tracked = form.querySelectorAll('input[type=date], #latitude, #longitude');
    var initial = {};
    tracked.forEach(function (el) { initial[el.name] = el.value; }); // values as saved
    function chosen() { var n = 0; files.forEach(function (f) { if (f.files && f.files.length) n++; }); return n; }
    function changedFields() { var c = 0; tracked.forEach(function (el) { if (el.value !== initial[el.name]) c++; }); return c; }

    function refresh() {
        var n = 0;
        files.forEach(function (input) {
            var drop = input.nextElementSibling;
            var text = drop.querySelector('.ud-drop__text');
            if (input.files && input.files.length) {
                n++;
                text.textContent = input.files[0].name;
                drop.classList.add('is-chosen');
            } else {
                text.textContent = text.getAttribute('data-default');
                drop.classList.remove('is-chosen');
            }
        });
        count.classList.remove('is-warn');
        var c = changedFields();
        count.textContent = n ? n + (n === 1 ? ' file' : ' files') + ' ready to upload' : (c ? 'Changes ready to save' : 'No new files chosen');
    }
    files.forEach(function (input) {
        // pcg-ui.js checks the file type first (and clears a wrong one); then update the label
        input.addEventListener('change', function () {
            setTimeout(function () {
                var f = input.files && input.files[0];
                if (f && f.size > MAX) {
                    alert('This file is ' + (f.size / 1048576).toFixed(1) + ' MB. The limit is 10 MB per file. Please choose a smaller file.');
                    input.value = '';
                }
                refresh();
            }, 0);
        });
        var drop = input.nextElementSibling;
        ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-dragover'); }); });
        ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-dragover'); }); });
        drop.addEventListener('drop', function (e) {
            e.preventDefault();
            if (!e.dataTransfer || !e.dataTransfer.files.length) return;
            var dt = new DataTransfer();
            dt.items.add(e.dataTransfer.files[0]);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    tracked.forEach(function (el) { el.addEventListener('change', refresh); });
    form.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        var n = chosen();
        if (!n && !changedFields()) {
            e.preventDefault();
            count.textContent = tracked.length ? 'Nothing to save yet: choose a file, change a date or move the pin.' : 'Nothing to upload yet: choose a file first.';
            count.classList.add('is-warn');
            return;
        }
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        if (n) {
            // only real uploads get the full-screen notice
            overlay.querySelector('p').textContent = 'Encrypting and uploading ' + n + (n === 1 ? ' file' : ' files') + '…';
            overlay.hidden = false;
            overlay.classList.add('is-on');
        } else {
            btn.textContent = 'Saving…';
        }
    });
    window.addEventListener('pageshow', function () {
        overlay.classList.remove('is-on');
        overlay.hidden = true;
        document.getElementById('submitBtn').disabled = false;
        refresh();
    });

    // ---- residence map
    var mapEl = document.getElementById('map');
    if (!mapEl || typeof L === 'undefined') return;
    var latIn = document.getElementById('latitude'), lngIn = document.getElementById('longitude');
    var saved = mapEl.getAttribute('data-lat') !== '' && mapEl.getAttribute('data-lng') !== '';
    var lat = saved ? parseFloat(mapEl.getAttribute('data-lat')) : 13.41;
    var lng = saved ? parseFloat(mapEl.getAttribute('data-lng')) : 122.56;
    var map = L.map('map').setView([lat, lng], saved ? 15 : 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
    var marker = L.marker([lat, lng], { draggable: true }).addTo(map);
    function setCoords(a, b) {
        latIn.value = a; lngIn.value = b;
        document.getElementById('coords').textContent = 'Pinned at ' + a.toFixed(6) + ', ' + b.toFixed(6);
        refresh();
    }
    // show the saved pin without rewriting the saved numbers
    document.getElementById('coords').textContent = saved ? 'Pinned at ' + lat.toFixed(6) + ', ' + lng.toFixed(6) : 'Not pinned yet.';
    marker.on('dragend', function () { var p = marker.getLatLng(); setCoords(p.lat, p.lng); });
    map.on('click', function (e) { marker.setLatLng(e.latlng); setCoords(e.latlng.lat, e.latlng.lng); });
    if (L.Control.geocoder) {
        L.Control.geocoder({ defaultMarkGeocode: false }).on('markgeocode', function (e) {
            map.setView(e.geocode.center, 16); marker.setLatLng(e.geocode.center); setCoords(e.geocode.center.lat, e.geocode.center.lng);
        }).addTo(map);
    }
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            L.circleMarker([pos.coords.latitude, pos.coords.longitude], { radius: 7, color: '#0e7490', fillOpacity: .6 })
                .addTo(map).bindTooltip('Your current location');
        }, function () { /* permission refused: nothing to show */ });
    }
})();
