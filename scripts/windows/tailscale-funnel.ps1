# Public access through Tailscale Funnel (anyone on the internet can reach the app).
#   scripts\windows\tailscale-funnel.ps1 on      [-Port 8000] [-HttpsPort 443|8443|10000]
#   scripts\windows\tailscale-funnel.ps1 off     [-HttpsPort ...]
#   scripts\windows\tailscale-funnel.ps1 status
#
# "on": backs up .env, sets APP_URL/APP_ENV/APP_DEBUG/TRUSTED_PROXIES for the
# public address, clears the config cache, starts `php artisan serve` (in its
# own window) if it is not running, checks that upload folders are blocked,
# then turns Funnel on. If -HttpsPort is not given it uses 443, or 8443 when
# 443 is already used by another Funnel/Serve.
param(
    [Parameter(Mandatory = $true)][ValidateSet('on', 'off', 'status')][string]$Action,
    [int]$Port = 8000,
    [ValidateSet(0, 443, 8443, 10000)][int]$HttpsPort = 0
)

$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..\..')
Set-Location $root

function Fail($msg) { Write-Host $msg -ForegroundColor Red; exit 1 }
function Ok($msg) { Write-Host "  [ OK ] $msg" -ForegroundColor Green }
function Warn($msg) { Write-Host "  [WARN] $msg" -ForegroundColor Yellow }

if (-not (Get-Command tailscale -ErrorAction SilentlyContinue)) {
    Fail 'Tailscale CLI not found. Install from https://tailscale.com/download/windows and sign in.'
}

function Get-ServeConfig {
    $json = tailscale serve status --json 2>$null | Out-String
    if (-not $json.Trim()) { return $null }
    return $json | ConvertFrom-Json
}

# Which https ports already have a handler, and where do they point?
function Get-UsedPorts($cfg) {
    $used = @{}
    if ($cfg -and $cfg.Web) {
        foreach ($p in $cfg.Web.PSObject.Properties) {
            $hostPort = $p.Name                      # e.g. "hrpc.tail1234.ts.net:443"
            $num = [int]($hostPort.Split(':')[-1])
            $target = ($p.Value.Handlers.PSObject.Properties | ForEach-Object { $_.Value.Proxy }) -join ','
            $used[$num] = $target
        }
    }
    return $used
}

function Set-EnvValue([string[]]$lines, [string]$key, [string]$value) {
    $found = $false
    $out = foreach ($l in $lines) {
        if ($l -match "^\s*$key\s*=") { $found = $true; "$key=$value" } else { $l }
    }
    if (-not $found) { $out = @($out) + "$key=$value" }
    return , $out
}

switch ($Action) {
    'status' {
        tailscale status
        tailscale funnel status
        return
    }

    'off' {
        $cfg = Get-ServeConfig
        $used = Get-UsedPorts $cfg
        $target = "127.0.0.1:$Port"
        $ports = if ($HttpsPort) { @($HttpsPort) } else { @($used.Keys | Where-Object { $used[$_] -like "*$target*" -or $used[$_] -like "*localhost:$Port*" }) }
        if (-not $ports) { Write-Host "Nothing is forwarding to port $Port."; tailscale funnel status; return }
        foreach ($p in $ports) { tailscale funnel --https=$p off }
        Write-Host 'Funnel for PCG_HRMIS is off. Other Funnel/Serve entries were left as they were.' -ForegroundColor Green
        tailscale funnel status
        return
    }
}

# ---------------- on ----------------
Write-Host "PCG_HRMIS -> Tailscale Funnel (public internet)" -ForegroundColor Cyan

if (-not (Test-Path (Join-Path $root 'server.php'))) {
    Fail 'server.php is missing: run "git pull" first (it blocks direct downloads of uploaded files).'
}
if (-not (Test-Path (Join-Path $root '.env'))) { Fail '.env not found. Run scripts\windows\setup.ps1 first.' }

$self = (tailscale status --json | Out-String | ConvertFrom-Json).Self
if (-not $self -or -not $self.DNSName) { Fail 'Tailscale is not signed in on this PC (tailscale up).' }
$dnsName = $self.DNSName.TrimEnd('.')
Ok "Tailscale signed in as $dnsName"

# pick the public https port
$used = Get-UsedPorts (Get-ServeConfig)
$mine = { param($t) $t -like "*127.0.0.1:$Port*" -or $t -like "*localhost:$Port*" }
if (-not $HttpsPort) {
    $existing = @($used.Keys | Where-Object { & $mine $used[$_] })
    if ($existing) { $HttpsPort = $existing[0] }
    else { $HttpsPort = @(443, 8443, 10000) | Where-Object { -not $used.ContainsKey($_) } | Select-Object -First 1 }
    if (-not $HttpsPort) { Fail 'Ports 443, 8443 and 10000 are all in use by other Funnel/Serve entries.' }
} elseif ($used.ContainsKey($HttpsPort) -and -not (& $mine $used[$HttpsPort])) {
    Fail "https port $HttpsPort already forwards to $($used[$HttpsPort]). Choose another with -HttpsPort 8443 or 10000."
}
$publicUrl = if ($HttpsPort -eq 443) { "https://$dnsName" } else { "https://${dnsName}:$HttpsPort" }
Ok "Public address: $publicUrl (https port $HttpsPort)"

# .env (backup first, write UTF-8 without BOM so the first key still parses)
$envPath = Join-Path $root '.env'
$backup = "$envPath.backup-" + (Get-Date -Format 'yyyyMMdd-HHmmss')
Copy-Item $envPath $backup
$lines = Get-Content $envPath
$lines = Set-EnvValue $lines 'APP_URL' $publicUrl
$lines = Set-EnvValue $lines 'APP_ENV' 'production'
$lines = Set-EnvValue $lines 'APP_DEBUG' 'false'
$lines = Set-EnvValue $lines 'TRUSTED_PROXIES' '127.0.0.1'
[IO.File]::WriteAllLines($envPath, $lines, (New-Object Text.UTF8Encoding $false))
Ok "Updated .env (backup: $(Split-Path $backup -Leaf))"

php artisan optimize:clear | Out-Null
Ok 'Cleared cached config/routes/views'

# app server
$listening = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
if ($listening) {
    Warn "Something is already listening on port $Port. If it is an old 'php artisan serve', close that window and run this again so the new .env is used."
} else {
    Start-Process powershell -WorkingDirectory $root -ArgumentList '-NoExit', '-Command', "Write-Host 'PCG_HRMIS server - keep this window open'; php artisan serve --host=127.0.0.1 --port=$Port"
    Start-Sleep -Seconds 4
    Ok "Started php artisan serve on 127.0.0.1:$Port (new window - keep it open)"
}

# self-checks
function Get-Status($url) {
    try { return (Invoke-WebRequest $url -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 15).StatusCode }
    catch { if ($_.Exception.Response) { return [int]$_.Exception.Response.StatusCode } return 0 }
}
$s = Get-Status "http://127.0.0.1:$Port/login"
if ($s -eq 200) { Ok 'Sign-in page answers' } else { Fail "App is not answering on port $Port (status $s). Check the server window." }
foreach ($dir in 'documents_toupload', '201_files', 'disciplinary_actions', 'selfies') {
    $s = Get-Status "http://127.0.0.1:$Port/$dir/"
    if ($s -eq 404 -or $s -eq 403) { Ok "/$dir is not downloadable directly ($s)" }
    else { Fail "/$dir answered $s - uploads are NOT protected. Funnel not started." }
}

# funnel
tailscale funnel --bg --https=$HttpsPort "http://127.0.0.1:$Port"
if ($LASTEXITCODE -ne 0) {
    Write-Host ''
    Write-Host 'Funnel could not start. In https://login.tailscale.com/admin : DNS -> enable MagicDNS and HTTPS Certificates;' -ForegroundColor Yellow
    Write-Host 'Access controls -> allow the "funnel" node attribute for this PC. Then run this script again.' -ForegroundColor Yellow
    exit 1
}
tailscale funnel status

Write-Host ''
Write-Host "PCG_HRMIS is public at: $publicUrl" -ForegroundColor Green
Write-Host 'Test from a phone on mobile data (Wi-Fi off). Keep this PC on and the server window open.'
Write-Host 'Turn off:  scripts\windows\tailscale-funnel.ps1 off'
