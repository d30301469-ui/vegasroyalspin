# Hotfix: game-launch CSRF after trust hardening.
$ErrorActionPreference = 'Stop'
$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = if ($env:VRS_DEPLOY_HOSTKEY) { $env:VRS_DEPLOY_HOSTKEY } else { 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI' }
$hostName = if ($env:VRS_DEPLOY_HOST) { $env:VRS_DEPLOY_HOST } else { 'root@13.140.159.112' }
$root = Split-Path -Parent $PSScriptRoot
$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'Set VRS_DEPLOY_PASS' }

$frontendRoot = '/www/wwwroot/vegasroyalspin.com'
$adminRoot = '/www/wwwroot/admin.vegasroyalspin.com'

$uploads = @(
  @{ Local = 'config/member_api_public.php'; Remote = "$frontendRoot/config/member_api_public.php" },
  @{ Local = 'admin/config/member_api_public.php'; Remote = "$adminRoot/config/member_api_public.php" },
  @{ Local = 'admin/config/app.php'; Remote = "$adminRoot/config/app.php" },
  @{ Local = 'admin/api/v2/includes/member_frontend_trust.php'; Remote = "$adminRoot/api/v2/includes/member_frontend_trust.php" },
  @{ Local = 'admin/api/v2/includes/member_api_kernel.php'; Remote = "$adminRoot/api/v2/includes/member_api_kernel.php" }
)

foreach ($item in $uploads) {
  $local = Join-Path $root ($item.Local -replace '/', '\')
  if (-not (Test-Path -LiteralPath $local)) { throw "Missing $local" }
  Write-Host "UPLOAD $($item.Local)"
  $remoteDir = ($item.Remote -replace '[^/]+$','').TrimEnd('/')
  & $plink -batch -pw $pw -hostkey $hk $hostName "mkdir -p '$remoteDir'" | Out-Null
  & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
  if ($LASTEXITCODE -ne 0) { throw "pscp failed $($item.Local)" }
}

$verifyScript = @'
#!/bin/bash
set -e
FR=/www/wwwroot/vegasroyalspin.com
AD=/www/wwwroot/admin.vegasroyalspin.com
for D in /www/wwwroot/m.vegasroyalspin119.com /www/wwwroot/vegasroyalspin119.com; do
  if [ -d "$D" ] && [ "$(readlink -f "$D" 2>/dev/null || echo "$D")" != "$(readlink -f "$FR")" ]; then
    cp -a "$FR/config/member_api_public.php" "$D/config/member_api_public.php" && echo MIRROR member_api_public.php
  fi
done
php -l "$AD/api/v2/includes/member_frontend_trust.php"
php -l "$AD/api/v2/includes/member_api_kernel.php"
grep -n "memberRequestHasFrontendTrust\|memberFrontendTrustIpAllowed" "$AD/api/v2/includes/member_api_kernel.php" "$AD/api/v2/includes/member_frontend_trust.php"
echo CSRF_GAME_LAUNCH_FIX_OK
'@

$verifyPath = Join-Path $env:TEMP 'vrs_csrf_fix_verify.sh'
[System.IO.File]::WriteAllText($verifyPath, $verifyScript.Replace("`r`n", "`n"))
& $pscp -batch -pw $pw -hostkey $hk $verifyPath "${hostName}:/tmp/vrs_csrf_fix_verify.sh"
& $plink -batch -pw $pw -hostkey $hk $hostName 'bash /tmp/vrs_csrf_fix_verify.sh'
Write-Host 'CSRF GAME LAUNCH FIX DEPLOYED'
