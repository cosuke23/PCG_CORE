# CLAUDE.md — PCG_CORE (MEPCOM HRMIS)

Laravel **9.52** monolith (PHP ≥ 8.0.2, Blade, MySQL/MariaDB database `pcg_core`)
for the PCG Maritime Environmental Protection Command: personnel registration,
SOI profile, document review, 201 files, training tracks, MEPTDI LMS, OSAERT.
Read `docs/` before larger changes; `docs/IMPLEMENTATION_ROADMAP.md` is the progress checklist — update it.

## Commands
```bash
composer install
php artisan test              # in-memory SQLite (phpunit.xml) — never the real DB
php artisan pcg:check         # read-only env/DB/folder health check
php artisan pcg:hash-password # bcrypt hash for ADMIN_PASSWORD_HASH
php artisan route:list
php artisan serve --host=127.0.0.1 --port=8000
# vendor/bin/pint exists but the codebase is not Pint-formatted; do not mass-reformat
composer audit
```
Windows helpers: `scripts/windows/setup.ps1`, `start.ps1`, `tailscale-serve.ps1`.

## Architecture (actual)
- Routes: `routes/web.php`, grouped by audience:
  public · applicant (`auth`, `users` table) · unit checker (`unit.session`, `unit_users`) · admin (`admin.session`, single admin from `.env`).
  Every new non-public route MUST be inside the right group; add it to `tests/Feature/AccessControlTest.php`.
- Controllers in `app/Http/Controllers` talk to the DB directly (mostly `DB::table`); ~30 tables have no model.
- Join key across tables: `serial_number`. For applicant writes always take it from `$request->user()`, never from input.
- Project config: `config/pcg.php` (admin creds, `upload_root`, `upload_url`, `selfie_dir`, `trusted_proxies`). No hardcoded paths/URLs/credentials.
- Uploaded files live under `config('pcg.upload_root')` (default `public/`); build URLs with `App\Support\Uploads::url()`.
- Views: Blade + CDN Tailwind/Alpine/jQuery/DataTables; Vite is configured but unused. Many links are root-absolute (`/profile`), so the app must be served at the root of a host/port with `public/` as document root.

## Database safety (strict)
- **Never** run `migrate`, `migrate:fresh/refresh/reset`, `db:wipe` against `pcg_core` — migrations do not match the real schema (duplicate `users`/`services`).
- No DROP/TRUNCATE/mass DELETE; no schema change without: reason, impact, backup, reversible migration, rollback, validation, and the owner's approval.
- Never commit `.env`, dumps (`*.sql`), or files from `public/documents_toupload`, `public/201_files`, `public/disciplinary_actions`, `selfies/`.

## Coding conventions
- Match surrounding style (4-space indent, PSR-12-ish, Laravel helpers). Keep changes minimal; don't rewrite working modules.
- Validate every request (`$request->validate` or Form Requests); uploads need `file|mimes|max`, sanitised names, 0755 dirs.
- Use query builder bindings; `whereRaw` only with `?` placeholders.
- Escape output with `{{ }}`; no `{!! !!}` for user data; `@csrf` on all forms.
- Don't log passwords, tokens, selfies, or full `$request->all()` (PII).
- Status values are mixed-case in the DB (`Approved`/`approved`) — compare case-insensitively.

## Testing
- Feature tests create only the tables they need on SQLite (see `tests/Feature/AuthenticationTest.php`).
- Report results as Passed / Failed / Not tested / Blocked; anything needing the real DB is Blocked here.
