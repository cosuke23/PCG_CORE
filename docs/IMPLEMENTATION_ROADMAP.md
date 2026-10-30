# Implementation Roadmap — PCG_CORE

Legend: ✅ done & verified · 🟡 done, needs verification on your PC/DB ·
⬜ not started · ⛔ blocked (reason given) · 🔒 needs your approval

Last updated: 2026-10-30 (branch `claude/cool-wozniak-rni95k`)

## Phase 1 — Discovery ✅
- ✅ Repository, stack, routes, controllers, models, views, config inspected
- ✅ `SYSTEM_ARCHITECTURE.md`, `DATABASE_ARCHITECTURE.md` (inferred), `SECURITY_AUDIT.md`, `FEATURE_INVENTORY.md`
- ✅ `composer audit` run (50 advisories, 14 packages)
- ⛔ Live schema review: no DB access here → run `database/audit/inspect_schema.sql` and send me the output
- ⛔ Visual reference https://cgmeptdi.com/ was blocked by this session's network policy

## Phase 2 — Planning ✅
- ✅ This roadmap; priorities = security → broken features → localhost/Tailscale → UI → refactor

## Phase 3 — Backup
- ✅ Git checkpoint: baseline `83459a9`; all work is on a branch
- ⬜ **You:** `mysqldump` of `pcg_core` before any DB change (DEPLOYMENT_GUIDE §7)

## Phase 4 — Architecture & security (critical) ✅
- ✅ Hardcoded admin credentials removed → `.env` + bcrypt + `pcg:hash-password`
- ✅ Access-control middleware on every applicant / unit / admin route (+ tests)
- ✅ Unit login verifies password; session regeneration; login throttling
- ✅ Rejected-user login bypass fixed
- ✅ Upload validation, file-name sanitising, 0755/0644 permissions
- ✅ Serial-number IDOR on profile saves
- ✅ No passwords/selfies in logs (Register, Reenlist)
- ✅ Security headers; trusted-proxy support
- ✅ Hardcoded server paths/URLs → `config/pcg.php`, `App\Support\Uploads`
- ✅ `.gitignore`, `.env.example`, `storage/` skeleton, README
- ✅ 48 automated tests (`php artisan test`)
- ⬜ **You:** rotate the exposed password, make the GitHub repo private (SECURITY_AUDIT §0)
- 🔒 Purge secret from git history (history rewrite)

### Phase 4b — remaining security work
- ⬜ Move personnel documents to private storage + authorised download route (C7) — 🔒 moves existing files
- ⬜ Scope unit checkers to their unit / role (H9) — needs your access rules
- ✅ Remove PII from logs (H8)
- ✅ Escape server data inserted with `innerHTML` (stored XSS via rejection comments)
- ⬜ Upgrade Laravel 9 → supported version + dependencies (H7)
- ⬜ Admin users in DB with roles; MFA (M6)

## Phase 5 — UI/UX
- ⬜ Design tokens (navy / white / gold), typography and spacing scale as Tailwind config
- ⬜ Vite build replacing CDN Tailwind/Alpine/jQuery copies; then CSP + SRI
- ⬜ Shared Blade components: app shell (sidebar + top bar), page header, card, data table, form fields, alerts, empty states
- ⬜ Redesign in this order: login pages (applicant/admin/unit) → admin dashboard & sidebar → unit dashboards → applicant dashboard/profile → LMS/OSAERT pages → public pages
- ⬜ Accessibility pass (labels, focus states, contrast, keyboard use of modals)
- ⛔ Reference site comparison: allow `cgmeptdi.com` in the environment network settings

## Phase 6 — Functional fixes
- ✅ Unit document review route (was an empty controller)
- ✅ Admin sidebar logout (405), GET logout not logging out
- ✅ `GET /documents/upload` 500; disciplinary delete crash
- ✅ Removed routes to missing methods (`/services`, `/decision/submit`, `/meptdi-lms`)
- ⬜ Contact form has no backend — decide: remove or implement (needs mail settings)
- ⬜ "Add student" forms with `action="#"` — verify against intended flow
- ⬜ UI to create unit-checker accounts (`/unit-users/store` exists, no form)
- ⬜ Face verification: finish or remove (no link, wrong validation table)
- ✅ Wrap SOI steps 3/4/6 delete-and-reinsert in transactions (test proves old rows survive a failed insert)
- ⬜ Remove dead controllers and backup views (after your confirmation)

## Phase 7 — Database validation ⛔ (needs DB access/output)
- ⬜ Run `inspect_schema.sql`; update DATABASE_ARCHITECTURE.md with real schema
- ⬜ `php artisan schema:dump`; retire duplicate migrations
- 🔒 Add missing `serial_number` indexes (additive, reversible)
- 🔒 Normalise status casing; OSAERT dates → DATE

## Phase 8 — Testing
- ✅ Access-control and authentication tests
- ⬜ Feature tests per module against a schema-dump test DB (after Phase 7)
- ⬜ Browser console / responsive checks (after Phase 5)

## Phase 9 — Localhost (Windows)
- ✅ Setup/start scripts (`scripts/windows/*.ps1`) — 🟡 untested on Windows
- ✅ `php artisan pcg:check` health command
- ✅ App boots and public pages return 200 (verified here on PHP 8.3, empty SQLite)
- ⬜ **You:** run `setup.ps1`, `pcg:check` against real `pcg_core`, report results

## Phase 10 — Tailscale
- ✅ Guide + helper script; HTTPS link generation behind proxy simulated
- ⬜ **You:** `tailscale serve --bg …`, fill validation checklist (DEPLOYMENT_GUIDE §6)

## Phase 11 — Documentation ✅ (living)
- ✅ All docs in `docs/`, `CLAUDE.md`, `README.md`

## Test results log

| Date | Item | Result |
|------|------|--------|
| 2026-10-30 | `php artisan test` (48) | **Passed** |
| 2026-10-30 | `php artisan test` (50, + transaction rollback) | **Passed** |
| 2026-10-30 | Public pages `/`, `/about`, `/contact`, `/institutes/meptdi`, `/login`, `/register?type=officer`, `/admin/login`, `/unit/login`, `/unit/loginM1` | **Passed** (200) |
| 2026-10-30 | Protected pages redirect when logged out | **Passed** |
| 2026-10-30 | `/osaert/verify` | **Blocked** — needs `osaert_trainees` (real DB) |
| 2026-10-30 | All Blade templates compile (`view:cache`) | **Passed** |
| 2026-10-30 | CRUD, uploads, reports, analytics | **Not tested** — needs real DB |
| 2026-10-30 | Windows scripts | **Not tested** — no Windows here |
| 2026-10-30 | Tailscale access | **Not tested** — must run on your PC |
