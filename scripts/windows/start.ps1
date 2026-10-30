# Start PCG_CORE with PHP's built-in server on http://127.0.0.1:8000
# (development / small private use). For a more robust setup use the
# Apache virtual host described in docs/DEVELOPMENT_SETUP.md.
# Stop with Ctrl+C.
param([int]$Port = 8000)
Set-Location (Resolve-Path "$PSScriptRoot\..\..")

$mysql = Get-NetTCPConnection -LocalPort 3306 -State Listen -ErrorAction SilentlyContinue
if (-not $mysql) {
    Write-Host "MySQL/MariaDB is not listening on 3306 - start it from the XAMPP/Laragon control panel." -ForegroundColor Yellow
}

# Bind to 127.0.0.1 only. Remote access goes through Tailscale Serve, never the LAN.
php artisan serve --host=127.0.0.1 --port=$Port
