# Development Setup — Windows

The repository contained PowerShell artefacts (the old `.gitignore`, a UTF-16
`README.md`), so the development machine is Windows. Which PHP stack is
installed (XAMPP, Laragon, WAMP) **could not be checked from here**. The steps
below work with any of them; the examples use XAMPP paths because phpMyAdmin is
part of your workflow.

## 1. Requirements

| Software | Version | Check |
|----------|---------|-------|
| PHP | **8.2 or newer** (Laravel 12). XAMPP 8.2.x is fine; older XAMPP (PHP 8.0/8.1) must be updated | `php -v` |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `curl` | `php -m` |
| Composer | 2.x | `composer -V` |
| MySQL or MariaDB | the server that already hosts `pcg_core` | XAMPP Control Panel / `mysql --version` |
| Git | any | `git --version` |
| Node.js | **not required** (views load assets from CDNs; Vite is unused) | — |
| Tailscale | latest Windows client (only for remote access) | `tailscale version` |

In XAMPP, add `C:\xampp\php` to `PATH`. In `C:\xampp\php\php.ini`, uncomment
`extension=fileinfo`, `extension=gd`, `extension=pdo_mysql` and set
`upload_max_filesize = 20M`, `post_max_size = 25M` (documents are up to 10 MB each).

## 2. First-time setup

```powershell
cd C:\path\to\PCG_CORE
powershell -ExecutionPolicy Bypass -File scripts\windows\setup.ps1
```

The script checks PHP and its extensions, creates `.env` from `.env.example`,
runs `composer install` and `key:generate`, creates the storage folders, and
runs the read-only `php artisan pcg:check`.

Then edit `.env`:

```dotenv
APP_URL=http://localhost:8000
APP_DEBUG=true            # local only; set false before enabling Tailscale
DB_HOST=127.0.0.1
DB_DATABASE=pcg_core
DB_USERNAME=pcg_app       # see DATABASE_ARCHITECTURE.md §6 (avoid root)
DB_PASSWORD=...
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD_HASH='$2y$10$...'   # from: php artisan pcg:hash-password
```

Run `php artisan pcg:check` again until every line is `[ OK ]`.

### Database

Use the existing `pcg_core` database. **Do not run `php artisan migrate`**: the
migrations don't describe the real schema (see `DATABASE_ARCHITECTURE.md`).
To set up a second machine, import a backup in phpMyAdmin (Import tab) or with
`mysql -u root -p pcg_core < backup.sql`. Keep backups outside the repository.

### Unit-checker passwords

Unit login now checks passwords. Accounts that have none cannot log in. Set a
password from the project folder:

```powershell
php artisan tinker
>>> DB::table('unit_users')->where('email','checker@example.com')->update(['password' => Hash::make('NewStrongPassword!')]);
```

(This changes real user data, so do it deliberately, one account at a time.)

## 3. Run

### Option A — built-in server (simplest)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\windows\start.ps1   # http://127.0.0.1:8000
```

Stop with **Ctrl+C**. It is single-process, which is fine for development and a
handful of users.

### Option B — Apache virtual host (XAMPP, recommended for daily use)

The app **must be served from the root of a host/port**, with `public\` as the
document root. Views use root-absolute links (`/profile`, `/unit/login`,
`fetch('/temp-save/...')`), so a sub-folder URL such as
`http://localhost/pcg_core/` breaks navigation. Pointing Apache at the project
root would also expose `.env`.

`C:\xampp\apache\conf\extra\httpd-vhosts.conf`:

```apache
Listen 127.0.0.1:8080
<VirtualHost 127.0.0.1:8080>
    ServerName localhost
    DocumentRoot "C:/path/to/PCG_CORE/public"
    <Directory "C:/path/to/PCG_CORE/public">
        AllowOverride All
        Require local
    </Directory>
    ErrorLog "logs/pcg_core-error.log"
    CustomLog "logs/pcg_core-access.log" common
</VirtualHost>
```

Make sure `LoadModule rewrite_module modules/mod_rewrite.so` is enabled in
`httpd.conf`, restart Apache, and browse `http://localhost:8080`. Set
`APP_URL=http://localhost:8080`.

`Require local` means only this PC can connect. Tailscale Serve connects from
localhost, so remote access still works.

### Stop services

- Built-in server: Ctrl+C.
- XAMPP: Control Panel → Stop Apache / MySQL (or `C:\xampp\apache_stop.bat`, `C:\xampp\mysql_stop.bat`).

## 4. Tests and checks

```powershell
php artisan test          # 48 tests, in-memory SQLite, never touches pcg_core
php artisan pcg:check     # read-only environment check
php artisan route:list
# (Laravel Pint is installed, but the existing code is not Pint-formatted yet)
composer audit            # dependency advisories
```

## 5. Troubleshooting

| Symptom | Cause / fix |
|---------|-------------|
| `No application encryption key` | `php artisan key:generate` |
| `could not find driver` | enable `extension=pdo_mysql` in php.ini |
| `SQLSTATE[HY000] [1045] Access denied` | wrong `DB_USERNAME`/`DB_PASSWORD` |
| `SQLSTATE[HY000] [2002]` | MySQL not running, or wrong `DB_HOST`/`DB_PORT` |
| Admin login: "not configured on this server" | set `ADMIN_EMAIL`, `ADMIN_PASSWORD_HASH` (single-quoted), then `php artisan config:clear` |
| Unit login always fails | the account has no bcrypt password (see §2) |
| 404 on every page except `/` | Apache `mod_rewrite` off or `AllowOverride None` |
| Document "View" links 404 | files are in `public\documents_toupload\...`; copy them from production, or set `PCG_UPLOAD_URL` |
| Upload fails with 413 / empty request | raise `upload_max_filesize` / `post_max_size` |
| Changes in `.env` ignored | `php artisan config:clear` (and `optimize:clear`) |
| Profile photo broken | `php artisan storage:link` (needs Developer Mode or an elevated shell on Windows) |

Logs: `storage\logs\laravel.log` (or `laravel-YYYY-MM-DD.log` with `LOG_CHANNEL=daily`).
