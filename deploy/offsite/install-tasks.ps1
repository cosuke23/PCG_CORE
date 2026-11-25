<#
.SYNOPSIS
    Schedules the off-site copy (every night 03:30) and the restore drill (the
    1st of every month 04:30) as Windows tasks of the signed-in user. A missed
    time (PC off, asleep or on battery) runs as soon as the PC is on again. Run
    once; run again to update. -Remove deletes both tasks (the copies and key stay).

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
    # Catch up instead of skipping (2026-11-25: copies stopped for 4 days because
    # the PC was asleep or on battery at 03:30 and Windows refused the start):
    # run as soon as possible after a missed time, also on battery, wake the PC
    # for it, and stop a run that hangs after 2 hours.
    try {
        $settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -WakeToRun -AllowStartIfOnBatteries `
            -DontStopIfGoingOnBatteries -ExecutionTimeLimit (New-TimeSpan -Hours 2) -MultipleInstances IgnoreNew
        Set-ScheduledTask -TaskName $t.Name -Settings $settings -ErrorAction Stop | Out-Null
        $note = 'catches up after a missed time, runs on battery, wakes the PC'
    } catch {
        $note = "WARNING: could not set catch-up options ($($_.Exception.Message)); a missed time is skipped"
    }
    Write-Host "scheduled: $($t.Name) ($($t.Schedule -join ' ')) - $note"
}
if (-not $Remove) {
    Write-Host ''
    Write-Host 'If the PC is off or asleep at those times, the task runs as soon as it is on again (it can also wake the PC).'
    Write-Host "Copies: $(Join-Path $env:USERPROFILE 'HRMIS-offsite')   Key: $(Join-Path $env:USERPROFILE 'HRMIS-offsite-key\offsite.key') (back it up on a USB drive)"
}
