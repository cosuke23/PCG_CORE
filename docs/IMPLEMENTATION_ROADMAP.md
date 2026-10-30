# Implementation Roadmap — PCG_CORE

Legend: ✅ done & verified · 🟡 done, needs verification on your PC / real data ·
⬜ not started · ⛔ blocked (reason) · 🔒 needs your approval · 👤 only you can do it

Last updated: 2026-10-30 · branch `claude/cool-wozniak-rni95k`

## Your action list (in order)

1. 👤 **Change the leaked admin password everywhere it is used**, make the GitHub repo private (SECURITY_AUDIT §0, A1–A2).
2. 👤 On your PC: `scripts\windows\setup.ps1`, set `.env` (DB, `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`), then `php artisan pcg:check` until all `[ OK ]`. **PHP 8.2+ is required** (Laravel 12).
2b. 👤 Upgrade PHP to 8.5.12 — `docs/PHP_UPGRADE_WINDOWS.md` (verified compatible).
2c. 👤 Back up, `php artisan migrate`, `php artisan pcg:create-admin`, sign in and set up two-factor (SECURITY_AUDIT A8).
3. 👤 Run `database/audit/inspect_schema.sql` in phpMyAdmin and send me the output — the only way to confirm the inferred schema matches `pcg_core`.
4. 👤 Admin → **Unit accounts**: set passwords where flagged, **Grant M1 access** to M1 checkers (A6, A6b).
5. 👤 Move uploaded files out of `public/` and set `PCG_UPLOAD_ROOT` (A7) — production data, so your call.
6. 👤 `tailscale serve --bg http://127.0.0.1:8000`, then fill in DEPLOYMENT_GUIDE §6.
7. 👤 Copy the missing images/videos from production (FEATURE_INVENTORY → Missing files).

## Phase 1 — Discovery ✅
- ✅ Architecture, database (inferred), security, feature docs
- ✅ Schema reconstructed from code: `database/dev/schema_inferred.sql` (40 tables)
- ⛔ Real schema: needs `inspect_schema.sql` output from you
- ⛔ Visual reference https://cgmeptdi.com/ blocked by this session's network policy

## Phase 2 — Planning ✅

## Phase 3 — Backup
- ✅ Git history on a branch; baseline `83459a9`
- 👤 `mysqldump` before any schema change (DEPLOYMENT_GUIDE §7)

## Phase 4 — Security ✅ (code side complete)
- ✅ Hardcoded credentials removed; access control on every route; unit password check; rejected-user bypass; upload validation; serial IDOR; PII out of logs; transactions; security headers; throttling
- ✅ Personnel files served only via access-checked `/files/{path}`; direct access blocked in `.htaccess`
- ✅ Own-unit vs M1 checkers separated (role, middleware, review and file scoping)
- ✅ Stored XSS fixed (rejection comments; registrant data in admin dialog); password hashes removed from admin page
- ✅ **Laravel 9.52 → 12**; `composer audit` 50 advisories → 0; Ignition removed
- 🔒 Purge leaked secret from git history (rewrite)
- ✅ Admin accounts in the database with roles + mandatory TOTP 2FA + audit log (approved 2026-10-30)
- ✅ Content-Security-Policy with nonces; 41 inline handlers converted; 0 violations
- ✅ Separate rate limiters per login form (shared counter locked admins out)
- 🔒 Random verification tokens for OSAERT certificates (schema change + reprint)

## Phase 5 — UI/UX
- ✅ Design system `public/css/pcg-ui.css` + `public/js/pcg-ui.js` (no build, no CDN)
- ✅ All sign-in pages; public certificate verification pages
- ✅ Admin app shell (one sidebar, top bar, messages) on all admin pages; six copied sidebars removed
- ✅ New Unit accounts page
- ✅ Unit pages: real navigation, messages, logo
- ✅ All CDN assets self-hosted (Tailwind compiled, jQuery, DataTables, SweetAlert2, Chart.js, Font Awesome, Leaflet, Alpine, Inter font)
- ⬜ Restyle page content of legacy admin/unit/applicant pages with pcg-ui components (works today with compiled Tailwind)
- ✅ Personnel app shell + redesigned personnel dashboard; admin dashboard summary
- ⬜ Move remaining personnel pages (SOI form, uploads, training) into the personnel shell
- ⛔ Comparison with cgmeptdi.com (network)

## Phase 6 — Functional fixes ✅
- ✅ Unit review route; admin Reject button; add/remove student; student list; OSAERT verify page, certificate page, year filters and numbers; `/upload-201` 500; training-courses JS error; SOI undefined components; logout bugs; disciplinary delete; dead routes and links
- ✅ Contact form built (stored + admin inbox)
- ✅ Face verification removed (unfinished, unlinked)
- ⬜ Track create/edit UI (routes exist for operational tracks only)
- ⬜ Delete dead controllers and backup views (after your confirmation)

## Phase 7 — Database validation
- ✅ Dev database: `schema_inferred.sql` + `php artisan pcg:dev-seed` (refuses anything but `*_dev`)
- ⛔ Real schema comparison (needs your `inspect_schema.sql` output)
- ✅ Lookup indexes migration (approved; tested add / re-run / rollback)
- ✅ Missing training/LMS tables migration (`000005`): the owner's local `pcg_core` had none of the 7 training tables; creates only missing ones, empty (tested add / re-run / rollback keeps data, MariaDB)
- 🔒 Normalise status casing; OSAERT dates → DATE

## Phase 8 — Testing ✅
- ✅ 70 PHPUnit tests (access control, logins, uploads, files, unit/M1, OSAERT, transactions, route integrity)
- ✅ Browser end-to-end (`tests/e2e`): 47 pages × roles with no PHP/JS errors; 34 form-flow checks
- 🟡 Same suites against a copy of the real data

## Phase 9 — Localhost (Windows) 🟡
- ✅ Scripts, `pcg:check`, docs; app verified on PHP 8.3 + MariaDB 10.11 (Linux)
- 👤 Run on your Windows PC (action list 2)

## Phase 10 — Tailscale 🟡
- ✅ Guide, helper script, trusted-proxy support (simulated)
- 👤 Run and validate (action list 6)

## Phase 11 — Documentation ✅ (living)

## Test results log

| Date | Item | Result |
|------|------|--------|
| 2026-10-30 | `php artisan test` (Laravel 12) | **Passed** — 70 tests, 217 assertions |
| 2026-10-30 | `php artisan test` after admin accounts/2FA, CSP, contact | **Passed** — 91 tests on PHP 8.3.6 and on PHP 8.5.12 |
| 2026-10-30 | Browser suite on PHP 8.5.12 with CSP enforced | **Passed** — 51 pages, 0 CSP violations; 25 admin (incl. 2FA enrolment), 16 user, 2 registration checks; no deprecations |
| 2026-10-30 | Index migration on an index-free schema copy | **Passed** — add, re-run no-op, rollback exact |
| 2026-10-30 | `composer audit` | **Passed** — no advisories |
| 2026-10-30 | Browser crawl, every page × role (dev DB) | **Passed** — 47 pages; only external map tiles fail (no internet in sandbox) |
| 2026-10-30 | Admin flows (approve, reject, unit accounts, courses, students, CSV, OSAERT, applications) | **Passed** — 18/18, twice |
| 2026-10-30 | Unit/M1/personnel flows (review, disciplinary, uploads, own files, SOI save, training apply) | **Passed** — 14/14 |
| 2026-10-30 | Self-registration with camera selfie | **Passed** |
| 2026-10-30 | `config:cache`, `route:cache`, `view:cache` | **Passed** |
| 2026-10-30 | Apache 2.4 virtual host as documented (`public/` docroot, `AllowOverride All`, `Require local`): crawl + all flows | **Passed** — same results as `artisan serve` |
| 2026-10-30 | Apache: direct requests to `documents_toupload/`, `201_files/`, `selfies/` | **Passed** — 403; `/files/...` requires sign-in; `/.env` 404 |
| 2026-10-30 | Training tables migration on a schema copy missing 6 of 7 tables | **Passed** — creates the 6, leaves the existing one, re-run no-op, rollback keeps a table with data |
| — | Real `pcg_core` data | **Blocked** — needs access on your PC |
| — | Windows scripts, Tailscale | **Not tested** — no Windows/Tailscale in this environment |
