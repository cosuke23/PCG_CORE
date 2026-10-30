# Feature Inventory — PCG_CORE (MEPCOM HRMIS)

Status key
- **Works (code)**: code path is complete and consistent with its view. It has
  **not** been run against the real database.
- **Verified**: exercised in this environment (artisan server or automated test).
- **Broken / Fixed**: defect found by reading the code, and what was done about it.
- **Blocked**: needs the real `pcg_core` database to test.

## Public

| Feature | Route | Status |
|---------|-------|--------|
| Home, About, Contact, MEPTDI page | `/`, `/about`, `/contact`, `/institutes/meptdi` | **Verified** (HTTP 200) |
| Contact form | `/contact` | **Non-functional**: `<form>` has no action, no method and no controller, so it submits nowhere |
| Registration (officer / non-officer, live selfie) | `/register?type=officer\|non_officer`, `POST /register.submit` | Form **verified** (200; bad type → 404). Submit **blocked** (DB). Selfie validation added |
| OSAERT certificate verification | `/osaert/verify`, `/osaert/verify/{trainee}` | **Blocked** (needs `osaert_trainees`) |
| `/services` | — | **Removed**: pointed at a missing method (always 500) |

## Applicant / personnel portal (login: `users`, `auth`)

| Feature | Route(s) | Status |
|---------|----------|--------|
| Login / logout | `/login`, `POST /logout` | **Verified** by tests (approved user logs in; pending/rejected/unknown refused). **Fixed** the rejected-user bypass. Redesigned; the non-working "Forgot password" link (no reset flow exists) is replaced by an instruction to contact the administrator, and the Bootstrap register modal by two direct links |
| Dashboard (rejected-document alerts, LMS class) | `/dashboard` | Works (code) · Blocked |
| SOI profile, 6-step save | `/profile`, `/temp-save/step1…6` | Works (code) · Blocked. **Fixed** serial-number IDOR in step 1. Steps 3, 4 and 6 delete-then-insert without a transaction |
| Legacy one-shot SOI save | `POST /reinlist/store` | Only called from commented-out JS. Now requires login and is restricted to your own serial |
| Profile update (legacy `profiles` table) | `POST /profile/update` | Works (code); stores the photo on the `public` disk (needs `php artisan storage:link`) |
| Upload required documents | `/upload-documents`, `POST /documents/upload` | Works (code). **Fixed**: no server-side validation; errors and success messages were never displayed |
| 201 file upload | `/upload-201`, `POST /upload-201` | Works (code) |
| Generate SOI (printable) | `/generate-soi` | Works (code) · Blocked |
| Training: view tracks, apply | `/applicant/administrative-training`, `/applicant/operational-training`, `/applicant/training/apply` | Works (code) · Blocked |
| Face verification | `/face-verify`, `/applicant/{id}/face-verify`, `POST /applicant/face-verify/result` | **Incomplete**: page has no menu link; `saveResult` validates against an `applicants` table that the code doesn't use anywhere else. `public/face-api-models/` is shipped but no view loads face-api.js |

## Unit checker portal (login: `unit_users`, `unit.session`)

| Feature | Route(s) | Status |
|---------|----------|--------|
| Login (unit / M1) | `/unit/login`, `/unit/loginM1` | **Fixed**: password was not checked. **Verified** by tests |
| Unit dashboard (own unit) | `/unit/dashboard` | Works (code) · Blocked |
| M1 dashboard (all units) | `/unit/dashboardM1` | Works (code) · Blocked |
| Approve / reject documents | `POST /unit/documents/review` | **Fixed — was broken**: route pointed at the empty `UnitReviewController`. Now uses `UnitDocumentController::review` (same field names) |
| Serial lookup | `/unit/search-serial/{serial}` | Works (code) |
| 201 files list and detail | `/unit/201-files`, `/unit/201-files/{serial}` | Works (code). File links pointed at production. **Fixed** to `PCG_UPLOAD_URL` |
| Disciplinary upload / delete | `POST /unit/disciplinary/store`, `DELETE /unit/disciplinary/delete/{id}` | **Fixed**: delete crashed (missing `Storage` import) and looked in the wrong folder |
| Create unit account | `POST /unit-users/store` (admin only) | **Incomplete**: no UI form calls it |

## Admin / MEPTDI LMS (login: `.env` admin, `admin.session`)

| Feature | Route(s) | Status |
|---------|----------|--------|
| Admin login / logout | `/admin/login`, `POST /admin/logout`, `GET /admin/logout` | **Verified** by tests. **Fixed**: hardcoded credentials; GET logout cleared the wrong session keys; sidebar logout links sent GET to a POST-only route (405); login errors were never displayed; form was hidden behind a "click to access" animation |
| User approval dashboard | `/admin/dashboard`, `POST /admin/approve/{id}`, `/admin/reject/{id}` | Works (code) · Blocked |
| HR analytics | `/admin/hr-analytics` | Works (code) · Blocked |
| Administrative / operational tracks | `/administrative-tracks`, `/operation-tracks` (CRUD) | Works (code) · Blocked |
| Personnel vs. training matrices | `/personnel-administrative-training`, `/personnel/operational-training` | Works (code) · Blocked |
| Training applications | `/admin/training-applications`, `PATCH …/{id}/status` | Works (code) · Blocked |
| Training courses + students (CSV import / template) | `/admin/training-courses`, `/training-courses/{course}/students…` | Works (code) · Blocked. Two "add student" forms use `action="#"` (`students/create`, `students/index`) and need checking |
| OSAERT trainees (bulk CSV), certificates | `/admin/osaert…` | Works (code) · Blocked |
| MEPTDI LMS dashboard | `/meptdi-lms` | **Removed**: pointed at a missing method (always 500) |

## Dead code (kept, not routed)

`DecisionController`, `UnitReviewController`, `UploadController` (empty);
`TrainingCourseStudentController` (wrong namespace, duplicate);
`unit/dashboard_m1` and `unit/dashboard` duplicate ~600 lines;
7 backup view files. Delete them once you confirm they aren't needed.
