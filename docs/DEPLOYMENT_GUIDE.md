# Deployment Guide — Windows PC + Tailscale (private)

Target architecture:

```
Your Windows PC
 ├─ Apache (127.0.0.1:8080)  or  php artisan serve (127.0.0.1:8000)
 │    └─ PCG_CORE (Laravel, document root = public\)
 ├─ MySQL / MariaDB (127.0.0.1:3306)  — never exposed
 ├─ phpMyAdmin (http://localhost/phpmyadmin) — local only, never served
 └─ Tailscale Serve  https://<pc-name>.<tailnet>.ts.net  →  http://127.0.0.1:<port>
        └─ reachable only by devices/users allowed in your tailnet policy
```

Status: **not yet executed.** Tailscale is not available in the audit
environment. Every command below must be run and checked on your PC (see §6).

## 1. Pre-flight (on the PC)

1. Complete `DEVELOPMENT_SETUP.md` (PHP 8.5 recommended, 8.2 minimum); back up, run `php artisan migrate`, create your admin with `php artisan pcg:create-admin` and sign in once to set up two-factor; `php artisan pcg:check` is all `[ OK ]`, including "Uploads stored outside the web root" and "Admin accounts exist".
2. In `.env` set:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://<pc-name>.<tailnet>.ts.net
   SESSION_SECURE_COOKIE=true
   TRUSTED_PROXIES=127.0.0.1
   ```
   then `php artisan config:clear`.
   - `APP_DEBUG=false` is mandatory: debug pages show environment values and
     enable the `/_ignition` endpoints.
   - `SESSION_SECURE_COOKIE=true` means the login cookie is only sent over
     HTTPS, so log in through the `ts.net` URL. If you also need plain
     `http://localhost` logins on the PC, leave it `false`.
3. Make sure nothing listens on your LAN:
   ```powershell
   Get-NetTCPConnection -State Listen | Where-Object LocalPort -in 80,443,3306,8000,8080 | Select LocalAddress,LocalPort
   ```
   The expected `LocalAddress` is `127.0.0.1` (or `::1`). If MySQL shows
   `0.0.0.0`, set `bind-address=127.0.0.1` under `[mysqld]` in
   `C:\xampp\mysql\bin\my.ini` and restart it. Keep Windows Firewall on.

## 2. Tailscale setup

1. Install the Windows client and sign in: <https://tailscale.com/download/windows>.
2. In the admin console (<https://login.tailscale.com/admin/dns>) enable
   **MagicDNS** and **HTTPS Certificates**. Serve needs both to issue the `ts.net` certificate.
3. Check the client and find your hostname:
   ```powershell
   tailscale version
   tailscale status
   ```
   The DNS name is shown under *Machines* in the admin console. Don't assume it.

## 3. Enable private access

Start the app first (`scripts\windows\start.ps1`, or Apache on 8080). Then:

```powershell
# Foreground test (Ctrl+C stops it):
tailscale serve http://127.0.0.1:8000

# When it works, run it in the background (persists across reboots):
tailscale serve --bg http://127.0.0.1:8000
tailscale serve status
```

For Apache use `http://127.0.0.1:8080`. The helper does the same:
`scripts\windows\tailscale-serve.ps1 on -Port 8080`.

**Do not use `tailscale funnel`.** Funnel publishes to the public internet.
Check it is off:

```powershell
tailscale funnel status
```

## 4. Disable remote access

```powershell
tailscale serve reset          # removes all Serve config
# or: scripts\windows\tailscale-serve.ps1 off
```

## 5. Restrict who can reach it (tailnet policy)

In the admin console → *Access controls*, grant access to the PC's HTTPS port
only to the people or devices who should use the app. Example (adjust users/tags):

```json
{
  "tagOwners": { "tag:pcg-server": ["autogroup:admin"] },
  "grants": [
    { "src": ["group:pcg-users"], "dst": ["tag:pcg-server"], "ip": ["443"] }
  ],
  "groups": { "group:pcg-users": ["you@example.com"] }
}
```

Tag the PC with `tag:pcg-server`. Remove the default allow-all rule only once
your own access is covered by a grant. Tailscale controls who can reach the
server; the app's own logins still decide what each person can see.

## 6. Validation checklist (fill in on your PC)

| Check | Command / action | Result |
|-------|------------------|--------|
| App local | open `http://127.0.0.1:8000` | ☐ |
| Health | `php artisan pcg:check` | ☐ |
| Serve status | `tailscale serve status` shows `https://<name>.ts.net` → `127.0.0.1:<port>` | ☐ |
| Funnel off | `tailscale funnel status` shows no funnel | ☐ |
| Remote device (phone/laptop on tailnet) | open `https://<name>.ts.net`, log in, open a form, submit | ☐ |
| Links are HTTPS | form `action` URLs start with `https://<name>.ts.net` | ☐ |
| Not reachable off-tailnet | disconnect Tailscale on the phone → URL must fail | ☐ |
| MySQL not exposed | `Get-NetTCPConnection -LocalPort 3306` → 127.0.0.1 only | ☐ |
| phpMyAdmin not exposed | `https://<name>.ts.net/phpmyadmin` → 404 from Laravel | ☐ |
| Debug off | visit a missing URL → plain 404, no stack trace | ☐ |

The HTTPS-link behaviour behind a proxy was simulated in the audit environment:
a request from `127.0.0.1` with `X-Forwarded-Proto: https` and
`X-Forwarded-Host: mypc.tail1234.ts.net`, with `TRUSTED_PROXIES=127.0.0.1`,
produced `action="https://mypc.tail1234.ts.net/admin/login"`.

## 7. Backups

```powershell
# Database (run nightly via Task Scheduler; store outside the repo, ideally encrypted)
& "C:\xampp\mysql\bin\mysqldump.exe" -u backup_user -p --single-transaction --routines --triggers pcg_core > D:\backups\pcg_core_$(Get-Date -f yyyyMMdd).sql
# Uploaded documents
robocopy C:\path\to\PCG_CORE\public\documents_toupload D:\backups\documents_toupload /MIR
robocopy C:\path\to\PCG_CORE\public\201_files D:\backups\201_files /MIR
robocopy C:\path\to\PCG_CORE\public\disciplinary_actions D:\backups\disciplinary_actions /MIR
```

Backups contain personnel PII. Keep them off cloud-synced folders unless those
are encrypted and access-controlled. Test a restore into a scratch database.

## 8. Existing production (pcg-mepcom.com, cPanel) — notes only

This guide does not change production. If this branch is deployed there:

- **PHP 8.2+** on the host (Laravel 12).
- `.env`: `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH`, `APP_DEBUG=false`,
  `PCG_UPLOAD_ROOT` / `PCG_SELFIE_DIR` — ideally a folder outside
  `public_html`; for the current layout:
  `/home/<account>/public_html/pcg-mepcom.com/public` and
  `/home/<account>/public_html/pcg-mepcom.com/selfies`.
- `composer install --no-dev --optimize-autoloader`, then
  `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
- Every active `unit_users` row needs a bcrypt password, and M1 checkers need
  the M1 role, before deployment (Admin → Unit accounts).
- Fix the document root so it points at `public/` (Security Audit A4/C8).
- Documents are now linked via `/files/...`; old direct `/public/...` links
  are blocked by `public/.htaccess`.
