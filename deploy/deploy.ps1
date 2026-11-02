<#
.SYNOPSIS
    Test and deploy MEPCOM HRMIS (PCG_CORE) to Hermes (https://mepcom-hrmis.<tailnet>.ts.net).

.DESCRIPTION
    Run from the PCG_CORE folder on the PC. Deploys the commit checked out now:

    1. Checks everything is committed.
    2. Refuses if .env files, database dumps or personnel uploads are tracked.
    3. Runs the PHP tests and stops on the first failure (skip with -SkipTests).
    4. Copies deploy/ to ~/mepcom-hrmis-deploy and pushes HEAD to the server's "live" branch.
    5. Builds and switches the release on the server (switches back by itself if the
       new release does not answer), then checks the site.

    Only /srv/mepcom-hrmis is changed on the server; see deploy/README.md.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Migrate
    powershell -ExecutionPolicy Bypass -File deploy\deploy.ps1 -Rollback
#>
param(
    # Apply pending database migrations (asks for confirmation, backs up first).
    [switch]$Migrate,
    # Switch the site back to the previous release.
    [switch]$Rollback,
    # Do not run the PHP tests first.
    [switch]$SkipTests,
    # SSH login on Hermes, e.g. user@host. Not stored in git: taken from
    # -Server/-Port, else $env:MEPCOM_DEPLOY_SERVER / $env:MEPCOM_DEPLOY_PORT,
    # else deploy\target.local (asked once and saved there; git ignores it).
    [string]$Server = '',
    [int]$Port = 0
)

$ErrorActionPreference = 'Stop'

# ---- where to deploy (kept out of the public repository)
$TargetFile = Join-Path $PSScriptRoot 'target.local'
if (-not $Server -and $env:MEPCOM_DEPLOY_SERVER) { $Server = $env:MEPCOM_DEPLOY_SERVER }
if (-not $Port -and $env:MEPCOM_DEPLOY_PORT) { $Port = [int]$env:MEPCOM_DEPLOY_PORT }
if ((-not $Server -or -not $Port) -and (Test-Path $TargetFile)) {
    $saved = @(Get-Content $TargetFile | Where-Object { $_.Trim() -and -not $_.Trim().StartsWith('#') })
    if (-not $Server -and $saved.Count -ge 1) { $Server = $saved[0].Trim() }
    if (-not $Port -and $saved.Count -ge 2) { $Port = [int]$saved[1].Trim() }
}
if (-not $Server) {
    $Server = (Read-Host 'SSH login for the server (user@host)').Trim()
    $p = (Read-Host 'SSH port (Enter for 22)').Trim()
    $Port = if ($p) { [int]$p } else { 22 }
    Set-Content -Path $TargetFile -Value @('# deploy target (not in git)', $Server, $Port)
    Write-Host "Saved to $TargetFile (ignored by git)."
}
if (-not $Port) { $Port = 22 }

$Root = Split-Path -Parent $PSScriptRoot
$Ssh = @('-o', 'BatchMode=yes', '-p', "$Port", $Server)
$Remote = "ssh://${Server}:${Port}/srv/mepcom-hrmis/repo.git"

function Step([string]$Text) { Write-Host "`n==> $Text" -ForegroundColor Cyan }

function Fail([string]$Text) {
    Write-Host "`nDEPLOY STOPPED: $Text" -ForegroundColor Red
    exit 1
}

function Invoke-Checked([string]$What, [scriptblock]$Block) {
    & $Block
    if ($LASTEXITCODE -ne 0) { Fail "$What failed (exit code $LASTEXITCODE)." }
}

Set-Location $Root

if ($Rollback) {
    Step 'Switching the site back to the previous release'
    & ssh @Ssh 'bash ~/mepcom-hrmis-deploy/release.sh --rollback'
    exit $LASTEXITCODE
}

Step 'Checking the working tree'
if (git status --porcelain --untracked-files=no) { Fail 'there are uncommitted changes. Commit them first.' }

Step 'Checking that no sensitive files are tracked'
# Schema-only SQL files that belong in git (no data; checked for INSERTs below).
$allowedSql = @('database/audit/inspect_schema.sql', 'database/dev/schema_inferred.sql')
$sensitive = git ls-files | Where-Object { $allowedSql -notcontains $_ } | Where-Object {
    $_ -match '(^|/)\.env$|\.sql(\.gz)?$|\.sqlite$|^public/(documents_toupload|201_files|disciplinary_actions|selfies)/|^selfies/|storage/app/private/(?!\.gitignore$)'
}
if ($sensitive) { Fail ("these files must not be in git:`n  " + ($sensitive -join "`n  ")) }
foreach ($f in $allowedSql) {
    if ((Test-Path $f) -and (Select-String -Path $f -Pattern 'INSERT\s+INTO' -Quiet)) { Fail "$f must stay schema-only but contains INSERT statements." }
}

if (-not $SkipTests) {
    Step 'Running the PHP tests'
    # The tests use an in-memory SQLite database (phpunit.xml).
    if (-not ((& php -m) -match '^pdo_sqlite$')) {
        Fail ("PHP on this PC has no pdo_sqlite, which the tests need. In the php.ini shown by 'php --ini', " +
              "remove the ';' before 'extension=pdo_sqlite' and 'extension=sqlite3', save, and run this again " +
              "(or deploy without tests: -SkipTests).")
    }
    Invoke-Checked 'The PHP tests' { & php artisan test --stop-on-failure }
}

Step 'Checking the server connection'
Invoke-Checked 'SSH to the server' { & ssh @Ssh 'test -d /srv/mepcom-hrmis/repo.git' }

Step 'Sending the deploy scripts and the code to the server'
Invoke-Checked 'Copying deploy/' { & scp -q -P $Port -r deploy "${Server}:mepcom-hrmis-deploy.new" }
Invoke-Checked 'Replacing ~/mepcom-hrmis-deploy' { & ssh @Ssh 'rm -rf ~/mepcom-hrmis-deploy && mv ~/mepcom-hrmis-deploy.new ~/mepcom-hrmis-deploy && sed -i "s/\r$//" ~/mepcom-hrmis-deploy/*.sh ~/mepcom-hrmis-deploy/*.template' }
Invoke-Checked 'Pushing to the server' { git push --force $Remote HEAD:refs/heads/live }

$release = 'bash ~/mepcom-hrmis-deploy/release.sh live'
if ($Migrate) {
    $answer = Read-Host 'Apply pending database migrations? A backup runs first. Type YES to continue'
    if ($answer -ne 'YES') { Fail 'migrations were not confirmed.' }
    $release = 'bash ~/mepcom-hrmis-deploy/backup.sh && MIGRATE=1 bash ~/mepcom-hrmis-deploy/release.sh live'
}

Step 'Building and switching the release on the server'
Invoke-Checked 'The release on the server' { & ssh @Ssh $release }

Step 'Checking the site'
$expected = (git rev-parse --short=8 HEAD).Trim()
$live = (& ssh @Ssh 'cat /srv/mepcom-hrmis/current/REVISION').Trim()
$appPort = (& ssh @Ssh 'cat /srv/mepcom-hrmis/shared/PORT').Trim()
Write-Host "  live revision: $live (expected $expected)"
$problems = @()
if ($live -ne $expected) { $problems += 'revision' }
foreach ($path in '/', '/login', '/admin/login') {
    $code = (& ssh @Ssh "curl -s -o /dev/null -m 30 -w '%{http_code}' http://127.0.0.1:$appPort$path").Trim()
    Write-Host ("  {0,-13} HTTP {1}" -f $path, $code)
    if ($code -ne '200') { $problems += $path }
}
if ($problems) {
    Fail ("the site check failed ($($problems -join ', ')). Roll back with: deploy\deploy.ps1 -Rollback")
}

$site = (& ssh @Ssh "grep -m1 '^APP_URL=' /srv/mepcom-hrmis/shared/.env | cut -d= -f2-").Trim()
Write-Host "`nDEPLOYED $expected to $site" -ForegroundColor Green
