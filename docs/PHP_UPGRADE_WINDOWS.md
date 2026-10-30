# Upgrading PHP to 8.5 on Windows

**Verified (2026-10-30):** PCG_CORE runs on **PHP 8.5.12**. It was built
from the `php-8.5.12` source tag in the development environment, and
everything passed with no deprecation warnings in the logs:
- all 91 automated tests;
- the browser crawl of 51 pages;
- every end-to-end form flow;
- Composer's platform check.

The minimum is still PHP 8.2 (Laravel 12), but **PHP 8.2 security support
ends on 31 December 2026**, so upgrade now.

What changes on your PC depends on how PHP is installed. Check first:

```powershell
php -v
where.exe php
```

## Before you start

1. Back up the database (`docs/DEPLOYMENT_GUIDE.md` §7) and copy the project folder.
2. Note the extensions your current `php.ini` enables (`php -m`). PCG_CORE needs:
   `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `curl`, `intl` (recommended), `zip`, `sodium`.
3. Install or update the **Microsoft Visual C++ Redistributable 2015–2022 (x64)**.
   PHP 8.5 Windows builds are compiled with Visual Studio 2022 ("VS17").

## Option A — Laragon (easiest)

1. Download the **PHP 8.5.12 VS17 x64 Thread Safe** zip from the official
   Windows PHP site (windows.php.net → Downloads).
2. Extract it to `C:\laragon\bin\php\php-8.5.12-Win32-vs17-x64\`.
3. Laragon menu → **PHP → Version** → choose 8.5.12. Laragon creates `php.ini` from
   `php.ini-development`; enable the extensions listed above, then restart all services.

## Option B — XAMPP (replace the bundled PHP)

XAMPP releases lag behind PHP, so PHP has to be swapped in by hand:

1. Stop Apache in the XAMPP Control Panel.
2. Rename `C:\xampp\php` to `C:\xampp\php-old` (your rollback).
3. Download the **PHP 8.5.12 VS17 x64 Thread Safe** zip (Thread Safe is required for
   Apache's `php8apache2_4.dll`) and extract it to `C:\xampp\php`.
4. Copy `C:\xampp\php\php.ini-production` to `C:\xampp\php\php.ini` and set:
   ```ini
   extension_dir = "C:\xampp\php\ext"
   extension=curl
   extension=fileinfo
   extension=gd
   extension=intl
   extension=mbstring
   extension=openssl
   extension=pdo_mysql
   extension=sodium
   extension=zip
   upload_max_filesize = 20M
   post_max_size = 25M
   date.timezone = Asia/Manila
   ```
5. Check `C:\xampp\apache\conf\extra\httpd-xampp.conf`:
   - It should contain `LoadModule php_module "C:/xampp/php/php8apache2_4.dll"` (the
     file name is the same for PHP 8.5).
   - The `PHPIniDir` line should point to `C:/xampp/php`.
6. Start Apache, then confirm:
   ```powershell
   C:\xampp\php\php.exe -v
   cd C:\path\to\PCG_CORE
   php artisan pcg:check
   php artisan test
   ```
7. **phpMyAdmin:** older bundled versions can show deprecation notices on PHP 8.5.
   If they do, install the current phpMyAdmin release into `C:\xampp\phpMyAdmin`
   and keep your `config.inc.php`.

**Rollback:** stop Apache, then rename `php` → `php-85` and `php-old` → `php`.

## Option C — only `php artisan serve` (no Apache)

Extract the **Non Thread Safe** or **Thread Safe** zip to `C:\php85`, create
`php.ini` as in Option B (with `extension_dir = "C:\php85\ext"`), and put
`C:\php85` first on your user `PATH`. Then open a new PowerShell window and run
`php -v`.

## After the upgrade

```powershell
composer install          # re-checks platform requirements
php artisan optimize:clear
php artisan pcg:check     # shows the PHP version; warns below 8.3
scripts\windows\start.ps1
```

Then sign in as each role once (personnel, unit, admin) and open a document.
