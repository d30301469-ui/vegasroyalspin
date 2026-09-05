# Deploy mobile smart-menu header position fix.
# Usage:
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_mobile_smart_menu_fix.ps1

$ErrorActionPreference = 'Stop'

$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$base = '/www/wwwroot/vegasroyalspin.com'
$root = Split-Path -Parent $PSScriptRoot

$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'SSH password required (set VRS_DEPLOY_PASS).' }

$files = @(
    'assets/css/mobile-bc-header.css',
    'assets/css/mobile-smart-panel.css',
    'assets/css/mobile-bc-custom.css'
)

foreach ($rel in $files) {
    $local = Join-Path $root ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    $remote = "$base/$rel"
    Write-Host "UPLOAD $rel -> $remote"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$remote"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $rel" }
}

Write-Host 'DEPLOY OK — mobil header sag akilli menu ikonunu Ctrl+F5 sonrasi kontrol edin.'
