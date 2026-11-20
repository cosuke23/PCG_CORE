<#
.SYNOPSIS
    Monthly restore drill on this PC (deploy/offsite/restore-drill.php) and its
    result sent to Hermes (shared/restore-drill-last.json), where pcg:monitor
    emails the administrators if the drill failed or has not run for 40 days.
    Needs the local MariaDB/MySQL (XAMPP) running; it is started for the drill
    when it is not.

    powershell -ExecutionPolicy Bypass -File deploy\offsite\restore-drill.ps1
#>
param(
    [string]$Dest = (Join-Path $env:USERPROFILE 'HRMIS-offsite'),
    [string]$KeyFile = (Join-Path $env:USERPROFILE 'HRMIS-offsite-key\offsite.key'),
    [string]$Php = 'C:\php84\php.exe',
    [string]$MysqlBin = 'C:\xampp\mysql\bin',
    [string]$Server,
    [int]$Port
)
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$repo = Split-Path -Parent (Split-Path -Parent $here)
if (-not $Server) {
    $t = Join-Path $repo 'deploy\target.local'
    if (Test-Path $t) { $saved = @(Get-Content $t | Where-Object { $_.Trim() -ne '' }); $Server = $saved[0].Trim(); if (-not $Port -and $saved.Count -ge 2) { $Port = [int]$saved[1].Trim() } }
}
if (-not $Port) { $Port = 22 }

# start the local database for the drill if it is not running (stopped again afterwards)
$started = $null
if (-not (Get-Process mysqld -ErrorAction SilentlyContinue)) {
    $ini = Join-Path $MysqlBin 'my.ini'
    $started = Start-Process -FilePath (Join-Path $MysqlBin 'mysqld.exe') -ArgumentList "--defaults-file=`"$ini`"", '--standalone' -WindowStyle Hidden -PassThru
    Start-Sleep -Seconds 8
}
try {
    $out = & $Php (Join-Path $here 'restore-drill.php') $Dest $KeyFile "--mysql=$(Join-Path $MysqlBin 'mysql.exe')"
    $code = $LASTEXITCODE
    $out | ForEach-Object { Write-Host $_ }
    $json = ($out | Where-Object { $_ -like '{*' } | Select-Object -Last 1)
    if ($Server -and $json) {
        $b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($json))
        & ssh -p "$Port" -o BatchMode=yes -o ConnectTimeout=20 $Server "echo $b64 | base64 -d > /srv/mepcom-hrmis/shared/restore-drill-last.json" 2>$null | Out-Null
    }
    exit $code
} finally {
    if ($started) { & (Join-Path $MysqlBin 'mysqladmin.exe') -uroot shutdown 2>$null | Out-Null }
}
