# Security Audit — PCG_CORE (MEPCOM HRMIS)

Audit date: 2026-10-30 · Baseline commit: `83459a9` ("first commit") · Branch: `claude/cool-wozniak-rni95k`

Scope: source code in this repository. The live `pcg_core` database and the
production server (`pcg-mepcom.com`) were **not** accessed; findings about them
are marked *(needs verification)*.

Severity: **Critical** = direct compromise of accounts or personnel data;
**High** = serious weakness with a realistic path to abuse; **Medium/Low** =
defence-in-depth.

---

## 0. Immediate actions for the owner (cannot be done from code)

| # | Action | Why |
|---|--------|-----|
| A1 | **Change the admin password everywhere it is used** — including any e-mail, hosting, cPanel or database account that shares it. | The plaintext admin password was committed in `AdminLoginController.php` and `AdminController.php` in commit `83459a9`, and **the GitHub repository `cosuke23/PCG_CORE` is public**. Removing it from the code does not remove it from git history or from anyone who already cloned it. |
| A2 | Make the GitHub repository **private** (Settings → General → Danger Zone → Change visibility). | Repository also discloses the production server account path (`/home/sghwkf6wslnz/...`), table names and business logic. |
| A3 | Decide whether to purge the secret from git history (e.g. `git filter-repo`). | Rewriting history is destructive and needs your explicit approval; rotation (A1) is what actually removes the risk. |
| A4 | On production, check that `https://pcg-mepcom.com/.env`, `/storage/logs/laravel.log`, `/composer.json` and `/public/documents_toupload/` are **not** reachable *(needs verification)*. | The views link to `https://pcg-mepcom.com/public/...`, which means the web server's document root is the project root, not `public/`. That layout exposes everything unless a root `.htaccess` blocks it. |
| A5 | Set `ADMIN_EMAIL` / `ADMIN_PASSWORD_HASH` in every `.env` (local and production) before deploying this branch. | Admin login now fails closed when these are missing. |
| A6 | Ensure every row in `unit_users` has a bcrypt password. | Unit login now verifies the password; accounts without one cannot log in. |

---

## 1. Findings and status

### Critical

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| C1 | Hardcoded plaintext admin credentials (two accounts) | `AdminLoginController::login`, `AdminController` private props | **Fixed** — credentials from `.env`, bcrypt-verified, session regenerated, throttled (5/min). Rotation still required (A1). |
| C2 | No access control on almost every route: admin dashboard, user approve/reject, HR analytics, training CRUD, OSAERT bulk upload, unit dashboards, 201 files, disciplinary upload/delete, `/unit/201-files/{serial}` JSON (full personnel record), `/unit/search-serial/{serial}` | `routes/web.php` (only `/dashboard` and `/documents/upload` were behind `auth`) | **Fixed** — route groups with `auth`, `admin.session`, `unit.session`; covered by `tests/Feature/AccessControlTest.php`. |
| C3 | Unit-checker login checked e-mail only; the password field on the form was ignored | `UnitUserController::login`, `loginM1` | **Fixed** — `Hash::check` against `unit_users.password`, session regenerated, throttled. |
| C4 | Rejected applicants could log in: admin writes `Rejected`, login compared with `rejected` and any other non-`Approved` status fell through to a successful login | `Auth\LoginController::login` | **Fixed** — case-insensitive; anything other than `approved` is refused. Tested. |
| C5 | Unrestricted file upload into the web root: `/documents/upload` had no server-side type validation and kept the client file name (`time()_<original name>`) | `ApplicantDocumentController::store` | **Fixed** — `mimes`/`max` validation per field, file names sanitised. Tested with a `.php` upload. |
| C6 | Any logged-in (or, before C2, anonymous) user could overwrite another member's profile records by sending a different `serial_number` | `TempRegistrationController::saveStep1`, `ReenlistController::store` | **Fixed** — serial number forced to the logged-in account's. |
| C7 | Sensitive personnel documents (NBI/Ombudsman/Sandiganbayan clearances, medical, 201 files, disciplinary records, selfies) are stored under the public web root and served without authentication. File names are only `time()` + original name, so they are guessable. | Upload handlers write to `public/documents_toupload`, `public/201_files`, `public/disciplinary_actions`; views link to them directly | **Open** — needs migration to private storage with an authorised download controller (roadmap Phase 6). Moving the existing files touches production data, so it needs your approval. |
| C8 | Production document root appears to be the project root, exposing `.env`, logs and source *(needs verification)* | URLs `https://pcg-mepcom.com/public/...` in views | **Open** — server configuration (A4). |

### High

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| H1 | Passwords and the base64 selfie were written to the log on every registration (`$request->all()`) | `RegisterController::register` | **Fixed** — logs e-mail and serial only. |
| H2 | Selfie upload accepted any base64 payload and wrote it with `chmod 0777` | `RegisterController::register` | **Fixed** — must decode to PNG/JPEG, random file name, `0644`. |
| H3 | Upload folders created `0777` | upload handlers | **Fixed** — `0755`. |
| H4 | Hardcoded production file-system paths (`/home/sghwkf6wslnz/...`) made uploads fail anywhere else and leaked the hosting account name | 4 controllers | **Fixed** — `PCG_UPLOAD_ROOT`, `PCG_SELFIE_DIR`, `PCG_UPLOAD_URL` in `config/pcg.php`. |
| H5 | No login rate limiting | routes | **Fixed** — `throttle` on all login/registration POSTs. |
| H6 | Session fixation: admin and unit logins did not regenerate the session | controllers | **Fixed**. |
| H7 | Outdated, end-of-life framework: Laravel 9.52 (security support ended Feb 2024). `composer audit` reports 50 advisories in 14 packages, including 1 critical and 1 high in `laravel/framework`, plus `symfony/http-foundation`, `league/commonmark`, `guzzlehttp/guzzle`. | `composer.lock` | **Open** — roadmap Phase 4b (upgrade to a supported Laravel; needs regression testing against the real DB). |
| H8 | Extensive PII logging: full request payloads (addresses, government IDs, dependents) logged by `ReenlistController`, `TempRegistrationController`, `UnitDocumentController`, `UnitUserController::storeDisciplinary` | `Log::info(... $request->all())` | **Fixed** — payload dumps removed from Register, Reenlist, profile save steps 1–6, unit review and disciplinary upload; logs keep IDs and counts only. (CSV-import logs still record trainee names supplied by the admin.) |
| H9 | Unit checkers can see/act on any unit's personnel: `dashboardM1`, `index_201`, `show_201`, `searchSerial`, review/disciplinary endpoints are not scoped by `unit_name` / role | `UnitUserController` | **Open** — needs your rule for which unit roles may see which units. |
| H10 | `/unit-users/store` lets the admin create unit accounts, but no admin UI calls it; previously it was public and unvalidated | `UnitUserController::store` | **Fixed** (admin-only + validation). |

### Medium / Low

| ID | Finding | Status |
|----|---------|--------|
| M1 | No security headers | **Fixed** — `SecurityHeaders` middleware (X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS). |
| M2 | No Content-Security-Policy; ~20 CDN scripts loaded without SRI, including the Tailwind *Play* CDN (not for production) and unpinned `alpinejs@3.x.x`, `chart.js`, `sweetalert2@11` | Open — Phase 5 (bundle assets with Vite, then add CSP). |
| M3 | Exception messages returned to users (`'Upload failed: ' . $e->getMessage()`) | Fixed in `storeDisciplinary`; review others. |
| M4 | `GET /admin/logout` and `GET /unit/logout` (logout via GET is CSRF-able) | Kept for the existing sidebar links; low impact. Convert to POST forms in Phase 5. |
| M5 | Admin session flag has no idle timeout separate from `SESSION_LIFETIME` (120 min) | Open. |
| M6 | No MFA for admin | Open — recommend TOTP once admin moves to a real user table. |
| M7 | `.gitignore` was a PowerShell script fragment; `storage/` missing from the repo | Fixed. |
| M8 | `spatie/laravel-ignition` exposes `/_ignition/*` when `APP_DEBUG=true` | Keep `APP_DEBUG=false` outside development; install with `composer install --no-dev` on servers. |
| L1 | DOM `innerHTML` built from server data: rejection comments shown to applicants (stored XSS), names, file URLs in unit dashboards and 201 files | **Fixed** — `escapeHtml()` applied in `applicant/dashboard`, `unit/dashboard`, `unit/dashboard_m1`, `unit/201_files_index`. |
| L2 | Blade output is escaped with `{{ }}` throughout; no `{!! !!}` with user data found | OK. |
| L3 | CSRF: all POST forms include `@csrf`; `VerifyCsrfToken` has no exclusions | OK. |
| L4 | SQL: queries use the query builder / bindings; the `whereRaw` calls use `?` placeholders | OK — no SQL injection found. |

---

## 2. Remediation checklist

- [x] Remove hardcoded admin credentials; `.env` + bcrypt (`php artisan pcg:hash-password`)
- [x] Route-level access control for applicant / unit / admin areas
- [x] Unit login verifies password
- [x] Rejected/pending/unknown-status users cannot log in
- [x] Server-side upload validation and file-name sanitising
- [x] Serial-number IDOR on profile writes
- [x] Stop logging passwords and selfies
- [x] Login throttling, session regeneration, security headers
- [x] Configurable upload paths/URLs; trusted proxy for Tailscale
- [x] Automated tests (`php artisan test`, 48 tests)
- [ ] **Owner:** rotate the leaked password (A1), make repo private (A2)
- [ ] **Owner:** verify production document root (A4)
- [ ] Move personnel documents to private storage + authorised download route (C7)
- [ ] Scope unit-checker data access by unit/role (H9)
- [x] Remove PII from logs (H8)
- [x] Escape server data in `innerHTML` (L1)
- [x] Transactions around profile delete-and-reinsert saves
- [ ] Upgrade Laravel / dependencies (H7)
- [ ] Self-host front-end assets, add CSP and SRI (M2)
- [ ] Admin accounts in the database with roles + MFA (M6)

## 3. Unresolved risks (accepted until fixed)

1. Personnel documents remain publicly reachable by URL (C7) on any server where `public/` is web-accessible.
2. Unit checkers are not restricted to their own unit (H9).
3. Known CVEs in the framework and libraries (H7).
4. Single shared admin identity, no audit trail of who approved/rejected whom.
