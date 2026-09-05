# Fix remaining session issues: guest 401 spam, heartbeat, auth-shared hydrate gating.
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
  @{ Local = 'views/partials/member-api-layout-script.php'; Remote = "$frontendRoot/views/partials/member-api-layout-script.php" },
  @{ Local = 'assets/js/auth-shared.js'; Remote = "$frontendRoot/assets/js/auth-shared.js" },
  @{ Local = 'assets/js/session-heartbeat.js'; Remote = "$frontendRoot/assets/js/session-heartbeat.js" },
  @{ Local = 'assets/js/header-balance-poll.js'; Remote = "$frontendRoot/assets/js/header-balance-poll.js" },
  @{ Local = 'assets/js/header.js'; Remote = "$frontendRoot/assets/js/header.js" },
  @{ Local = 'assets/js/play-page.js'; Remote = "$frontendRoot/assets/js/play-page.js" }
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
MIRROR_FILES=(
  views/partials/member-api-layout-script.php
  assets/js/auth-shared.js
  assets/js/session-heartbeat.js
  assets/js/header-balance-poll.js
  assets/js/header.js
  assets/js/play-page.js
)
for D in /www/wwwroot/m.vegasroyalspin119.com /www/wwwroot/vegasroyalspin119.com; do
  if [ -d "$D" ] && [ "$(readlink -f "$D" 2>/dev/null || echo "$D")" != "$(readlink -f "$FR")" ]; then
    for rel in "${MIRROR_FILES[@]}"; do
      mkdir -p "$(dirname "$D/$rel")"
      cp -a "$FR/$rel" "$D/$rel" 2>/dev/null || true
    done
    echo MIRROR "$D"
  fi
done
grep -n "has_restore_cookie\|shouldAttemptSessionHydrate" "$FR/views/partials/member-api-layout-script.php" "$FR/assets/js/auth-shared.js" | head -8
grep -n "shouldAttemptSessionHydrate" "$FR/assets/js/session-heartbeat.js" "$FR/assets/js/header-balance-poll.js" | head -6
php -l "$FR/views/partials/member-api-layout-script.php"
echo SESSION_REMAINING_FIX_OK
'@

$verifyPath = Join-Path $env:TEMP 'vrs_session_remaining_fix_verify.sh'
[System.IO.File]::WriteAllText($verifyPath, $verifyScript.Replace("`r`n", "`n"))
& $pscp -batch -pw $pw -hostkey $hk $verifyPath "${hostName}:/tmp/vrs_session_remaining_fix_verify.sh"
& $plink -batch -pw $pw -hostkey $hk $hostName 'bash /tmp/vrs_session_remaining_fix_verify.sh'
Write-Host 'SESSION REMAINING FIX DEPLOYED'
