# MEPCOM HRMIS — record of processing (Data Privacy Act of 2012)

Draft for the MEPCOM Data Protection Officer (DPO) to review and sign off.
It describes what the system does today (2026-11-20). The public notice is
`/privacy` (`resources/views/pages/privacy.blade.php`); its version, date and DPO
contact are in `config('pcg.privacy')` (`PCG_PRIVACY_NOTICE_VERSION`,
`PCG_PRIVACY_NOTICE_DATE`, `PCG_DPO_NAME`, `PCG_DPO_EMAIL`).

## 1. Controller and contact

- Personal information controller: Philippine Coast Guard, Maritime Environmental Protection Command (MEPCOM).
- DPO: to be named by the Command (`PCG_DPO_NAME`, `PCG_DPO_EMAIL`).
- System owner / processor: MEPCOM HRMIS team (deployment on the Hermes server, private Tailscale network only).

## 2. What is processed, why, and on what basis

| Data | Where (tables / folders) | Purpose | Basis |
|---|---|---|---|
| Identity and service: name, rank, serial, email, birth date, appointment date, ETE, command, unit | `users`, `personnel`, `personnel_assignments` | personnel records, re-enlistment, readiness | mandate of a government agency (RA 10173 sec. 12(e), 13(b)) |
| Registration selfie | `selfies/` (encrypted), `users.selfie_image` | confirm the account owner | consent at registration (`users.privacy_consent_at`) |
| SOI (personal, family, education, service, awards, schooling) | `reinlist_*`, `temp_*` tables | personnel record, Adjutant records | mandate |
| Required documents and clearances, 201 files | `personnel_data`, `personnel_201_files`, upload root (encrypted) | document review, records | mandate |
| Residence location (map pin) | `personnel_data.latitude/longitude` | emergency and recall planning | consent at registration |
| Leave and credits | `leave_requests`, `leave_credit_entries` | leave administration, readiness | mandate |
| Training (MEPTDI LMS, OSAERT) | `training_*`, `course_*` | training records, certificates | mandate |
| Activity records: sign-ins, downloads, changes, IP address | `admin_audit_logs`, `storage/logs/audit-*.log` | security, accountability | legitimate interest / mandate |
| Contact form messages | `contact_messages` | answer questions | consent (sending the message) |

Sensitive personal information (sec. 3(l)) in scope: birth date, government-issued
identifiers (serial number, clearances), health (medical clearance, sensitive leave
types such as maternity or VAWC leave). Sensitive leave types are shown only as
"On leave" to anyone other than the member and the approving checker
(`App\Support\LeaveTypes::sensitive`).

## 3. Who receives it (inside the PCG only)

- The member.
- Own-unit checkers (members whose `users.mother_unit` is their unit) and M1 checkers (all units).
- Admin roles, each limited to their command/unit by `App\Services\OrgScope` and to their
  capabilities by `App\Support\AdminPermissions` (super_admin, hr_admin, records_reviewer,
  records_approver, auditor, lms_admin, reenlistment_admin, adjutant).
- No transfer outside the PCG; no third-party processors except the email provider used to
  send sign-in codes and notices (`MAIL_*`).

## 4. Security measures

- Network: reachable only through the Command's Tailscale network; HTTPS.
- Accounts: staff need a second sign-in step (email code or authenticator app; unit checkers
  email code since 2026-11-20), temporary passwords must be changed at first sign-in, rate-limited sign-in.
- Files: every upload is AES-256-GCM encrypted (`docs/FILE_ENCRYPTION.md`); served only after an
  access check; every file view is audited.
- Audit: admin, unit checker, member and system actions are recorded (`admin_audit_logs`, console Audit log, "Who" filter).
- Content Security Policy, CSRF protection, no third-party scripts or CDNs.
- Backups daily (`deploy/backup.sh`, 14 days kept, encryption keys kept apart), verified weekly
  (`pcg:backup-verify --alert`); health checked hourly (`pcg:monitor --alert`), errors emailed to the administrators.

## 5. Retention

| Records | Kept for | How it is enforced |
|---|---|---|
| Personnel record, SOI, 201 file, approved leave, training | while in the service, then as required by government records rules (NAP schedules) | manual, on the DPO's decision |
| Registrations never approved | 180 days (`PCG_RETAIN_PENDING_DAYS`) | `pcg:retention-report` counts them; `pcg:purge-accounts` removes (dry run first, after a backup) |
| Activity records | 400 days (`LOG_AUDIT_DAYS`) | daily log files remove themselves; database rows counted by the report |
| Contact messages | 365 days (`PCG_RETAIN_CONTACT_DAYS`) | counted by the report |
| Declined / cancelled leave | 5 years (`PCG_RETAIN_LEAVE_DAYS`) | counted by the report |
| Backups | 14 days | `deploy/backup.sh` |

`php artisan pcg:retention-report` is read-only and prints counts only (no names).

## 6. Data subject requests

- Access / copy: the member sees their own records in the portal; a full copy is prepared by an
  HR admin on request to the DPO.
- Correction: the member edits the SOI until it is submitted; after that the Adjutant or HR
  admin reopens or edits it (`/admin/soi`, audited `soi.reopened` / `soi.edited`).
- Erasure / blocking: decided by the DPO; carried out with the dry-run commands after a backup.
- Answer within 30 days (NPC rules); log each request and its outcome.

## 7. Breach procedure (summary)

1. Contain: disable affected accounts, rotate keys (`pcg:app-key-rotate`, `pcg:file-keys rotate`), keep logs.
2. Assess within 24 hours: what data, how many people, likely harm.
3. If it involves sensitive personal information or can lead to identity fraud and affects
   real risk of serious harm: notify the National Privacy Commission and the affected members
   within 72 hours of knowledge (NPC Circular 16-03).
4. Record the incident and the actions taken.

## 8. Consent

Registration requires ticking "I have read the privacy notice and agree…"; the time and the
notice version are stored in `users.privacy_consent_at` / `users.privacy_notice_version`
(migration `2026_11_20_000003`). Accounts created before that date have no stored consent;
the DPO decides whether to ask them again.
