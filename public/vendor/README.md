# Vendored front-end libraries

Served locally instead of from CDNs, so the app works on a private network
without internet access and no third-party scripts run on its pages.
Copied unmodified from the npm packages below; each file keeps its own
licence header.

| Folder | Package | Version | Licence |
|--------|---------|---------|---------|
| jquery | jquery | 3.7.1 | MIT |
| datatables | datatables.net, -dt, -responsive, -responsive-dt | 2.3.2 / 3.0.5 | MIT |
| sweetalert2 | sweetalert2 | 11.22.0 | MIT |
| chartjs | chart.js | 4.4.4 | MIT |
| fontawesome | @fortawesome/fontawesome-free | 6.5.2 | Icons CC BY 4.0, fonts SIL OFL 1.1, code MIT |
| leaflet | leaflet | 1.9.4 | BSD-2-Clause |
| leaflet-geocoder | leaflet-control-geocoder | 2.4.0 | BSD-2-Clause |
| alpinejs | alpinejs | 3.14.9 | MIT |
| bootstrap | bootstrap | 5.3.1 | MIT |
| qrcodejs | qrcodejs | 1.0.0 | MIT |
| inter | @fontsource/inter (latin 400/600/700/800) | 5.1.1 | SIL OFL 1.1 |

To update one: `npm install <package>@<version>` in a scratch folder, copy the
same dist file over the old one, rename it with the new version, and update
the `asset('vendor/...')` references in `resources/views`.

`public/css/tailwind.css` is compiled from the Blade views:
`npm install && npm run build:css` (only needed when you use a Tailwind class
that is not used anywhere yet).
