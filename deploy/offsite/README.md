# Off-site backup copy and restore drill (office PC)

Owner decision 2026-11-20: the off-site copy lives on the office Windows PC.

| What | When | Script |
|---|---|---|
| Copy the newest Hermes backup (02:15), check its SHA256SUMS, encrypt every file (AES-256-GCM), keep 30 | every night 03:30 | `offsite-pull.ps1` |
| Decrypt the newest copy, restore it into a scratch database `mepcom_restore_drill` on the local MariaDB, compare every table's row count with `row-counts.json` recorded on Hermes, drop the scratch database | the 1st of every month 04:30 | `restore-drill.ps1` (→ `restore-drill.php`) |

Install or update the schedule (once): `powershell -ExecutionPolicy Bypass -File deploy\offsite\install-tasks.ps1`
(`-Remove` deletes the tasks; copies and key stay).

- Copies: `%USERPROFILE%\HRMIS-offsite\<backup>\*.enc`, log `offsite.log`, drill log `restore-drill.log`.
- Key: `%USERPROFILE%\HRMIS-offsite-key\offsite.key`, made on the first run, readable only by this Windows account.
  **Copy it to a USB drive kept apart from the PC**: without it the copies cannot be read. It is never sent
  to Hermes and never committed. The file encryption master keys of the uploads are not in the backups
  either (`docs/FILE_ENCRYPTION.md`).
- Server: read from `deploy/target.local` (git-ignored, the same file `deploy.ps1` uses).
- Alerts: each copy leaves `shared/offsite-last.txt` and each drill `shared/restore-drill-last.json` on Hermes;
  `pcg:monitor --alert` (hourly) emails the administrators when no copy has arrived for 48 hours, the last
  drill failed, or no drill ran for 40 days.
- By hand: decrypt one file with `php deploy\offsite\offsite-crypt.php decrypt <key> <file>.enc <out>`.
- The PC must be on at 03:30 / 04:30. The drill starts XAMPP's MariaDB if it is not running and stops it after.
