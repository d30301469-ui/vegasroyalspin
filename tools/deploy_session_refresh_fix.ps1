# Fix: session lost on page refresh + header balance missing.
$ErrorActionPreference = 'Stop'
$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = if ($env:VRS_DEPLOY_HOSTKEY) { $env:VRS_DEPLOY_HOSTKEY } else { 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI' }
$hostName = if ($env:VRS_DEPLOY_HOST) { $env:VRS_DEPLOY_HOST } else { 'root@13.140.159.112' }
$root = Split-Path -Parent $PSScriptRoot
$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'Set VRS_DEPLOY_PASS' }

$frontendRoot = '/www/wwwroot/vegasroyalspin.com'

$uploads = @(
  @{ Local = 'shared/services/MemberJwtVerify.php'; Remote = "$frontendRoot/shared/services/MemberJwtVerify.php" },
  @{ Local = 'shared/services/BackendMemberApiProxy.php'; Remote = "$frontendRoot/shared/services/BackendMemberApiProxy.php" },
  @{ Local = 'config/member_api_public.php'; Remote = "$frontendRoot/config/member_api_public.php" },
  @{ Local = 'assets/js/header-balance-poll.js'; Remote = "$frontendRoot/assets/js/header-balance-poll.js" },
  @{ Local = 'assets/js/header.js'; Remote = "$frontendRoot/assets/js/header.js" }
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
for D in /www/wwwroot/m.vegasroyalspin119.com /www/wwwroot/vegasroyalspin119.com; do
  if [ -d "$D" ] && [ "$(readlink -f "$D" 2>/dev/null || echo "$D")" != "$(readlink -f "$FR")" ]; then
    cp -a "$FR/shared/services/MemberJwtVerify.php" "$D/shared/services/MemberJwtVerify.php" 2>/dev/null || true
    cp -a "$FR/shared/services/BackendMemberApiProxy.php" "$D/shared/services/BackendMemberApiProxy.php" 2>/dev/null || true
    cp -a "$FR/config/member_api_public.php" "$D/config/member_api_public.php" 2>/dev/null || true
    cp -a "$FR/assets/js/header-balance-poll.js" "$D/assets/js/header-balance-poll.js" 2>/dev/null || true
    cp -a "$FR/assets/js/header.js" "$D/assets/js/header.js" 2>/dev/null || true
    echo MIRROR "$D"
  fi
done
grep -n "payloadIfValid\|userIdFromJwt" "$FR/shared/services/MemberJwtVerify.php" | head -3
grep -n "syncPhpSessionFromRestoreJwt" "$FR/shared/services/BackendMemberApiProxy.php" | head -3
grep -n "HttpOnly restore cookie may exist" "$FR/assets/js/header-balance-poll.js" | head -2
php -l "$FR/shared/services/MemberJwtVerify.php"
php -l "$FR/shared/services/BackendMemberApiProxy.php"
php -l "$FR/config/member_api_public.php"
echo SESSION_REFRESH_FIX_OK
'@

$verifyPath = Join-Path $env:TEMP 'vrs_session_refresh_fix_verify.sh'
[System.IO.File]::WriteAllText($verifyPath, $verifyScript.Replace("`r`n", "`n"))
& $pscp -batch -pw $pw -hostkey $hk $verifyPath "${hostName}:/tmp/vrs_session_refresh_fix_verify.sh"
& $plink -batch -pw $pw -hostkey $hk $hostName 'bash /tmp/vrs_session_refresh_fix_verify.sh'
Write-Host 'SESSION REFRESH FIX DEPLOYED'
