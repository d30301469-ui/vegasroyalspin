# Deploy licence logo + console/API silence fixes to production.
# Usage (PowerShell):
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_licence_console_fix.ps1

$ErrorActionPreference = 'Stop'

$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$base = '/www/wwwroot/vegasroyalspin.com'
$root = Split-Path -Parent $PSScriptRoot

$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) {
    $secure = Read-Host -AsSecureString 'SSH password for 13.140.159.112'
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { $pw = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}
if (-not $pw) { throw 'SSH password required.' }

$files = @(
    'views/partials/footer-bc-data.php',
    'views/partials/footer-bc.php',
    'mobile/views/partials/mobile-footer-bc.php',
    'assets/css/layout-footer.css',
    'assets/css/mobile-footer.css',
    'assets/footer/licence-widget.html',
    'views/partials/member-api-layout-script.php',
    'views/partials/layout-after-header.php',
    'mobile/views/partials/layout-after-header.php',
    'assets/js/member-api-console.js',
    'assets/js/home.js',
    'assets/js/header.js',
    'assets/js/profile-api.js',
    'assets/js/profile.js',
    'assets/js/play-page.js',
    'assets/js/footer.js',
    'assets/js/register.js',
    'assets/js/jackpot.js',
    'views/partials/main-content.php',
    'views/layouts/head.php',
    'views/layouts/head_full.php',
    'assets/js/pwa-register.js',
    'assets/js/login.js',
    'assets/js/reset-password.js',
    'mobile/assets/js/mobile-header.js'
)

foreach ($rel in $files) {
    $local = Join-Path $root ($rel -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    $remote = "$base/$rel"
    Write-Host "UPLOAD $rel -> $remote"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$remote"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $rel" }
}

Write-Host 'DEPLOY OK — Ctrl+F5 ile footer ve console kontrol edin.'
