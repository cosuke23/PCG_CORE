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

- ✅ AES-256-GCM encryption of every uploaded file (envelope keys, streaming, audit of views and downloads, key rotation, backup and restore checks) — `docs/FILE_ENCRYPTION.md`
- 👤 Hermes: back up the key ring off the server after the first release; label `shared/keys` for SELinux; `php artisan pcg:files encrypt --apply` after a backup (FILE_ENCRYPTION procedure 1)

- ✅ Full re-check 2026-11-06 (SECURITY_AUDIT §0h): Symfony 7.4.23; /files re-checks admin sessions; photos need `documents.view`; checker accounts are created and reset only by admins with `documents.restricted`; member status re-checked per request; maker-checker counts editors; key ring never auto-created; random upload names
- 👤 Hermes: re-run `deploy/server-setup.sh` as root once (key ring group without nginx)
- ✅ Reverse-engineering review 2026-11-07 (SECURITY_AUDIT §0i): unit decisions change one row; CSP `strict-dynamic`; dispatcher locked down; member write/upload limits; bounded SOI lists; training rank check; approval lock; selfie re-encode; Apache/nginx deny rules; unused npm build tools removed; `deploy/audit-server.sh`; PHP `open_basedir`
- ✅ Registration fixed on Hermes (2026-11-08): users.mother_unit/sub_unit/unit_assignment widened to VARCHAR(255) (migration 2026_11_08_000001, reversible, owner request); the page shows every refusal by step; password eye toggle and strength meter; database errors logged without SQL values
- ✅ Checker accounts on Hermes (2026-11-08): create/M1/password/status fit the real unit_users columns; migration 2026_11_08_000002 adds m1_checker to the role ENUM (reversible, owner approval pending); `pcg:schema-report` (read-only) to align the dev schema with Hermes
- ✅ Admin redesign (2026-11-08): dark/light console theme for every admin page (`pcg-console.css`); HRMIS overview rebuilt as an interactive dashboard (clickable KPI cards and bars as combinable filters, search, sort, person details, 30-day movement chart, scoped and audited CSV export); Edit Profile fix
- ✅ Adjutant module (2026-11-08): role `adjutant` with OTP sign-in at /adjutant/login; records Coast Guard awards and decorations and Coast Guard schooling in members' SOI, scoped to its command/unit, audited; members see them read-only; SOI Education & Trainings save fixed (every training was refused)
- 👤 Hermes: create Adjutant accounts (Admin accounts → role Adjutant, access scope = command/unit) after `deploy.ps1 -Migrate`
- ✅ Member "Required documents" page redesigned (2026-11-08): grouped checklist with status (uploaded / expires soon / expired / not uploaded), progress, chosen-file names, drag and drop, AES-256 note, map script moved out of the page; residential sketch now accepts PDF/JPG/PNG like the server
- 👤 Hermes: `update-web-config.sh`, then `sudo bash ~/mepcom-hrmis-deploy/audit-server.sh` and send the output; owner decisions in §0i (email squatting, unique serial index, disciplinary soft delete, watchlist)

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
- ✅ Landing page redesigned in the cgmeptdi.com style (maroon/gold/cream/navy, serif headings) from the owner's screenshot; self-hosted font and images; fixed boat backdrop with panels scrolling over it, scroll dimming, solid-on-scroll header, reveal animations (`public/js/landing.js`, honours reduced motion)

## Phase 6 — Functional fixes ✅
- ✅ Unit review route; admin Reject button; add/remove student; student list; OSAERT verify page, certificate page, year filters and numbers; `/upload-201` 500; training-courses JS error; SOI undefined components; logout bugs; disciplinary delete; dead routes and links
- ✅ Contact form built (stored + admin inbox)
- ✅ Face verification removed (unfinished, unlinked)
- ✅ Uploading one 201 file no longer unlinks the member's other 201 files; uploading required documents no longer wipes expiry dates or the map location the form did not send (2026-11-05)
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

- ✅ Funnel (public) helper `scripts/windows/tailscale-funnel.ps1` + `server.php` upload block under `artisan serve` (owner chose public access 2026-10-30)
- 👤 Run `tailscale-funnel.ps1 on` on the PC (script not executed here: no Windows/PowerShell)

## Enterprise HRMIS (Functional Commands) — see `docs/HRMIS_ENTERPRISE_PLAN.md`
- ✅ Checkpoint 1: organisational hierarchy (`org_units`, Admin → Organization; tree, add/edit/move, activate/deactivate, cycle and duplicate-code checks, audited; 7 tests; browser-checked desktop + mobile)
- ✅ Checkpoints 2–4: personnel master, ranks, effective-dated assignments/transfers, `pcg:personnel-sync`, command-scoped HR admins with isolation tests
- ✅ D7 tool: `pcg:move-uploads` (copy + verify, never deletes)
- ✅ HRMIS overview (`/admin/hrmis`): personnel, officer/non-officer, 30-day movements, records needing attention, by unit and by rank, recent movements — all from the personnel master, limited to the admin's scope; landing page for command-scoped admins
- ✅ Roles (reviewer, approver, auditor) with a capability matrix; registration workflow; 201 file repository; promotions; training records; controlled editing; 11 scoped reports with CSV/print
- ⛔ Real-data run and PCG policy confirmations: see HRMIS_ENTERPRISE_PLAN §9
- 👤 On the PC: `git pull`, back up, `php artisan migrate`, `php artisan pcg:personnel-sync` (dry run) and send the output

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
| 2026-10-30 | Browser flows through the new `server.php` router (fresh dev DB) | **Passed** — 25 admin, 16 user, 2 registration; upload folders 404 (incl. `..`, `%5C`, case tricks), assets 200 |
| 2026-10-31 | Organisation module: `php artisan test` | **Passed** — 102 tests (7 new); MariaDB migrate OK; browser add-unit + confirm dialog, 0 JS/CSP errors |
| 2026-10-31 | Personnel master + scope: `php artisan test` | **Passed** — 119 tests (MEPCOM-vs-MSSC isolation, transfers keep history, duplicates, sync dry-run/apply, move-uploads) |
| 2026-10-31 | Browser: super admin creates MSSC person + transfer; MEPCOM-scoped admin sees 6/7, dashboard 403; crawl + all flows | **Passed** — crawl 51 ok (map tiles need internet), flows 25 + 16 + 2 |
| 2026-10-31 | HRMIS overview: `php artisan test` | **Passed** — 122 tests (scoped figures, org-wide figures, empty state); browser desktop + 390 px, 0 JS/CSP errors, no horizontal scroll |
| 2026-10-31 | HRMIS records modules: `php artisan test` | **Passed** — 144 tests (workflow, no self-approval, scope isolation, private/versioned/restricted documents, promotions, controlled edit, reports + CSV) |
| 2026-10-31 | Browser: `flow-hrmis.js` (4 roles), crawl 76 pages × 7 roles, flows-admin/users/register | **Passed** — 18/18, 76 ok (map tiles need internet), 25 + 16 + 2 |
| 2026-10-31 | MEPCOM unit import + sync mapping | **Passed** — 148 tests; dev MariaDB: 140 units imported, re-run creates nothing |
| 2026-11-02 | Hermes deploy kit (`deploy/`): bash -n + shellcheck, `nginx -t`, `php-fpm -t`, systemd unit verify; release build + migrate, probes (`/`, `/login`, `/admin/login` 200; `/.env` 403; upload folders 404), second release, broken release switched back automatically, `--rollback`, backup, UTF-16 dump import into an empty DB only | **Passed** (Debian sandbox) |
| 2026-11-02 | Kit on Hermes (Rocky 9.8, PHP 8.3.36): preflight, setup, data import, `deploy.ps1 -Migrate`, Funnel at `mepcom-hrmis.tail7d94c9.ts.net`, `verify.sh` | **Passed** — other apps identical before/after; `/`, `/login`, `/admin/login` 200; `/.env` 403; upload folders 404 |
| 2026-11-02 | Security review after going public (N1–N9 fixed, see SECURITY_AUDIT §0d): `php artisan test` | **Passed** — 163 tests, 746 assertions; `composer audit` no advisories |
| 2026-11-02 | `tests/e2e/responsive.js`: 54 pages × 7 sizes (320–1920 px) | **Passed** — no sideways scrolling except the fixed-size A4 SOI form; no iOS-zoom fields; remaining findings are 20 px text links |
| 2026-11-02 | Full security re-run before publication (SECURITY_AUDIT §0e, P1–P12): `php artisan test` | **Passed** — 166 tests; nginx + PHP-FPM config with a no-dev build: pages 200, `/files` reaches the app, blocked files 403/404, PUT/TRACE 405, 413 on oversized bodies, 429 under burst, foreign Host 400 |
| 2026-11-03 | Re-enlistment module (non-officers by ETE) + `reenlistment_admin` role and `/reenlistment/login`: `php artisan test` | **Passed** — 175 tests (6 new: 12-month window incl. boundary, overdue/no-ETE/all lists, filters, mixed-case data, officers and rejected excluded, CSV + audit, role isolation, auditor read-only); browser: sign-in lands on the module, only its menu, pages 200, no JS/CSP errors, no sideways scroll at 390 px |
| 2026-11-03 | Penetration test (SECURITY_AUDIT §0f, Q1–Q6 fixed): `php artisan test` + 24 black-box attacks on the production-like stack + stored-XSS check as 3 roles | **Passed** — 182 tests; 24/24 attacks blocked; no injected script ran |
| 2026-11-04 | `php artisan db:seed` (database/seeders, 7 seeders): every application table filled on a fresh `pcg_core_dev` | **Passed** — 586 rows across all 51 application tables (only the 3 framework tables stay empty, by design); re-run adds nothing; refuses APP_ENV=production and non-`_dev` databases (SeederGuardTest) |
| 2026-11-08 | Second reverse-engineering review (SECURITY_AUDIT §0j, W1–W6): `php artisan test`, pentest on the production-like copy, member browser flows | **Passed** — 238 tests; pentest 30/31 (expected plain-HTTP cookie result); flows-users 16/16; `composer audit` and `npm audit` clean |
| 2026-11-09 | Required documents locked while waiting for the unit checker (`DocumentReview`): `php artisan test`, member browser flows | **Passed** — 239 tests (new: page lock, server refusal, null status, unlock after return and after approval); flows-users 17/17 incl. the lock; desktop and phone: no JS/CSP errors, no sideways scroll |
| 2026-11-09 | Unit checkers can return the residence location; own-unit review lists all 10 documents: `php artisan test`, flows-users | **Passed** — 242 tests (location return clears only the pin, unticked changes nothing, new pin marks it complied, both windows list 10 documents + location); flows-users 20/20; M1 window checked in the browser |
| 2026-11-09 | Design pass 1–2: design rules in pcg-ui (accent, contrast, tabular numbers, balanced headings, radius rule, loading state on every form, feedback motion) + unit checker pages rebuilt on `layouts/unit` | **Passed** — 242 tests; flows-users 27/27 (filters, review dialog, return with comment, location, approve + highlighted row, M1 disciplinary upload, 201 viewer); flows-admin 25/25; crawl: every page OK (2 member pages log the sandbox's blocked map tiles); phone: no sideways scroll, no JS/CSP errors |
| — | Real `pcg_core` data | **Blocked** — needs access on your PC |
| — | Windows scripts, Tailscale | **Not tested** — no Windows/Tailscale in this environment |
