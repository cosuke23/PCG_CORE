<#
.SYNOPSIS
    Off-site copy of the MEPCOM HRMIS backups (owner decision 2026-11-20: this Windows PC).
.DESCRIPTION
    Copies the newest Hermes backup (made at 02:15 by deploy/backup.sh) over SSH,
    checks it against its SHA256SUMS, encrypts every file with the off-site key
    (deploy/offsite/offsite-crypt.php, AES-256-GCM) into <Dest>\<backup name>\,
    deletes the plain copy, keeps the newest -Keep copies, and leaves a heartbeat
    on Hermes (shared/offsite-last.txt) so pcg:monitor emails the administrators
    when copies stop arriving. The key never leaves this PC.

    powershell -ExecutionPolicy Bypass -File deploy\offsite\offsite-pull.ps1
    (deploy\offsite\install-tasks.ps1 schedules it every night at 03:30)
#>
param(
    [string]$Dest = (Join-Path $env:USERPROFILE 'HRMIS-offsite'),
    [string]$KeyFile = (Join-Path $env:USERPROFILE 'HRMIS-offsite-key\offsite.key'),
    [int]$Keep = 30,
    [string]$Server,
    [int]$Port,
    [string]$Php = 'C:\php84\php.exe'
)
$ErrorActionPreference = 'Stop'
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$repo = Split-Path -Parent (Split-Path -Parent $here)
$crypt = Join-Path $here 'offsite-crypt.php'
New-Item -ItemType Directory -Force $Dest | Out-Null
$log = Join-Path $Dest 'offsite.log'
function Say([string]$m) { $line = "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $m"; Write-Host $line; Add-Content -Path $log -Value $line -Encoding utf8 }

# server: the same saved target as deploy.ps1 (deploy/target.local, git-ignored)
if (-not $Server) {
    $t = Join-Path $repo 'deploy\target.local'
    if (Test-Path $t) { $saved = @(Get-Content $t | Where-Object { $_.Trim() -ne '' }); $Server = $saved[0].Trim(); if (-not $Port -and $saved.Count -ge 2) { $Port = [int]$saved[1].Trim() } }
}
if (-not $Server) { Say 'FAIL no server (pass -Server user@host or fill deploy\target.local)'; exit 1 }
if (-not $Port) { $Port = 22 }
$ssh = @('-p', "$Port", '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=20', $Server)
$scp = @('-P', "$Port", '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=20', '-q')

# key: made on the first run, kept apart from the copies
if (-not (Test-Path $KeyFile)) {
    New-Item -ItemType Directory -Force (Split-Path -Parent $KeyFile) | Out-Null
    & $Php $crypt keygen $KeyFile | Out-Null
    if ($LASTEXITCODE -ne 0) { Say 'FAIL could not create the off-site key'; exit 1 }
    & icacls (Split-Path -Parent $KeyFile) /inheritance:r /grant:r "${env:USERNAME}:(OI)(CI)F" | Out-Null
    Say "NEW KEY $KeyFile - copy it to a USB drive kept somewhere safe: without it the copies cannot be read."
}

try {
    $name = (& ssh @ssh 'ls -1 /srv/mepcom-hrmis/backups | grep -E "^[0-9]{8}-[0-9]{6}$" | sort | tail -1' 2>$null | Select-Object -Last 1)
    if ($LASTEXITCODE -ne 0 -or -not $name) { throw 'cannot list the backups on the server' }
    $name = $name.Trim()
    $target = Join-Path $Dest $name
    if (Test-Path (Join-Path $target 'COMPLETE')) { Say "OK   $name already copied"; }
    else {
        $plain = Join-Path $env:TEMP ("hrmis-offsite-" + [guid]::NewGuid().ToString('N'))
        New-Item -ItemType Directory -Force $plain | Out-Null
        try {
            & scp @scp -r "${Server}:/srv/mepcom-hrmis/backups/$name/*" $plain 2>$null
            if ($LASTEXITCODE -ne 0) { throw "copy of $name failed" }
            $sums = Get-Content (Join-Path $plain 'SHA256SUMS')
            foreach ($row in $sums) {
                if ($row -notmatch '^([0-9a-f]{64})\s+\*?(.+)$') { continue }
                $f = Join-Path $plain $Matches[2].Trim()
                if ((Get-FileHash -Algorithm SHA256 $f).Hash.ToLower() -ne $Matches[1]) { throw "$($Matches[2]) does not match its checksum" }
            }
            New-Item -ItemType Directory -Force $target | Out-Null
            foreach ($f in Get-ChildItem $plain -File) {
                & $Php $crypt encrypt $KeyFile $f.FullName (Join-Path $target ($f.Name + '.enc'))
                if ($LASTEXITCODE -ne 0) { throw "encrypting $($f.Name) failed" }
            }
            Copy-Item (Join-Path $plain 'SHA256SUMS') (Join-Path $target 'SHA256SUMS')   # hashes only, safe in clear
            Set-Content -Path (Join-Path $target 'COMPLETE') -Value (Get-Date -Format o) -Encoding ascii
            $size = '{0:N1} MB' -f ((Get-ChildItem $target -File | Measure-Object Length -Sum).Sum / 1MB)
            Say "OK   $name copied, checked and encrypted ($size)"
        } finally {
            Remove-Item -Recurse -Force $plain -ErrorAction SilentlyContinue
        }
    }
    # keep the newest $Keep complete copies; half-finished folders are removed
    Get-ChildItem $Dest -Directory | Where-Object { $_.Name -match '^\d{8}-\d{6}$' -and -not (Test-Path (Join-Path $_.FullName 'COMPLETE')) -and $_.Name -ne $name } |
        ForEach-Object { Remove-Item -Recurse -Force $_.FullName }
    Get-ChildItem $Dest -Directory | Where-Object { $_.Name -match '^\d{8}-\d{6}$' } | Sort-Object Name -Descending | Select-Object -Skip $Keep |
        ForEach-Object { Remove-Item -Recurse -Force $_.FullName; Say "OK   removed old copy $($_.Name)" }
    # heartbeat for pcg:monitor (the file's time is when the copy finished)
    & ssh @ssh "echo $name > /srv/mepcom-hrmis/shared/offsite-last.txt" 2>$null | Out-Null
    exit 0
} catch {
    Say "FAIL $($_.Exception.Message)"
    exit 1
}
