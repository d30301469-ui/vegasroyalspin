# Deploy mobile profile close-icon position fix.
# Usage:
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_mprofile_close_fix.ps1

$ErrorActionPreference = 'Stop'

$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$root = Split-Path -Parent $PSScriptRoot

$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'SSH password required (set VRS_DEPLOY_PASS).' }

$uploads = @(
    @{ Local = 'assets/css/mobile-bc-header.css'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/css/mobile-bc-header.css' },
    @{ Local = 'assets/css/mobile-profile-panel.css'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/css/mobile-profile-panel.css' }
)

foreach ($item in $uploads) {
    $local = Join-Path $root ($item.Local -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    Write-Host "UPLOAD $($item.Local)"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $($item.Local)" }
}

& $plink -batch -pw $pw -hostkey $hk $hostName @"
grep -n 'header-bc > .hdr-main-content-bc\|position: absolute !important' /www/wwwroot/vegasroyalspin.com/assets/css/mobile-bc-header.css /www/wwwroot/vegasroyalspin.com/assets/css/mobile-profile-panel.css | head -20
echo DEPLOY_OK
"@

Write-Host 'DEPLOY OK — mobil profil kapat ikonu sagda olmali (hard refresh).'
