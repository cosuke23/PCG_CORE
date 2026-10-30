# PCG_CORE first-time setup on Windows (PowerShell).
# Run from the project root:   powershell -ExecutionPolicy Bypass -File scripts\windows\setup.ps1
# Safe to re-run. Does NOT touch the database.
$ErrorActionPreference = 'Stop'
Set-Location (Resolve-Path "$PSScriptRoot\..\..")

function Need($cmd, $hint) {
    if (-not (Get-Command $cmd -ErrorAction SilentlyContinue)) {
        Write-Host "Missing '$cmd'. $hint" -ForegroundColor Red
        exit 1
    }
}

Need php      "Add PHP to PATH (XAMPP: C:\xampp\php)."
Need composer "Install Composer from https://getcomposer.org/download/"

$phpVersion = php -r "echo PHP_VERSION;"
Write-Host "PHP $phpVersion"
php -r "exit(version_compare(PHP_VERSION, '8.0.2', '>=') ? 0 : 1);"
if ($LASTEXITCODE -ne 0) { Write-Host "PHP 8.0.2+ required." -ForegroundColor Red; exit 1 }

foreach ($ext in 'pdo_mysql','mbstring','openssl','fileinfo','gd','curl') {
    php -r "exit(extension_loaded('$ext') ? 0 : 1);"
    if ($LASTEXITCODE -ne 0) { Write-Host "PHP extension '$ext' is not enabled (php.ini: extension=$ext)" -ForegroundColor Yellow }
}

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    Write-Host "Created .env from .env.example - edit DB_* and ADMIN_* before starting." -ForegroundColor Yellow
}

foreach ($dir in 'storage\app\public','storage\framework\cache','storage\framework\sessions','storage\framework\views','storage\logs','bootstrap\cache') {
    New-Item -ItemType Directory -Force -Path $dir | Out-Null
}

composer install --no-interaction
if ($LASTEXITCODE -ne 0) { exit 1 }

if (-not (Select-String -Path .env -Pattern '^APP_KEY=base64:' -Quiet)) {
    php artisan key:generate
}

# public/storage link for profile photos (needs Developer Mode or an elevated shell on Windows)
if (-not (Test-Path public\storage)) {
    php artisan storage:link
}

php artisan config:clear
php artisan view:clear

Write-Host ""
Write-Host "Running read-only health check..."
php artisan pcg:check
if ($LASTEXITCODE -ne 0) {
    Write-Host "Fix the [FAIL] items above (MySQL running? DB_* and ADMIN_* in .env?)." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Setup done. Next: set ADMIN_EMAIL and ADMIN_PASSWORD_HASH (php artisan pcg:hash-password), then run scripts\windows\start.ps1" -ForegroundColor Green
