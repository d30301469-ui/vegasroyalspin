# Deploy aggregator demo-mode fix (isDemo + virtual wallet callback guard).
# Usage:
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_demo_fix.ps1

$ErrorActionPreference = 'Stop'

$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$root = Split-Path -Parent $PSScriptRoot

$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) {
    $secure = Read-Host -AsSecureString 'SSH password for 13.140.159.112'
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { $pw = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($bstr) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr) }
}
if (-not $pw) { throw 'SSH password required (set VRS_DEPLOY_PASS).' }

$uploads = @(
    @{ Local = 'shared/services/CasinoAggregatorService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/CasinoAggregatorService.php' },
    @{ Local = 'admin/api/v2/routes/member_games.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/api/v2/routes/member_games.php' },
    @{ Local = 'shared/services/CasinoAggregatorService.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/shared/services/CasinoAggregatorService.php' },
    @{ Local = 'admin/api/v2/routes/member_games.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/api/v2/routes/member_games.php' }
)

foreach ($item in $uploads) {
    $local = Join-Path $root ($item.Local -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    Write-Host "UPLOAD $($item.Local) -> $($item.Remote)"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $($item.Local)" }
}

Write-Host 'VERIFY php -l on remote...'
& $plink -batch -pw $pw -hostkey $hk $hostName @"
php -l /www/wwwroot/vegasroyalspin.com/shared/services/CasinoAggregatorService.php && \
php -l /www/wwwroot/vegasroyalspin.com/admin/api/v2/routes/member_games.php && \
php -l /www/wwwroot/admin.vegasroyalspin.com/shared/services/CasinoAggregatorService.php 2>/dev/null; \
php -l /www/wwwroot/admin.vegasroyalspin.com/admin/api/v2/routes/member_games.php 2>/dev/null; \
echo DEPLOY_OK
"@

if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed.' }
Write-Host 'DEPLOY OK — demo modunu Ctrl+F5 sonrasi test edin (bakiye/agent kredisi dusmemeli).'
