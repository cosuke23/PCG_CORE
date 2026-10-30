# System Architecture — PCG_CORE (MEPCOM HRMIS)

Everything below was taken from the repository at commit `83459a9` plus the
hardening changes on this branch. Items marked *(inferred)* could not be checked
without the live database or the production server.

## 1. What the system is

A **Laravel 12** monolith (upgraded from 9.52 on 2026-10-30) that serves the Philippine Coast Guard **Maritime
Environmental Protection Command (MEPCOM)** human-resources information system:

- public institutional pages (home, about, contact, MEPTDI institute page);
- personnel self-registration with a live selfie, admin approval;
- an applicant/personnel portal: Statement of Information (SOI) profile in six
  steps, document uploads (clearances, photos), 201-file uploads, training
  applications, SOI generation;
- a **unit checker** portal that reviews uploaded documents and handles
  disciplinary records and 201 files;
- an **admin / MEPTDI LMS** area: user approval, HR analytics, administrative
  and operational training tracks, training courses and enrolled students
  (CSV import), training applications, OSAERT trainees and certificates
  (bulk CSV upload, public certificate verification).

## 2. Technology stack

| Layer | Technology (evidence) |
|-------|-----------------------|
| Language | PHP `^8.2` (`composer.json`); verified on PHP 8.3 |
| Framework | `laravel/framework` **v12.x** (`composer.lock`), Sanctum 4, PHPUnit 11. Was 9.52 (end of life, 50 advisories); `composer audit` now reports none. The Laravel 9 directory structure (Http/Kernel, RouteServiceProvider) is kept — still supported by Laravel 12 |
| Packages | `laravel/sanctum` 3 (installed, not used by any route besides the default `/api/user`), `laravel/tinker`, `guzzlehttp/guzzle` |
| Views | Blade templates; 3 layouts (`layouts/app`, `layouts/admin`) plus many full-page standalone templates |
| CSS | Tailwind via the **Play CDN** (`cdn.tailwindcss.com`) in 15 views, Bootstrap 5.3 in one view, large inline `<style>` blocks |
| JS | Alpine.js (CDN, unpinned), jQuery 3.7, DataTables 2.3, SweetAlert2, Chart.js, Leaflet + geocoder, qrcode.js — all from CDNs |
| Build | Vite 4 + `laravel-vite-plugin` configured, but **no view uses `@vite`** — the asset pipeline is unused |
| UI system (new) | `public/css/pcg-ui.css` + `public/js/pcg-ui.js`, plain files with no build step; used by `layouts/auth` (all sign-in pages). Other pages still use CDN Tailwind until migrated |
| Database | MySQL/MariaDB `pcg_core` via Eloquent and the query builder (`config/database.php`, default connection `mysql`) |
| Sessions | file driver (`config/session.php`) |
| Production host | cPanel shared hosting, `pcg-mepcom.com`, account path `/home/<account>/public_html/pcg-mepcom.com` (from hardcoded paths, now removed) |

## 3. Current architecture

```
                         Browser (CDN assets: Tailwind, jQuery, DataTables, Alpine…)
                                                │
                                   Apache / PHP web server
                                                │
                                      public/index.php (Laravel)
                                                │
   ┌─────────────────────────────── routes/web.php ───────────────────────────────┐
   │ public             applicant ("auth")      unit ("unit.session")   admin ("admin.session")
   │ PageController     DashboardController     UnitUserController      AdminController
   │ Register/Login     TempRegistrationCtrl    UnitDocumentController  HRAnalyticsController
   │ OsaertCtrl@verify  ApplicantDocumentCtrl                           Training*/Course*/Osaert
   │                    ApplicantTrainingCtrl                           Operation/AdministrativeTrack
   │                    ProfileController (SOI) ReenlistController
   └──────────────────────────────────────────────────────────────────────────────┘
                 │                                           │
     MySQL/MariaDB  pcg_core                     File system (web-accessible)
     (~32 tables, mostly via DB::table)          public/documents_toupload, public/201_files,
                                                 public/disciplinary_actions, selfies/
```

### Authentication model (three separate mechanisms)

| Audience | Store | Mechanism | Guard on routes |
|----------|-------|-----------|-----------------|
| Applicant / personnel | `users` (status `Pending`/`Approved`/`Rejected`) | Laravel session guard `web` (`Auth::attempt`) | `auth` |
| Unit checker | `unit_users` (`is_active`, bcrypt `password`, `role`, `unit_name`) | custom session keys `unit_user_id`, `unit_name`… | `unit.session` (`EnsureUnitSession`) |
| Super admin | single account from `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`) | session flag `admin_logged_in` | `admin.session` (`EnsureAdminSession`) |

There are no roles or permissions beyond these three audiences. `unit_users.role`
and `unit_users.permissions` are stored but never checked.

### Request pipeline additions (this branch)

- `App\Http\Middleware\SecurityHeaders` — appended to the `web` group.
- `EnsureAdminSession`, `EnsureUnitSession` — route middleware, prioritised
  before route-model binding (`Kernel::$middlewarePriority`).
- `TrustProxies` — trusts `TRUSTED_PROXIES` (e.g. `127.0.0.1` for Tailscale Serve).
- `config/pcg.php` — admin credentials, upload root/URL, selfie folder, proxies.
- `App\Support\Uploads` — builds public URLs for uploaded files.

## 4. Code organisation

```
app/Http/Controllers/        26 controllers, no service or repository layer
app/Http/Middleware/         Laravel defaults + 3 new (admin/unit session, headers)
app/Models/                  9 Eloquent models; most tables have no model
app/Support/Uploads.php      upload URL helper (new)
app/Console/Commands/        pcg:hash-password (new)
config/pcg.php               project settings (new)
resources/views/             ~45 Blade files (+7 backup copies)
public/                      index.php, backgrounds/ (unit logos, photos), face-api-models/, ph_json/
database/migrations/         7 migrations that do NOT describe the real schema (see DATABASE_ARCHITECTURE.md)
tests/Feature/               AccessControlTest, AuthenticationTest (new)
```

## 5. Architectural problems

1. **Fat controllers, no domain layer.** `TempRegistrationController` (1,051
   lines), `TrainingCourseStudentController` (844), `CourseStudentController`
   (629) and `DashboardController` (591) hold validation, SQL, file handling and
   presentation mapping in single methods.
2. **Dead or broken code paths.**
   - `DecisionController`, `UnitReviewController` and `UploadController` are empty
     classes. Routes `/decision/submit` and `/unit/documents/review` pointed at
     them, so the unit review form always failed. **Fixed:** the review route now
     uses `UnitDocumentController::review`, which matches the form fields.
   - `/services` and `/meptdi-lms` pointed at methods that do not exist.
     **Removed.**
   - `TrainingCourseStudentController` declares namespace
     `App\Http\Controllers\Admin` but lives in `app/Http/Controllers/`, so it is
     not autoloadable and is unused (duplicates `CourseStudentController`).
   - `FaceVerificationController::saveResult` validates against a table
     `applicants` that the code never uses elsewhere *(inferred: does not exist)*.
   - `GET /documents/upload` rendered a non-existent view. **Fixed:** it now
     redirects to `/upload-documents`.
3. **Three authentication systems** with session flags instead of guards; no
   shared user/role model and no audit trail.
4. **Raw table access everywhere.** About 30 tables are accessed only via
   `DB::table()`, so there are no relationships, casts or reusable scopes.
5. **Schema not under version control.** Migrations cover 4 framework tables;
   `users` and `services` are each created by two migrations.
6. **Files in the web root** with URLs assembled in views (now centralised
   through `App\Support\Uploads`).
7. **Front-end duplication.** Each admin page re-declares its own sidebar
   (`lms-menu` partial plus copies in 5 standalone pages), CDN imports and
   inline CSS. No shared design tokens.
8. **Leftover files:** 7 view backups (`*.backup.html`, `*_march_*.txt`,
   `profile_backup.blade.php`, a file literally named
   `register.blade.php.working.backup.app')`), `public/backgrounds/desktop.ini`,
   two `drive-download-*` folders that duplicate logos.

## 6. Recommended target architecture (incremental, same stack)

Keep Laravel + Blade + MySQL. Change it in small steps, each covered by tests.

1. **Supported Laravel version** (10 → 11/12 path) with dependency updates.
2. **Schema baseline:** generate migrations from the live `pcg_core` schema
   (`database/schema/mysql-schema.sql` via `php artisan schema:dump`) so the
   schema is versioned without touching data.
3. **Models for the core tables** (`personnel_data`, `reinlist_*`,
   `personnel_201_files`, `rejected_documents`) and **services** for the
   SOI save flow, document review and CSV imports.
4. **Form Request classes** for validation; keep controllers thin.
5. **Private document storage** (`storage/app/private`) and a
   `DocumentController@show` that checks the viewer's right to the file.
6. **Admin accounts in the database** with roles (`super_admin`, `lms_admin`,
   `unit_checker`) and policies, replacing the `.env` admin.
7. **One layout system:** shared Blade components (sidebar, top bar, table,
   form controls) compiled with Vite + Tailwind, instead of CDN copies.
