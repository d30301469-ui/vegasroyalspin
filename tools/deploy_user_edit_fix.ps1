# Deploy admin user-edit fix to ALL known admin webroots on production.
# Usage (PowerShell):
#   $env:VRS_DEPLOY_PASS = 'YOUR_SSH_PASSWORD'
#   powershell -ExecutionPolicy Bypass -File tools\deploy_user_edit_fix.ps1

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
if (-not $pw) { throw 'SSH password required.' }

function Upload([string]$localRel, [string]$remotePath) {
    $local = Join-Path $root $localRel
    if (-not (Test-Path -LiteralPath $local)) { throw "Missing: $local" }
    Write-Host "UPLOAD $localRel -> $remotePath"
    & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$remotePath"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed: $localRel" }
}

$uiBases = @(
    '/www/wwwroot/admin.vegasroyalspin.com',
    '/www/wwwroot/vegasroyalspin.com/admin',
    '/www/wwwroot/default',
    '/www/wwwroot/ortaklik-vegasroyalspin.com'
)
foreach ($base in $uiBases) {
    Upload 'admin\admin-ui.js' "$base/admin-ui.js"
}

$appBases = @(
    '/www/wwwroot/admin.vegasroyalspin.com',
    '/www/wwwroot/vegasroyalspin.com/admin'
)
foreach ($base in $appBases) {
    Upload 'admin\app\Controllers\AdminUserController.php' "$base/app/Controllers/AdminUserController.php"
    Upload 'admin\app\Views\users\_edit_form.php' "$base/app/Views/users/_edit_form.php"
}

Write-Host 'VERIFY...'
& $plink -batch -pw $pw -hostkey $hk $hostName @"
set -e
echo SIZES
wc -c /www/wwwroot/admin.vegasroyalspin.com/admin-ui.js /www/wwwroot/vegasroyalspin.com/admin/admin-ui.js /www/wwwroot/default/admin-ui.js
echo AJAX
grep -c initAjaxForms /www/wwwroot/admin.vegasroyalspin.com/admin-ui.js /www/wwwroot/vegasroyalspin.com/admin/admin-ui.js /www/wwwroot/default/admin-ui.js
echo CONTROLLER
grep -c completeUserUpdate /www/wwwroot/admin.vegasroyalspin.com/app/Controllers/AdminUserController.php /www/wwwroot/vegasroyalspin.com/admin/app/Controllers/AdminUserController.php
echo VHOST
grep -Rnl 'admin.vegasroyalspin.com' /www/server/panel/vhost/nginx/ 2>/dev/null || true
echo LOCAL_CURL
curl -s -H 'Host: admin.vegasroyalspin.com' http://127.0.0.1/admin-ui.js | wc -c
curl -s -H 'Host: admin.vegasroyalspin.com' http://127.0.0.1/admin-ui.js | grep -c initAjaxForms || true
"@
if ($LASTEXITCODE -ne 0) { throw "verify failed: $LASTEXITCODE" }
Write-Host 'DEPLOY OK'
