# Database Architecture — `pcg_core`

> **Status: inferred from application code.** The live `pcg_core` database was
> not reachable from the audit environment (no credentials, no dump in the repo,
> and none should be committed). Every table and column below comes from queries
> in `app/`. Confirm it by running the read-only script
> [`database/audit/inspect_schema.sql`](../database/audit/inspect_schema.sql) in
> phpMyAdmin and replacing the "inferred" sections with the real output.

## 0. Development copy

`database/dev/schema_inferred.sql` recreates these 40 tables (DDL only, no
data) for a local `pcg_core_dev` database; `php artisan pcg:dev-seed` adds
sample data. All browser end-to-end checks ran against it (MariaDB 10.11).
It is **inferred** — compare it with the real schema before relying on it.

## 1. Engine and connection

- Driver: `mysql` (`config/database.php`), works with MySQL or MariaDB. The exact
  engine and version are **unverified**; query 1 of the inspection script reports them.
- Connection: `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`
  from `.env`. phpMyAdmin is only the admin UI; the app connects directly via PDO.
- Charset in config: `utf8mb4` / `utf8mb4_unicode_ci`. Table collations are unverified.

## 2. Migrations vs reality

`database/migrations/` contains 7 files:

| Migration | Problem |
|-----------|---------|
| `2014_10_12_000000_create_users_table` | Laravel default; real `users` has ~15 more columns |
| `2025_10_08_032843_create_users_table` | **Creates `users` again** — `php artisan migrate` fails on a fresh DB |
| `2025_10_08_032910_create_services_table` / `..._032931_...` | **Same table twice**; `services` is not used by any code |
| password_resets, failed_jobs, personal_access_tokens | framework tables |

**Never run `php artisan migrate`, `migrate:fresh` or `migrate:refresh` against
`pcg_core`.** The schema lives only in the database. Roadmap step: capture it with
`php artisan schema:dump`, then retire the broken migrations (no data change).

## 3. Tables used by the application (inferred)

Join key across almost everything: **`serial_number`** (PCG serial number, string).
There are no foreign keys in the code's assumptions; relationships are implicit.

### Accounts

| Table | Used by | Key columns (from code) |
|-------|---------|-------------------------|
| `users` | Login, Register, Admin, HR analytics, many joins | `id`, `rank`, `serial_number`, `name`, `email` (unique), `password` (bcrypt), `registration_type` (`officer`/`non_officer`), `selfie_image`, `date_of_birth`, `date_of_appointment`, `ete`, `mother_unit`, `sub_unit`, `unit_assignment`, `status` (`Pending`/`Approved`/`Rejected`), timestamps |
| `unit_users` | Unit login, `unit-users/store` | `id`, `user_id`, `personnel_id`, `serial_number`, `full_name`, `email`, `username`, `password` (bcrypt), `role` (`unit_checker` = own unit, `m1_checker` = all units — enforced since 2026-10-30), `unit_name`, `unit_code`, `permissions` (JSON), `is_active` |
| `profiles` | `Profile` model (PK `user_id`) — legacy profile, `FaceVerificationController` | address, education, `photo`, `profile_code` |

### SOI / re-enlistment ("reinlist") — one person = many rows keyed by `serial_number`

`reinlist_basic_info` (names, rank, class, birthday) ·
`reinlist_personal_info` (`personnel_id`, physical data, contacts, TIN/PhilHealth/Pag-IBIG/GSIS/SSS, licence) ·
`reinlist_dependents` · `reinlist_academic` (`level`, `course`, `school`, years) · `reinlist_vocational` ·
`reinlist_trainings` (`category`) · `reinlist_duties` (`type`) · `reinlist_appointments` · `reinlist_careers` ·
`reinlist_awards` · `reinlist_cg_awards_decorations` · `reinlist_cg_letters` · `reinlist_cg_certificates` ·
`reinlist_cg_appreciations` · `reinlist_cg_plaques` · `reinlist_military_letters` · `reinlist_civilian_awards` ·
`reinlist_other_letters` · `reinlist_other_appreciations` · `reinlist_other_plaques` · `reinlist_profile` ·
`reinlist_disciplinary_actions` (`serial_number`, `file_path`)

**Write pattern:** the save steps *delete all rows for the serial number, then
re-insert* (`TempRegistrationController`, `ReenlistController`). All of them now
run inside a transaction, so a failed insert rolls back the delete.

### Documents and review

| Table | Notes |
|-------|-------|
| `personnel_data` | One row per applicant: `personnel_id` (= `users.id`), `serial_number`, a file-path column per document (`soi`, `nbi_clearance`, `ombudsman_clearance`, `sandiganbayan_clearance`, `ofr`, `medical_clearance`, `pft_clearance`, `whole_body_picture`, `photo_2x2`, `residential_sketch`), the three clearance expiry dates, `latitude`/`longitude`, `status` (`Pending`/`approved` — **mixed case**) |
| `rejected_documents` | `serial_number`, `doc_type`, comment, `status` (`rejected`/`complied`) |
| `personnel_201_files` | `personnel_id`, `serial_number`, one path column per 201 category |

### Training / LMS

| Table | Model |
|-------|-------|
| `administrative_tracks`, `operation_tracks` | `AdministrativeTrack`, `OperationTrack` (`division_unit`, `rank`, `year_in_grade`, `course_training`, `provider`, `status`) |
| `rank_equivalencies` | used by personnel administrative training matching |
| `training_applications` | `TrainingApplication` (`serial_number`, `training_type`, `track_id`, `status`, `applied_at`) |
| `training_courses` / `course_students` | `TrainingCourse` hasMany `CourseStudent` via `course_id` |
| `courses` | read in profile form |
| `osaert_trainees` | `OsaertTrainee` (`name`, `company`, `date_attended`, `date_ended` — **stored as strings**, filtered with `RIGHT(date, 4)`) |

## 4. Integrity and design issues (from code)

1. **No declared relationships**: `serial_number` links ~30 tables without
   foreign keys; `users.serial_number` uniqueness is not enforced by the app.
   The inspection script's query 9 lists duplicates.
2. **Mixed-case status values** (`Approved`/`approved`, `Rejected`/`rejected`).
   This caused the rejected-user login bypass (now fixed in code). Normalising the
   stored values is a data change and needs approval.
3. **Dates as strings** in `osaert_trainees`, which forces `RIGHT(col, 4)` scans.
4. **Delete-and-reinsert** of child tables on every save (now transactional; still rewrites IDs on every save).
5. **Likely missing indexes** on `serial_number` / `personnel_id` in the reinlist
   and document tables *(verify with query 7)*. Every profile page load runs about
   20 `WHERE serial_number = ?` lookups.
6. **Unused tables**: `services`. `profiles` is probably legacy.

## 4b. Approved additive changes (2026-10-30)

| Migration | Adds | Rollback |
|-----------|------|----------|
| `2026_10_30_000001_create_admin_users_table` | `admin_users` (name, email, bcrypt password, role, is_active, encrypted TOTP secret, hashed recovery codes, last sign-in) | drops the table |
| `2026_10_30_000002_create_admin_audit_logs_table` | `admin_audit_logs` (who, action, route, result, IP, details) | drops the table |
| `2026_10_30_000003_add_lookup_indexes` | indexes on `serial_number`, `personnel_id`, `applicant_id`, `course_id`, etc. — only where missing, skipping TEXT columns | drops only `pcg_idx_*` indexes it created |
| `2026_10_30_000004_create_contact_messages_table` | `contact_messages` | drops the table |
| `2026_10_30_000005_create_missing_training_tables` | `administrative_tracks`, `operation_tracks`, `rank_equivalencies`, `training_applications`, `training_courses`, `course_students`, `osaert_trainees` — **only those missing** (some `pcg_core` copies have none of them); existing tables untouched | drops only tables it created (comment `pcg:000005`) and only while empty |
| `2026_10_31_000001_create_org_units_table` | `org_units` (PCG hierarchy; seeds NHQ-PCG + MSSC, MEPCOM, MARSLEC) | drops the table only while it holds just the 4 seeded units; otherwise refuses |
| `2026_10_31_000002_create_personnel_master_tables` | `ranks` (20 seeded), `personnel` (serial UNIQUE, link `user_id` UNIQUE), `personnel_assignments` (effective-dated), `admin_users.org_unit_id` | refuses while `personnel`/`personnel_assignments` hold rows; otherwise drops the three tables and the column |

Procedure on your PC:

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root -p --single-transaction --routines --triggers pcg_core > D:\backups\pcg_core_before_migrate.sql
php artisan migrate --pretend      # shows the SQL, changes nothing
php artisan migrate                # prints each index: added / skip (reason)
php artisan pcg:check
php artisan pcg:create-admin
```

Rollback, newest first: `php artisan migrate:rollback --step=1`; repeat as needed. Each step undoes only that migration.
Tested on a copy of the schema with every secondary index removed: indexes added, data intact, re-run a no-op, rollback exact.

## 5. Change procedure (mandatory)

Any future schema change must provide:

1. Reason and the affected tables/columns.
2. **Backup first**: `mysqldump --single-transaction --routines --triggers pcg_core > backups/pcg_core_YYYYMMDD.sql`
   (outside the repo; `*.sql` is git-ignored).
3. A reversible migration (`up` + `down`) that never drops or truncates data.
4. A rollback plan, and a validation query showing row counts before and after.
5. Approval before running it on real data.

Candidate changes, pending approval and verification of the real schema:

| Change | Type | Risk |
|--------|------|------|
| Index `serial_number` on reinlist/document tables that lack one | additive | low (online index build) |
| Unique index on `users.email` (if missing) and `unit_users.email` | additive; fails if duplicates exist | low |
| Normalise `status` casing | data update | medium — needs approval |
| Convert `osaert_trainees` dates to `DATE` | new column + backfill | medium |

## 6. Least-privilege account

Create an application account instead of using `root`:

```sql
CREATE USER 'pcg_app'@'localhost' IDENTIFIED BY '<strong password>';
GRANT SELECT, INSERT, UPDATE, DELETE ON pcg_core.* TO 'pcg_app'@'localhost';
-- Schema changes use a separate admin account, only during maintenance.
```
