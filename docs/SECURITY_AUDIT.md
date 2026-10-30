# Security Audit — PCG_CORE (MEPCOM HRMIS)

Audit date: 2026-10-30 · Baseline commit: `83459a9` ("first commit") · Branch: `claude/cool-wozniak-rni95k` · Last updated: 2026-10-30 (Laravel 12 upgrade, M1 separation, file access control)

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
| A6 | Ensure every row in `unit_users` has a bcrypt password — **Admin → Unit accounts → Set password** (accounts without one are flagged). | Unit login now verifies the password; accounts without one cannot log in. |
| A6b | On **Admin → Unit accounts**, click **Grant M1 access** for the accounts that use the M1 (all units) login. | M1 access is now an explicit role; until granted, M1 users are refused at `/unit/loginM1`. |
| A8 | Back up the database, run `php artisan migrate` (adds `admin_users`, `admin_audit_logs`, `contact_messages` and lookup indexes — nothing existing is changed), then `php artisan pcg:create-admin` and sign in to set up two-factor. | Admin accounts with roles and 2FA replace the shared `.env` admin. |
| A7 | Move uploaded files out of the web root: copy `public/documents_toupload`, `public/201_files`, `public/disciplinary_actions` (and `selfies/`) into e.g. `storage/app/private/`, set `PCG_UPLOAD_ROOT` / `PCG_SELFIE_DIR`, run `php artisan pcg:check`, then remove the public copies. | Links already go through the access-checked `/files` route; this removes the files from the web server's reach entirely. Moving files is your call (production data). |

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
| C7 | Sensitive personnel documents (NBI/Ombudsman/Sandiganbayan clearances, medical, 201 files, disciplinary records, selfies) were stored under the public web root and served without authentication; file names were guessable (`time()` + original name). | Upload handlers wrote to `public/documents_toupload`, `public/201_files`, `public/disciplinary_actions`; views linked to them directly | **Fixed in code** — every link goes through `FileController` (`/files/{path}`): personnel get only their own files, own-unit checkers only their unit's required documents, M1 checkers and admins everything; traversal-safe; `public/.htaccess` blocks direct requests to those folders. **Owner step A7:** move existing files out of `public/` and set `PCG_UPLOAD_ROOT` (the `.htaccess` block does not apply to `php artisan serve`; the root `server.php` router now applies the same block there: only css/js/vendor/images/backgrounds/ph_json/storage are served directly). |
| C8 | Production document root appears to be the project root, exposing `.env`, logs and source *(needs verification)* | URLs `https://pcg-mepcom.com/public/...` in views | **Open** — server configuration (A4). |

### High

| ID | Finding | Evidence | Status |
|----|---------|----------|--------|
| H1 | Passwords and the base64 selfie were written to the log on every registration (`$request->all()`) | `RegisterController::register` | **Fixed** — logs e-mail and serial only. |
| H2 | Selfie upload accepted any base64 payload and wrote it with `chmod 0777` | `RegisterController::register` | **Fixed** — must decode to PNG/JPEG, random file name, `0644`. |
| H3 | Upload folders created `0777` | upload handlers | **Fixed** — `0755`. |
| H4 | Hardcoded production file-system paths (`/home/sghwkf6wslnz/...`) made uploads fail anywhere else and leaked the hosting account name | 4 controllers | **Fixed** — `PCG_UPLOAD_ROOT`, `PCG_SELFIE_DIR` in `config/pcg.php`. |
| H5 | No login rate limiting | routes | **Fixed** — `throttle` on all login/registration POSTs. |
| H6 | Session fixation: admin and unit logins did not regenerate the session | controllers | **Fixed**. |
| H7 | Outdated, end-of-life framework: Laravel 9.52 (security support ended Feb 2024). `composer audit` reported 50 advisories in 14 packages (1 critical). | `composer.lock` | **Fixed** — upgraded to Laravel 12 (PHP 8.2+), Sanctum 4, PHPUnit 11; `composer audit` reports no advisories. Verified by 70 tests and the browser end-to-end suite. |
| H8 | Extensive PII logging: full request payloads (addresses, government IDs, dependents) logged by `ReenlistController`, `TempRegistrationController`, `UnitDocumentController`, `UnitUserController::storeDisciplinary` | `Log::info(... $request->all())` | **Fixed** — payload dumps removed from Register, Reenlist, profile save steps 1–6, unit review and disciplinary upload; logs keep IDs and counts only. (CSV-import logs still record trainee names supplied by the admin.) |
| H9 | Unit checkers could see and act on any unit's personnel: all-units dashboard, 201 files, serial search, disciplinary upload, and approve/reject for any serial number | `UnitUserController`, `UnitDocumentController` | **Fixed** — M1 is an explicit role (`m1_checker`) granted on Admin → Unit accounts; M1 pages require it (`unit.m1`); own-unit checkers can only review and open files of their own unit. **Owner step A6b:** grant M1 to existing M1 accounts. |
| H10 | `/unit-users/store` was public and unvalidated | `UnitUserController::store` | **Fixed** (admin-only + validation; new Unit accounts page). |

| H11 | The admin dashboard embedded every user's **password hash** and remember token in the page (rows serialised `users.*` to JSON) | `AdminController::dashboard`, `admin/dashboard.blade.php` | **Fixed** — explicit column list. |
| H12 | Stored XSS against the admin: self-registered name/serial/unit were inserted as HTML into the approval dialog | `admin/dashboard.blade.php` | **Fixed** — escaped. |
| H13 | Public `/osaert/verify` was written to list every OSAERT trainee's name and company (the page itself was missing, so it 500'd) | `OsaertController::verify` | **Fixed** — lookup by certificate number only; public verification routes throttled (60/min). Residual: `/osaert/verify/{id}` uses sequential IDs (printed QR codes depend on them); see risk 5. |
| H15 | All `throttle:N,1` routes shared one per-IP counter: a few personnel logins from one office IP locked the admin out | `routes/web.php` | **Fixed** — named limiters per form, keyed by IP + email. |
| H16 | 20 MB of face-recognition model files and an unfinished face-verification endpoint were publicly reachable | `public/face-api-models`, `FaceVerificationController` | **Fixed** — removed (feature was never linked or finished). |
| H14 | Training tracks: a rank without an equivalent matched every track (`LIKE '%%'`) | `ApplicantTrainingController` | **Fixed**. |

### Medium / Low

| ID | Finding | Status |
|----|---------|--------|
| M1 | No security headers | **Fixed** — `SecurityHeaders` middleware (X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS). |
| M2 | No Content-Security-Policy; ~20 CDN scripts loaded without SRI | **Fixed** — all assets self-hosted; enforced CSP with a per-request nonce on all 38 inline scripts, no inline event handlers (41 converted to `data-on-*` delegation), nothing from other origins except OpenStreetMap tiles/geocoding; `object-src 'none'`, `form-action`/`frame-ancestors 'self'`. Residual: `'unsafe-eval'` for Alpine.js, `'unsafe-inline'` styles. `PCG_CSP=report` for report-only. Verified: 0 violations across the crawl and all flows. |
| M3 | Exception messages returned to users (`'Upload failed: ' . $e->getMessage()`) | Fixed in `storeDisciplinary`; review others. |
| M4 | `GET /admin/logout` and `GET /unit/logout` (logout via GET is CSRF-able) | Kept for the existing sidebar links; low impact. Convert to POST forms in Phase 5. |
| M5 | No audit trail; admin session had no separate idle timeout | **Partially fixed** — `admin_audit_logs` records sign-ins, failed attempts, 2FA events and every state-changing admin request (Admin → Audit log). Idle timeout still = `SESSION_LIFETIME` (120 min). |
| M6 | No MFA for admin; single shared admin identity in `.env` | **Fixed** — `admin_users` with roles (super/HR/LMS) and **mandatory TOTP two-factor** (RFC 6238, replay-protected, encrypted secret, 8 hashed recovery codes). The `.env` admin only works until the first account exists. |
| M7 | `.gitignore` was a PowerShell script fragment; `storage/` missing from the repo | Fixed. |
| M8 | `spatie/laravel-ignition` exposed `/_ignition/*` when `APP_DEBUG=true` | **Fixed** — package removed in the Laravel 12 upgrade. Keep `APP_DEBUG=false` outside development. |
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
- [x] Automated tests (`php artisan test`, 70 tests) + browser end-to-end suite (`tests/e2e`)
- [ ] **Owner:** rotate the leaked password (A1), make repo private (A2)
- [ ] **Owner:** verify production document root (A4)
- [x] Authorised download route for personnel documents (C7) — **owner:** move existing files (A7)
- [x] Scope unit-checker data access by unit/role (H9) — **owner:** grant M1 (A6b)
- [x] Upgrade Laravel / dependencies (H7): Laravel 12, `composer audit` clean
- [x] Self-host all front-end assets (M2, part 1)
- [x] Password hashes out of the admin page (H11); admin-side stored XSS (H12)
- [x] Remove PII from logs (H8)
- [x] Escape server data in `innerHTML` (L1)
- [x] Transactions around profile delete-and-reinsert saves
- [x] Content-Security-Policy with nonces; no inline handlers (M2)
- [x] Admin accounts in the database with roles + mandatory 2FA + audit log (M5, M6)
- [x] Separate rate limits per form (H15)
- [ ] **Owner:** run `php artisan migrate` (after a backup) and create your super admin (A8)

## 3. Unresolved risks (accepted until fixed)

1. **Leaked admin password** in the public git history until it is rotated (A1) — the only critical item left, and only you can do it.
2. **Uploaded files inside `public/`** until they are moved (A7). Apache blocks direct requests via `public/.htaccess`; `php artisan serve` does not.
3. **Admin idle timeout** equals the general session lifetime (120 min); shorten `SESSION_LIFETIME` if admins share PCs.
4. **CSP residuals**: `'unsafe-eval'` (Alpine.js) and inline styles are allowed; removing them needs the Alpine CSP build and a rewrite of the SOI form.
5. **Sequential certificate IDs**: `/osaert/verify/{id}` can be enumerated to list trainee names and companies (throttled to 60/min). A random verification token per certificate would fix it but needs a new column and reprinting of QR codes.
6. **Inferred schema**: everything was verified on a database reconstructed from the code, not on the real `pcg_core`. Run `database/audit/inspect_schema.sql` and `php artisan pcg:check` against it.
