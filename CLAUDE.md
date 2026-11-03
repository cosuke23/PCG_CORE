# CLAUDE.md — PCG_CORE (MEPCOM HRMIS)

Laravel **12** monolith (PHP ≥ 8.2; verified on 8.5.12 — target version, Blade, MySQL/MariaDB database `pcg_core`; upgraded from 9.52 keeping the Laravel 9 directory structure — Http/Kernel, RouteServiceProvider, config/*.php)
for the PCG Maritime Environmental Protection Command: personnel registration,
SOI profile, document review, 201 files, training tracks, MEPTDI LMS, OSAERT.
Read `docs/` before larger changes (enterprise HRMIS plan + open decisions: `docs/HRMIS_ENTERPRISE_PLAN.md`; org hierarchy lives in `org_units` / `App\Models\OrgUnit` — never hardcode commands/units); `docs/IMPLEMENTATION_ROADMAP.md` is the progress checklist — update it.

## Commands
```bash
composer install
php artisan test              # in-memory SQLite (phpunit.xml) — never the real DB
php artisan pcg:check         # read-only env/DB/folder health check
php artisan pcg:hash-password # bcrypt hash for ADMIN_PASSWORD_HASH
php artisan pcg:mail-test you@example.com  # send a test email, show active MAIL_* settings (password hidden)
php artisan pcg:dev-seed      # sample data; refuses unless APP_ENV=local and DB name ends in _dev
php artisan pcg:create-admin  # create an admin account (2FA set up at first sign-in)
php artisan pcg:personnel-sync [--apply] [--assign-to=MEPCOM] [--map-units=database/data/mepcom_units.json]  # copy approved registrations into the personnel master (dry run by default)
php artisan pcg:import-org-units database/data/mepcom_units.json [--apply]  # insert-only unit import (dry run by default)
php artisan pcg:move-uploads [--apply]  # copy uploads out of public/ into private storage (never deletes)
php artisan migrate           # additive pcg migrations only (back up first)
npm run build:css             # only after adding a Tailwind class not used anywhere yet
php artisan route:list
php artisan serve --host=127.0.0.1 --port=8000
# vendor/bin/pint exists but the codebase is not Pint-formatted; do not mass-reformat
composer audit
```
Windows helpers: `scripts/windows/setup.ps1`, `start.ps1`, `tailscale-serve.ps1`. Ubuntu/Debian server (nginx + PHP-FPM + MariaDB + Tailscale): `sudo bash scripts/linux/install-server.sh` (DEPLOYMENT_GUIDE §9) — not Hermes, which is Rocky Linux 9: the separate MEPCOM HRMIS deployment there (`/srv/mepcom-hrmis`, port 8010, own FPM pool, DB and Tailscale node, never touching AFPC/BioVerify/`/srv/pcg-core`) uses the isolated kit in `deploy/` (`deploy/README.md`; `preflight.sh` → `server-setup.sh` → `deploy\deploy.ps1 -Migrate` → `verify.sh`).
Browser end-to-end checks against a `pcg_core_dev` database: `tests/e2e/README.md`
(dev schema: `database/dev/schema_inferred.sql`).

## Architecture (actual)
- Routes: `routes/web.php`, grouped by audience:
  public · applicant (`auth`, `users` table) · unit checker (`unit.session`, `unit_users`; M1/all-units pages add `unit.m1`, role `m1_checker`) · admin (`admin.session` + `admin.audit` + `admin.role:...`; accounts in `admin_users` with roles (see AdminPermissions) and mandatory 2FA: one-time email code by default (`PCG_ADMIN_2FA=email`, needs MAIL_*), or authenticator app (`totp`); the `.env` admin only works while no account exists). Login forms use the named rate limiters in RouteServiceProvider (never `throttle:N,M`, which shares one counter per IP).
  Every new non-public route MUST be inside the right group; add it to `tests/Feature/AccessControlTest.php`.
- Controllers in `app/Http/Controllers` talk to the DB directly (mostly `DB::table`); ~30 tables have no model.
- Join key across tables: `serial_number`. For applicant writes always take it from `$request->user()`, never from input.
- Project config: `config/pcg.php` (admin creds, `upload_root`, `selfie_dir`, `trusted_proxies`). No hardcoded paths/URLs/credentials.
- Uploaded files live under `config('pcg.upload_root')` (default `public/`); link them with `App\Support\Uploads::url()` (served by `FileController` at `/files/{path}` after an access check — never link upload folders directly).
- Views: Blade. Admin pages extend `layouts/admin` (app shell; nav lives there — never add sidebars to pages); sign-in and public verification pages extend `layouts/auth`; unit pages are standalone and include `unit/partials/nav`. Assets are self-hosted: `public/vendor/*` (pinned, see its README) and `public/css/tailwind.css` compiled from the views (`tailwind.config.js`). No CDNs. Vite is unused. Many links are root-absolute (`/profile`), so the app must be served at the root of a host/port with `public/` as document root.
- Personnel master: `personnel` (serial_number UNIQUE) + `personnel_assignments` (effective-dated; transfers close the current row, never edit history). Admin org scope: `admin_users.org_unit_id`; HRMIS routes use `admin.can:<capability>` (matrix in `App\Support\AdminPermissions`, roles super_admin/hr_admin/records_reviewer/records_approver/auditor/lms_admin/reenlistment_admin); 201 documents live in `config('pcg.document_root')` and are served only by `PersonnelDocumentController`; every personnel query goes through `App\Services\OrgScope`; pages that list everyone need `admin.orgwide`.
- Re-enlistment module (`ReenlistmentController`, `/admin/reenlistment`, capabilities `reenlistment.view` / `reenlistment.export`): non-officer registrations (`users.registration_type = non_officer`) by ETE; default list = ETE within 12 months (Asia/Manila dates); read-only, views and CSV exports audited. Role `reenlistment_admin` lands there and sees nothing else; its sign-in page is `/reenlistment/login` (same accounts, password + email code).
- Own-unit checkers may only act on personnel with `users.mother_unit = session('unit_name')`; M1 checkers and admins see all. Keep `UnitDocumentController::review` and `FileController` consistent with that.

## Database safety (strict)
- `database/migrations/` holds only **additive** migrations written for `pcg_core` (admin_users, admin_audit_logs, contact_messages, lookup indexes). The original migrations, which do not match the real schema, live in `database/migrations/legacy/` and are never loaded. `php artisan migrate` (after a backup) is therefore safe; **never** run `migrate:fresh/refresh/reset` or `db:wipe` against `pcg_core`.
- New migrations must be additive, idempotent where possible, and reversible (down() undoes only what up() did).
- No DROP/TRUNCATE/mass DELETE; no schema change without: reason, impact, backup, reversible migration, rollback, validation, and the owner's approval.
- Never commit `.env`, dumps (`*.sql`), or files from `public/documents_toupload`, `public/201_files`, `public/disciplinary_actions`, `selfies/`.

## Coding conventions
- Match surrounding style (4-space indent, PSR-12-ish, Laravel helpers). Keep changes minimal; don't rewrite working modules.
- Validate every request (`$request->validate` or Form Requests); uploads need `file|mimes|max`, sanitised names, 0755 dirs.
- Use query builder bindings; `whereRaw` only with `?` placeholders.
- Escape output with `{{ }}`; no `{!! !!}` for user data; `@csrf` on all forms.
- Don't log passwords, tokens, selfies, or full `$request->all()` (PII).
- Status values are mixed-case in the DB (`Approved`/`approved`) — compare case-insensitively.
- UI: new/redesigned pages use `public/css/pcg-ui.css` tokens and `.pcg-*` components plus `public/js/pcg-ui.js` (no CDN, no inline scripts, no build step). Sign-in pages extend `layouts/auth`. Don't add new CDN dependencies.
- Insert server data into the DOM with `textContent`, or `escapeHtml()` when building HTML strings. `data-on-*` only calls page-defined global functions (built-ins like `eval` are refused).
- Sign-out is POST only (`route('admin.logout')`, `route('unit.logout')` with `@csrf`). Unit sessions are re-checked per request (`UnitUser::refreshSession`); unit checker actions go to `Log::channel('audit')`.
- One approved account per serial number (`User::serialTaken`, pending accounts reserve nothing); unit access matches the record owner (`personnel_data.personnel_id`). Admin `/files` access follows `documents.view` / `documents.restricted`.
- Hermes web settings live in `deploy/nginx` and `deploy/php-fpm`; apply changes with `deploy/update-web-config.sh`. Never put the server address in git (`deploy/target.local`).
- Phones/tablets: check with `node tests/e2e/responsive.js`; wide tables scroll in `.pcg-table-wrap` (added automatically by `pcg-ui.js`).
- CSP is enforced: inline `<script>` blocks must carry `nonce="{{ $cspNonce ?? '' }}"`; **no inline event handlers** (`onclick=` etc. — a test fails) — use `data-on-click="globalFunction" data-args='[...]'` (or `pcg.*` built-ins) handled by `public/js/pcg-ui.js`; no external origins.
- Personnel pages extend `layouts/personnel`; admin pages `layouts/admin`.

## Testing
- Feature tests create only the tables they need on SQLite (see `tests/Feature/AuthenticationTest.php`). PHPUnit 11: static data providers with `#[DataProvider]`.
- `AccessControlTest` fails if a route points at a missing controller method.
- For UI/flow changes, run the e2e scripts on a re-seeded dev database.
- Report results as Passed / Failed / Not tested / Blocked; anything needing the real DB is Blocked here.
