# Private remote access through Tailscale Serve (tailnet only - NOT Funnel).
#   scripts\windows\tailscale-serve.ps1 on  [-Port 8000]
#   scripts\windows\tailscale-serve.ps1 off
#   scripts\windows\tailscale-serve.ps1 status
param(
    [Parameter(Mandatory = $true)][ValidateSet('on', 'off', 'status')][string]$Action,
    [int]$Port = 8000
)

if (-not (Get-Command tailscale -ErrorAction SilentlyContinue)) {
    Write-Host "Tailscale CLI not found. Install from https://tailscale.com/download/windows" -ForegroundColor Red
    exit 1
}

switch ($Action) {
    'on' {
        tailscale version
        # Proxies https://<this-pc>.<tailnet>.ts.net -> http://127.0.0.1:$Port
        tailscale serve --bg "http://127.0.0.1:$Port"
        tailscale serve status
        Write-Host "Make sure Funnel is OFF (public internet):" -ForegroundColor Yellow
        tailscale funnel status
    }
    'off' {
        tailscale serve reset
        tailscale serve status
    }
    'status' {
        tailscale status
        tailscale serve status
        tailscale funnel status
    }
}
