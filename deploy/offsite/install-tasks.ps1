<#
.SYNOPSIS
    Schedules the off-site copy (every night 03:30) and the restore drill (the
    1st of every month 04:30) as Windows tasks of the signed-in user. Run once;
    run again to update. -Remove deletes both tasks (the copies and key stay).

    powershell -ExecutionPolicy Bypass -File deploy\offsite\install-tasks.ps1
#>
param([switch]$Remove)
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$tasks = @(
    @{ Name = 'MEPCOM HRMIS off-site backup'; Script = 'offsite-pull.ps1'; Schedule = @('/SC', 'DAILY', '/ST', '03:30') },
    @{ Name = 'MEPCOM HRMIS restore drill'; Script = 'restore-drill.ps1'; Schedule = @('/SC', 'MONTHLY', '/D', '1', '/ST', '04:30') }
)
foreach ($t in $tasks) {
    & schtasks.exe /Delete /TN $t.Name /F 2>$null | Out-Null
    if ($Remove) { Write-Host "removed: $($t.Name)"; continue }
    $cmd = "powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$(Join-Path $here $t.Script)`""
    & schtasks.exe /Create /TN $t.Name /TR $cmd @($t.Schedule) /F | Out-Null
    if ($LASTEXITCODE -ne 0) { Write-Host "FAILED: $($t.Name)"; exit 1 }
    Write-Host "scheduled: $($t.Name) ($($t.Schedule -join ' '))"
}
if (-not $Remove) {
    Write-Host ''
    Write-Host 'The PC must be on (and not asleep) at those times; if it was off, the newest backup is copied on the next run.'
    Write-Host "Copies: $(Join-Path $env:USERPROFILE 'HRMIS-offsite')   Key: $(Join-Path $env:USERPROFILE 'HRMIS-offsite-key\offsite.key') (back it up on a USB drive)"
}
