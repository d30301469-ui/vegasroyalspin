# Deploy header SSR perf fix: short timeouts + 60s session cache for balance/loyalty.
# Usage:
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_header_perf_fix.ps1

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
    @{ Local = 'views/partials/header-init.php'; Remote = '/www/wwwroot/vegasroyalspin.com/views/partials/header-init.php' },
    @{ Local = 'shared/api/Loyalty.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/api/Loyalty.php' },
    @{ Local = 'shared/services/MemberViewDataService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/MemberViewDataService.php' },
    @{ Local = 'shared/services/BackendApiClient.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/BackendApiClient.php' },
    @{ Local = 'shared/api/Loyalty.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/shared/api/Loyalty.php' },
    @{ Local = 'shared/services/MemberViewDataService.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/shared/services/MemberViewDataService.php' },
    @{ Local = 'shared/services/BackendApiClient.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/shared/services/BackendApiClient.php' }
)

foreach ($item in $uploads) {
    $local = Join-Path $root ($item.Local -replace '/', '\')
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    Write-Host "UPLOAD $($item.Local) -> $($item.Remote)"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $($item.Local)" }
}

Write-Host 'VERIFY + soft reload PHP-FPM...'
& $plink -batch -pw $pw -hostkey $hk $hostName @"
php -l /www/wwwroot/vegasroyalspin.com/views/partials/header-init.php && \
php -l /www/wwwroot/vegasroyalspin.com/shared/api/Loyalty.php && \
php -l /www/wwwroot/vegasroyalspin.com/shared/services/MemberViewDataService.php && \
php -l /www/wwwroot/vegasroyalspin.com/shared/services/BackendApiClient.php && \
grep -n 'balanceForSession(2)\|__header_member_cache\|timeoutSeconds\|timeout <= 3' /www/wwwroot/vegasroyalspin.com/views/partials/header-init.php /www/wwwroot/vegasroyalspin.com/shared/api/Loyalty.php /www/wwwroot/vegasroyalspin.com/shared/services/MemberViewDataService.php /www/wwwroot/vegasroyalspin.com/shared/services/BackendApiClient.php | head -25 && \
kill -USR2 `$(pgrep -o php-fpm) 2>/dev/null || true; \
sleep 1; \
echo LOAD:`$(cat /proc/loadavg); \
echo ROGUE:`$(pgrep -af 'grep -RIn.*deploy' || echo none); \
echo FPM_BUSY_TAIL:`$(tail -n 30 /www/server/php/83/var/log/php-fpm.log 2>/dev/null | grep -c 'seems busy' || echo 0); \
echo DEPLOY_OK
"@

if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed.' }
Write-Host 'DEPLOY OK — header SSR artik 2s timeout + 60s cache kullanıyor.'
