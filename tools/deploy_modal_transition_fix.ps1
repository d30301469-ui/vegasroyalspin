# Deploy profile modal transition speed fix.
# Usage:
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_modal_transition_fix.ps1

$ErrorActionPreference = 'Stop'

$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$base = '/www/wwwroot/vegasroyalspin.com'
$root = Split-Path -Parent $PSScriptRoot

$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'SSH password required (set VRS_DEPLOY_PASS).' }

$files = @(
    'assets/js/profile.js',
    'assets/css/profile-cm622-fix.css'
)

foreach ($rel in $files) {
    $local = Join-Path $root ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    Write-Host "UPLOAD $rel"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$base/$rel"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $rel" }
}

Write-Host 'DEPLOY OK — profil modal gecislerini Ctrl+F5 sonrasi test edin.'
