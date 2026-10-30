# Feature Inventory — PCG_CORE (MEPCOM HRMIS)

Last verified: 2026-10-30, branch `claude/cool-wozniak-rni95k`, on PHP 8.3 and **PHP 8.5.12**, with the Content-Security-Policy enforced.

Admin roles: **super admin** (everything), **HR admin** (approvals, HR analytics, unit accounts, messages, training), **LMS admin** (training tracks/applications, courses, students, OSAERT).

## How this was verified

- **Automated tests** (`php artisan test`, in-memory SQLite): access control,
  logins, uploads, file access, unit/M1 separation, OSAERT logic.
- **Browser end-to-end** (`tests/e2e`, Chromium) against **`pcg_core_dev`**, a
  local MariaDB 10.11 database built from `database/dev/schema_inferred.sql`
  (the schema reconstructed from the code) and `php artisan pcg:dev-seed`
  sample data. Each role signs in through the real form, every page is opened
  (HTTP, PHP and JavaScript errors recorded), and each form is submitted, with
  the database checked afterwards.

**Not yet verified against the real `pcg_core` data.** The dev schema is
inferred; differences surface as SQL errors, so run
`database/audit/inspect_schema.sql` and `php artisan pcg:check` on your PC.

Status key: **Verified** = passed the browser checks on the dev database ·
**Fixed** = was broken, now verified · **Blocked** = needs something external
(noted).

## Public

| Feature | Route | Status |
|---------|-------|--------|
| Home, About, Contact, MEPTDI page | `/`, `/about`, `/contact`, `/institutes/meptdi` | Verified |
| Contact page form | `/contact` | **Built** (had no backend): messages stored in `contact_messages`, read under Admin → Messages; validation, anti-spam field, 5/min. Verified end to end |
| Self-registration (officer / non-officer, live selfie) | `/register?type=…`, `POST /register.submit` | **Verified** end to end with a camera: creates a `Pending` user with a real PNG selfie. Fixed: intro video blocked the form when the video file is missing |
| OSAERT certificate lookup | `/osaert/verify?certificate=OSAERT-YYYY-NNNNNN` | **Fixed** (view never existed, HTTP 500). Lookup by number; does not list trainees |
| OSAERT certificate page (QR target) | `/osaert/verify/{id}` | **Fixed**: used the admin layout (admin menu + Logout shown publicly) and showed blank dates |

## Personnel (login: `users`)

| Feature | Route(s) | Status |
|---------|----------|--------|
| Sign in / out | `/login`, `POST /logout` | Verified. Fixed: rejected users could sign in |
| Dashboard | `/dashboard` | **Redesigned** (personnel app shell): documents uploaded, review status, reviewer remarks, enrolled MEPTDI classes, task tiles. Fixed: LMS card always shown with a dead link; stored XSS in remarks |
| SOI profile, 6 steps | `/profile`, `/temp-save/step1…6` | Verified (step 1 save end to end). Fixed: undefined Alpine components, serial-number IDOR, non-transactional saves |
| Required documents upload | `/upload-documents` | Verified (upload, success message, own-file access, non-PDF refused). Fixed: no server validation, no messages |
| 201 files upload | `/upload-201` | **Fixed** (HTTP 500: `@vite` without a build). Verified upload |
| Generate SOI | `/generate-soi` | Verified (renders) |
| Training tracks, apply | `/applicant/administrative-training`, `/applicant/operational-training`, `POST /applicant/training/apply` | Verified (application created). Fixed: ranks without an equivalent saw every track |
| Open own files | `/files/...` | Verified; other people's files return 404 |
| Face verification | — | **Removed** (never linked or finished; 20 MB of public model files). Restore from git history if wanted |

## Unit checkers (login: `unit_users`)

| Feature | Route(s) | Status |
|---------|----------|--------|
| Own-unit sign in | `/unit/login` | Verified. Fixed: password was never checked |
| M1 sign in | `/unit/loginM1` | Verified. Requires the M1 role (new) |
| Document review, own unit | `/unit/dashboard`, `POST /unit/documents/review` | **Fixed** (form posted to an empty controller). Verified approve and reject-with-comment. Limited to own unit (new) |
| Document review, all units (M1) | `/unit/dashboardM1` | Verified |
| 201 files (M1) | `/unit/201-files` | Verified |
| Serial search + disciplinary upload (M1) | `/unit/search-serial/{serial}`, `POST /unit/disciplinary/store` | Verified upload. Fixed: delete crashed |
| Navigation | sidebar | **Fixed**: four links pointed to `#` |

## Admin / MEPTDI LMS (login: `.env` admin)

| Feature | Route(s) | Status |
|---------|----------|--------|
| Sign in / out | `/admin/login` → two-factor | Verified. Accounts in `admin_users` with mandatory TOTP 2FA (enrolment via QR code, recovery codes). Fixed: hardcoded credentials, errors never shown, logout bugs |
| Admin accounts (super admin) | `/admin/accounts` | **New.** Create, change role, reset password / 2FA, activate/deactivate; safeguards for the last super admin. Verified |
| Audit log (super admin) | `/admin/accounts/audit-log` | **New.** Sign-ins, failures, every admin change. Verified |
| Organization (super admin edits, HR admin views) | `/admin/organization` | **New.** Database-driven PCG hierarchy (HQ → command → office → unit → section), effective dates, activate/deactivate (never delete), audited. Seeded: NHQ-PCG, MSSC, MEPCOM, MARSLEC. Verified |
| HRMIS overview (super/HR admin, scoped) | `/admin/hrmis` | **New.** Figures from the personnel master only, limited to the admin's command/unit; links to filtered lists. Verified |
| Personnel master (super/HR admin, scoped) | `/admin/personnel` | **New.** One record per serial number; list with search/filters, profile, add (duplicate checks), assign/transfer with history; limited to the admin's command/unit. Verified |
| Admin access scope | `/admin/accounts` | **New.** HR admins can be limited to one command/unit (e.g. MEPCOM). Verified |
| Messages (super/HR admin) | `/admin/messages` | **New.** Contact-form inbox. Verified |
| Dashboard: approve / reject registrations | `/admin/dashboard` (double-click a row) | Verified. **Fixed: there was no Reject button**; registrant data escaped (stored XSS); password hashes no longer embedded in the page |
| Unit accounts (new) | `/admin/unit-users` | Verified: create, set password, grant/remove M1, activate/deactivate |
| HR analytics | `/admin/hr-analytics` | Verified (renders with data) |
| Administrative / operational tracks | `/administrative-tracks`, `/operation-tracks` | Verified (read-only lists; there is no create/edit UI although `operation-tracks` has store/update/destroy routes) |
| Personnel vs training matrices | `/personnel-administrative-training`, `/personnel/operational-training` | Verified |
| Training applications + status | `/admin/training-applications` | Verified status update |
| Training courses CRUD | `/admin/training-courses` | Verified create, edit, delete. Fixed: a JS syntax error disabled the table and the delete confirmation |
| Course students: add, CSV import, remove | `/training-courses/{id}/students` | **Fixed** (add and remove posted to `#`; add sent the wrong field; name/serial never saved). Verified all three |
| OSAERT trainees: bulk CSV, year filters | `/admin/osaert` | Verified. Fixed: year filters missed `yyyy-mm-dd` rows |
| OSAERT certificates (printable, QR) | `/admin/osaert/certificates` | Verified. Fixed: malformed numbers like `OSAERT-6-14-000002` |

## Removed (pointed at missing code, always HTTP 500)

`/services`, `/decision/submit`, `/meptdi-lms`, `GET /applicant/training/apply`.
A test now fails if any route points at a missing controller method.

## Missing files (not in git; copy from production)

Public pages reference images/videos that are not in the repository:
`backgrounds/MEPCOM.png`, `MEPLEF_new.png`, `NOCOP_new.png`, `MFSIF.png`,
`MEPAcES.png`, `MEPRCGS.png`, `MEPTDI.png`, `HSS.png`, `MEPPU.png`,
`images/pcg_ship.jpg`, and the videos `backgrounds/0406.mp4`,
`MEPCOM AVP FOR BEACON.mp4`, `mepcom 4.mp4` (videos are git-ignored by
design). Pages still work; those images show as broken until copied.

## Needs internet

Map tiles on the document-upload page (OpenStreetMap). Everything else is
self-hosted.

## Dead code (kept, not routed)

`DecisionController`, `UnitReviewController`, `UploadController` (empty),
`TrainingCourseStudentController` (wrong namespace, duplicate), 7 view backup
files. Safe to delete after your confirmation.
