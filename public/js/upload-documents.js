/* Member: required documents. Shows chosen file names, counts them, drag and drop,
   the residence map (Leaflet + address search), and the upload overlay. */
(function () {
    'use strict';
    var form = document.getElementById('uploadForm');
    if (!form) return;
    var files = form.querySelectorAll('.ud-file');

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
        document.getElementById('ud-count').textContent = n === 0 ? 'No new files chosen' : n + (n === 1 ? ' file' : ' files') + ' ready to upload';
    }
    files.forEach(function (input) {
        // pcg-ui.js checks the file type first (and clears a wrong one); then update the label
        input.addEventListener('change', function () { setTimeout(refresh, 0); });
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
    form.addEventListener('submit', function (e) {
        if (!e.defaultPrevented) document.getElementById('ud-overlay').hidden = false;
    });
    window.addEventListener('pageshow', function () { document.getElementById('ud-overlay').hidden = true; refresh(); });

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
    }
    if (saved) setCoords(lat, lng); else document.getElementById('coords').textContent = 'Not pinned yet.';
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
