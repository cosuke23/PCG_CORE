# MEPCOM HRMIS on Hermes - isolated deployment kit

Deploys this repository (PCG_CORE, "MEPCOM HRMIS") on the Hermes server
(`coastguard-hermes`, Rocky Linux 9) as a **separate app** next to AFPC,
BioVerify and the existing `/srv/pcg-core` app, **without touching them**.
Modelled on AFPC's proven kit. Everything listens on 127.0.0.1; its own
Tailscale node publishes it at `https://mepcom-hrmis.<tailnet>.ts.net`
(tailnet only by default, public with `PUBLIC=1`).

## What it creates (and nothing else)

| Piece | Name | Shared with other apps? |
|---|---|---|
| App folder | `/srv/mepcom-hrmis` (`repo.git`, `releases/`, `current`, `shared/.env`, `shared/storage`, `shared/uploads`, `backups/`) | no |
| Service account | `mepcom` (no login) | no |
| Database / user | `mepcom_hrmis` / `mepcom_hrmis@localhost` - rights on its own database only, max 20 connections | no |
| PHP-FPM pool | `/etc/php-fpm.d/mepcom-hrmis.conf` (user `mepcom`, own socket, own upload limits, max 4 workers) | uses the installed PHP; **php.ini not edited** |
| nginx site | `/etc/nginx/conf.d/mepcom-hrmis.conf`, `127.0.0.1:8010` | nginx process shared; **nginx.conf and other sites not edited** |
| Tailscale node | `tailscaled-mepcom.service`, state `/var/lib/tailscale-mepcom`, hostname `mepcom-hrmis` | no - existing nodes untouched |
| sudo rule | `/etc/sudoers.d/mepcom-hrmis-deploy` (deploy account may reload php-fpm) | no |
| Daily backup | `/etc/cron.d/mepcom-hrmis-backup` -> `/srv/mepcom-hrmis/backups` (14 days) | no |
| SELinux | labels for `/srv/mepcom-hrmis` and port 8010 only | no global booleans |

Every installed file starts with `managed-by: mepcom-hrmis deploy kit`; the
scripts refuse to overwrite any file without that line.

## Rules the scripts enforce

- No `dnf install/update`: a missing PHP extension stops setup with a message (installing it is a shared change for the server owner).
- No edits to php.ini, nginx.conf, other `conf.d`/`php-fpm.d` files, firewalld, sshd, or other apps' folders and databases.
- Config is tested before a **graceful reload** (never a restart); a file that fails its test is removed again and nothing is reloaded.
- Before changing anything: a full backup of every database and of the nginx/php-fpm configs in `/root/mepcom-hrmis-predeploy-<time>/`.
- A snapshot of the other apps (services, their HTTP answers, config checksums, databases) is taken first and compared afterwards (`verify.sh`).
- Data is imported only into the **empty** `mepcom_hrmis` database; the dump's `CREATE DATABASE`/`USE` lines are stripped so it cannot write elsewhere.

## First-time setup

1. **Copy the kit to the server** (PowerShell, in the PCG_CORE folder):

       scp -P 400 -r deploy coastguard-admin@112.199.83.96:mepcom-hrmis-deploy
       ssh -p 400 coastguard-admin@112.199.83.96 "sed -i 's/\r$//' ~/mepcom-hrmis-deploy/*.sh"

2. **Preflight, read-only** (on the server). Fix anything it reports; nothing is changed.

       sudo bash ~/mepcom-hrmis-deploy/preflight.sh
       # port taken? try another:  PORT=8012 sudo bash ~/mepcom-hrmis-deploy/preflight.sh

3. **Setup** (on the server). Prints a Tailscale login link: sign in to the same account as the other nodes.

       sudo bash ~/mepcom-hrmis-deploy/server-setup.sh             # tailnet only (recommended)
       sudo PUBLIC=1 bash ~/mepcom-hrmis-deploy/server-setup.sh    # public via Funnel
       # with another port: sudo PORT=8012 bash ...

4. **Optional: bring your data** (dump made on the PC with
   `mysqldump.exe -u root -p --single-transaction --routines --result-file=pcg_core.sql pcg_core`):

       scp -P 400 pcg_core.sql coastguard-admin@112.199.83.96:~/
       sudo bash ~/mepcom-hrmis-deploy/import-database.sh ~/pcg_core.sql

   Uploaded files: copy the PC's `documents_toupload`, `201_files`,
   `disciplinary_actions` and `selfies` folders into `/srv/mepcom-hrmis/shared/uploads/`, then
   `sudo chown -R mepcom:mepcom /srv/mepcom-hrmis/shared/uploads && sudo restorecon -R /srv/mepcom-hrmis/shared/uploads`.

5. **Deploy from the PC** (runs the tests, pushes, builds, migrates):

       powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Migrate

6. **On the server:** mail for sign-in codes (`MAIL_*` in `/srv/mepcom-hrmis/shared/.env`,
   then `cd /srv/mepcom-hrmis/current && php artisan config:cache`), first admin
   (`php artisan pcg:create-admin`), and the final check:

       sudo bash ~/mepcom-hrmis-deploy/verify.sh

## Updates and rollback (from the PC)

    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1            # code only
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Migrate   # with migrations (backup first)
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Rollback  # previous release

A release that does not answer `/` and `/login` with 200 is switched back
automatically. A code rollback does not undo migrations; restore the backup
taken before them (`/srv/mepcom-hrmis/backups/<time>/database.sql.gz`).

## Undo (removes only MEPCOM HRMIS pieces)

    sudo tailscale --socket=/run/tailscale-mepcom/tailscaled.sock logout
    sudo systemctl disable --now tailscaled-mepcom
    sudo rm -f /etc/systemd/system/tailscaled-mepcom.service /etc/sudoers.d/mepcom-hrmis-deploy /etc/cron.d/mepcom-hrmis-backup
    sudo rm -f /etc/nginx/conf.d/mepcom-hrmis.conf /etc/php-fpm.d/mepcom-hrmis.conf
    sudo nginx -t && sudo systemctl daemon-reload && sudo systemctl reload nginx php-fpm
    sudo bash ~/mepcom-hrmis-deploy/verify.sh   # the other apps must still match

`/srv/mepcom-hrmis` and the `mepcom_hrmis` database hold data: back them up
before removing either (`DROP DATABASE` is never run by these scripts).

## Files

| File | Runs where / as | Changes |
|---|---|---|
| `config.sh`, `lib.sh` | sourced | - |
| `preflight.sh` | server, sudo | nothing (writes a snapshot in /var/tmp) |
| `server-setup.sh` | server, sudo, once | creates the pieces above |
| `import-database.sh` | server, sudo | fills the empty `mepcom_hrmis` only |
| `release.sh` | server, deploy account | `/srv/mepcom-hrmis/releases`, `current` |
| `backup.sh` | server, deploy account / cron | `/srv/mepcom-hrmis/backups` |
| `verify.sh` | server, sudo | nothing |
| `deploy.ps1` | the PC | pushes and calls `release.sh` |
