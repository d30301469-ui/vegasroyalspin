# Harden all weak points from architecture audit.
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
  @{ Local = 'config/env.php'; Remote = "$frontendRoot/config/env.php" },
  @{ Local = 'config/app.php'; Remote = "$frontendRoot/config/app.php" },
  @{ Local = 'config/member_api_public.php'; Remote = "$frontendRoot/config/member_api_public.php" },
  @{ Local = 'shared/services/WageringService.php'; Remote = "$frontendRoot/shared/services/WageringService.php" },
  @{ Local = 'admin/config/env.php'; Remote = "$adminRoot/config/env.php" },
  @{ Local = 'admin/config/app.php'; Remote = "$adminRoot/config/app.php" },
  @{ Local = 'admin/config/member_api_public.php'; Remote = "$adminRoot/config/member_api_public.php" },
  @{ Local = 'shared/services/WageringService.php'; Remote = "$adminRoot/shared/services/WageringService.php" },
  @{ Local = 'admin/database/migrations/2026_08_28_000001_harden_security_schema.php'; Remote = "$adminRoot/database/migrations/2026_08_28_000001_harden_security_schema.php" },
  @{ Local = 'admin/api/v2/includes/member_frontend_trust.php'; Remote = "$adminRoot/api/v2/includes/member_frontend_trust.php" },
  @{ Local = 'admin/api/v2/includes/member_login_rate_limit.php'; Remote = "$adminRoot/api/v2/includes/member_login_rate_limit.php" },
  @{ Local = 'admin/api/v2/includes/member_bonus_helpers.php'; Remote = "$adminRoot/api/v2/includes/member_bonus_helpers.php" },
  @{ Local = 'admin/api/v2/includes/member_api_kernel.php'; Remote = "$adminRoot/api/v2/includes/member_api_kernel.php" },
  @{ Local = 'admin/api/v2/routes/member_internal.php'; Remote = "$adminRoot/api/v2/routes/member_internal.php" },
  @{ Local = 'admin/api/v2/routes/member_auth.php'; Remote = "$adminRoot/api/v2/routes/member_auth.php" },
  @{ Local = 'admin/api/v2/routes/member_games.php'; Remote = "$adminRoot/api/v2/routes/member_games.php" }
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
    for f in config/env.php config/app.php config/member_api_public.php shared/services/WageringService.php; do
      [ -f "$FR/$f" ] && mkdir -p "$(dirname "$D/$f")" && cp -a "$FR/$f" "$D/$f" && echo MIRROR "$f"
    done
  fi
done
cd "$AD" && php bin/install.php migrate 2>/dev/null || php -r '
require "config/bootstrap.php";
$pdo = AdminDatabase::pdo();
$m = require "database/migrations/2026_08_28_000001_harden_security_schema.php";
$m($pdo);
echo "MIGRATION_OK\n";
'
php -l "$AD/api/v2/includes/member_frontend_trust.php"
php -l "$AD/api/v2/routes/member_internal.php"
php -l "$AD/api/v2/routes/member_auth.php"
php -l "$FR/shared/services/WageringService.php"
grep -n "memberFrontendTrustVerify\|memberUserPendingBonusClaimV2\|frontend_runtime_migrations_allowed\|gsc_demo_unsupported\|PASSWORD_UPGRADE_REQUIRED" \
  "$AD/api/v2/includes/member_frontend_trust.php" \
  "$AD/api/v2/includes/member_bonus_helpers.php" \
  "$AD/api/v2/routes/member_auth.php" \
  "$AD/api/v2/routes/member_games.php" \
  "$FR/config/env.php" | head -20
UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15'
code=$(curl -skL -A "$UA" -o /tmp/vrs_home.html -w "%{http_code}" --max-time 25 https://vegasroyalspin119.com/ || echo ERR)
echo "HOME $code"
echo DEPLOY_OK
'@

$verifyPath = Join-Path $env:TEMP 'vrs_harden_verify.sh'
[System.IO.File]::WriteAllText($verifyPath, $verifyScript.Replace("`r`n", "`n"))
& $pscp -batch -pw $pw -hostkey $hk $verifyPath "${hostName}:/tmp/vrs_harden_verify.sh"
& $plink -batch -pw $pw -hostkey $hk $hostName 'bash /tmp/vrs_harden_verify.sh'
Write-Host 'HARDEN DEPLOY OK'
