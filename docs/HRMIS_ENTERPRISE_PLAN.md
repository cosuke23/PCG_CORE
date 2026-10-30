# PCG-HRMIS Enterprise Plan: Phase 1 (Functional Commands)

Started 2026-10-31 from the owner's master prompt "PCG-CORE — Philippine Coast
Guard Enterprise HRMIS" and the PCG organisational chart they supplied.
**Verified** means taken from the code, the database or the owner's chart.
**Proposed** means a design that needs the owner's confirmation.

## 1. Discovery findings (verified)

| Area | Finding |
|------|---------|
| Stack | Laravel 12, PHP 8.2+ (runs on 8.5.12), Blade, MySQL/MariaDB `pcg_core`, self-hosted assets, CSP enforced. 102 automated tests. |
| Scope today | **MEPCOM only.** Public pages, registration and unit review are all MEPCOM. MSSC and MARSLEC do not appear anywhere in code or data. |
| Personnel identity | No personnel master table. A person is a row in `users` (login and personnel data together). Other tables are joined through the free-text `serial_number`. **No uniqueness guarantee on `serial_number`** in the inferred schema; the real constraint is unknown until `inspect_schema.sql` is run. |
| Organisation | No organisation tables. The MEPCOM unit list (about 200 entries, "based on your CSV") is **hardcoded in JavaScript** in `pages/register.blade.php`. Registration stores free text: group → `users.mother_unit`, unit → `users.sub_unit`, "unit - sub unit" → `users.unit_assignment`. Unit checkers are matched by `unit_users.unit_name = users.mother_unit`. |
| Assignments | Only the current text values on `users`. No history, no effective dates, no transfer records. |
| Ranks | The rank list is hardcoded in the registration form (ASN…FMCPO, ENS…ADM). `users.rank` is free text. `rank_equivalencies` maps ranks to training tracks. |
| Documents | Required documents are columns of `personnel_data`; 201 files are in `personnel_201_files`; disciplinary records are in `reinlist_disciplinary_actions`. The files are under `public/`, but served only through the access-checked `/files` route (with `.htaccess` and `server.php` blocks). No categories table, versions, verification metadata or download audit. |
| SOI / service history | About 20 `reinlist_*` tables (education, trainings, duties, appointments, careers, awards, …), keyed by `serial_number`, rewritten as a whole on each save. |
| Approval | One step: an HR/super admin approves or rejects a self-registration (`users.status`). Unit checkers approve or reject documents. No draft, return-for-correction or history. |
| Accounts | Three separate login systems: personnel (`users`), unit checkers (`unit_users`; own unit or M1/all), admins (`admin_users`: super_admin / hr_admin / lms_admin, mandatory TOTP, audit log). **No organisational scope on admin accounts**: every HR admin sees everyone. |
| Training/LMS | Tracks, applications, MEPTDI courses, course students, OSAERT. |
| Design | Shared design system `pcg-ui.css`/`pcg-ui.js` (navy/white/gold), admin app shell, personnel shell. cgmeptdi.com could not be studied (blocked by this environment's network policy). |
| Project path | The master prompt names `C:\Users\HRPC\Desktop\public_html`. This work is in the GitHub repo `cosuke23/PCG_CORE` (checked out on the owner's PC as `C:\Users\HRPC\Desktop\PCG_HRMIS`). **Confirm which copy is canonical.** |

## 2. Gaps for the three Functional Commands

1. Database-driven organisation hierarchy. **Started: org_units (this checkpoint).**
2. One personnel master identity with a unique service number, independent of login and of assignment.
3. Effective-dated assignments and transfers (history kept).
4. Organisation-scoped admin roles, so a MEPCOM HR user sees MEPCOM only. Today every HR admin sees everyone.
5. Registration workflow: draft → submitted → returned → approved/rejected, with remarks, history, no self-approval and duplicate detection.
6. 201 file repository: categories, metadata, versions, verification, expiry, download audit, private storage.
7. Rank reference table and promotion history.
8. Scoped dashboards and reports with exports.

## 3. Proposed architecture

```
org_units (hierarchy, effective dates, active flag)       <- built
   ^ org_unit_id
personnel_assignments (person, unit, position, start, end, order ref, is_current)
   v personnel_id
personnel (one permanent record: service/serial number UNIQUE, name, dob,
           classification, rank_id, service status ...)
   ^ personnel_id (nullable)        ^ personnel_id
users (personnel login)            personnel_documents / promotions / trainings / registrations
admin_users + admin_scopes (role x org_unit subtree)
```

- **One identity:** `personnel.service_number` is unique. Existing `users`, `reinlist_*` and `personnel_201_files` rows stay as they are and link by serial number, so nothing is migrated destructively. Backfilling `personnel` from `users` is a copy (additive), and is **run only after the owner reviews the duplicate-serial report**.
- **Scope:** every scoped query passes through one service (`OrgScope::personnelQuery($admin)`), which limits personnel to those whose *current* assignment is inside the admin's permitted subtrees. It is enforced in controllers and queries, not only in the navigation. Tested with a MEPCOM-vs-MSSC isolation test.
- **Approval chain:** configurable steps per organisation (reviewer, approver). No step is invented: until the owner provides the chain, the default is the current single approval by an HR admin, with self-approval blocked.
- **Workspaces:** MSSC, MEPCOM and MARSLEC "workspaces" are the same screens filtered by the admin's scope or the selected command. There are no separate applications or databases.

## 4. Roadmap (checkpoints)

| # | Checkpoint | Status |
|---|-----------|--------|
| 1 | Organisation hierarchy: table, admin tree, add/edit/move, activate/deactivate, audit, tests | **Done** |
| 2 | Personnel master + duplicate-serial report (read-only) + backfill command (dry-run first) | Needs decisions D2, D3 |
| 3 | Assignments and transfers (effective-dated), personnel lists per unit, map legacy `mother_unit` text to units | Needs D4 |
| 4 | Organisation-scoped admin roles + isolation tests; scoped dashboard | Needs D5 (changes admin authorisation) |
| 5 | Registration workflow (draft/return/approve, history, duplicates) | Needs D6 (approval chain) |
| 6 | 201 file repository (categories, versions, verification, private storage, download audit) | Needs D7 |
| 7 | Rank reference + promotion history | Needs D8 |
| 8 | Reports and exports (CSV/Excel, print), scoped | after 4 |

## 5. Decisions needed from the owner

| ID | Decision | Recommendation |
|----|----------|----------------|
| D1 | Which project copy is canonical: `public_html` or the GitHub repo / `PCG_HRMIS`? | The GitHub repo. Copy anything unique from `public_html` into it. |
| D2 | Personnel master: a new `personnel` table (recommended) or extend `users`? | New table. `users` stays the login, linked by `personnel_id`. |
| D3 | Can the service/serial number be the unique permanent identifier for officers and non-officers alike? | Yes, if PCG issues one per person. Otherwise an internal ID plus a unique serial. |
| D4 | May the MEPCOM unit list in the registration form ("from your CSV") be imported as MEPCOM's official subordinate units? Can you provide the verified unit lists for MSSC and MARSLEC? | Import MEPCOM after you confirm it. MSSC/MARSLEC only from your documents. |
| D5 | Scoped admin roles: HQ HR (all), Command HR admin/staff (one command), Unit HR (one unit), Records reviewer, Records approver, Read-only auditor. Approve this role set? | Approve as the starting set. Existing hr_admin accounts keep "all" (HQ) until reassigned. |
| D6 | Registration approval chain per command (who reviews, who approves). | Provide the official procedure. Until then: one approval, no self-approval. |
| D7 | Move existing uploaded files out of `public/` into private storage (production data move). | Yes, after a backup (SECURITY_AUDIT A7). |
| D8 | Official rank list and order, including civilian/non-uniformed personnel, if any. | Start from the ranks already in the registration form. |
| D9 | Should the other 23 commands from the chart be added now as inactive or placeholder records? | Add them as records only (no workflows), once you confirm the chart is current. |
