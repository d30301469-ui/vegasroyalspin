# Deploy desktop profile sidebar bonus/loyalty spacing fix.
# Usage:
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_profile_sidebar_gap_fix.ps1

$ErrorActionPreference = 'Stop'

$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$base = '/www/wwwroot/vegasroyalspin.com'
$root = Split-Path -Parent $PSScriptRoot

$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'SSH password required (set VRS_DEPLOY_PASS).' }

$rel = 'assets/css/profile-cm622-fix.css'
$local = Join-Path $root ($rel -replace '/', '\')
if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
Write-Host "UPLOAD $rel"
& $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$base/$rel"
if ($LASTEXITCODE -ne 0) { throw "pscp failed: $rel" }

Write-Host 'DEPLOY OK — profil modal sidebar boslugunu Ctrl+F5 sonrasi kontrol edin.'
