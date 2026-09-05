# Deploy system audit fixes (bonus/wagering/mobile/play).
$ErrorActionPreference = 'Stop'
$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$root = Split-Path -Parent $PSScriptRoot
$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'Set VRS_DEPLOY_PASS' }

$frontendRoot = '/www/wwwroot/vegasroyalspin.com'
$adminRoot = '/www/wwwroot/admin.vegasroyalspin.com'

$uploads = @(
  @{ Local = 'shared/services/WageringService.php'; Remote = "$frontendRoot/shared/services/WageringService.php" },
  @{ Local = 'shared/services/WageringService.php'; Remote = "$adminRoot/shared/services/WageringService.php" },
  @{ Local = 'core/helpers.php'; Remote = "$frontendRoot/core/helpers.php" },
  @{ Local = 'pages/play.php'; Remote = "$frontendRoot/pages/play.php" },
  @{ Local = 'admin/api/v2/includes/member_bonus_helpers.php'; Remote = "$adminRoot/api/v2/includes/member_bonus_helpers.php" },
  @{ Local = 'admin/api/v2/routes/member_bonuses.php'; Remote = "$adminRoot/api/v2/routes/member_bonuses.php" },
  @{ Local = 'admin/api/v2/routes/member_engagement.php'; Remote = "$adminRoot/api/v2/routes/member_engagement.php" },
  @{ Local = 'admin/api/v2/routes/member_games.php'; Remote = "$adminRoot/api/v2/routes/member_games.php" },
  @{ Local = 'admin/app/Controllers/AdminBonusClaimController.php'; Remote = "$adminRoot/app/Controllers/AdminBonusClaimController.php" }
)

foreach ($item in $uploads) {
  $local = Join-Path $root ($item.Local -replace '/', '\')
  if (-not (Test-Path -LiteralPath $local)) { throw "Missing $local" }
  Write-Host "UPLOAD $($item.Local) -> $($item.Remote)"
  & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
  if ($LASTEXITCODE -ne 0) { throw "pscp failed $($item.Local)" }
}

& $plink -batch -pw $pw -hostkey $hk $hostName @'
set -e
for D in /www/wwwroot/m.vegasroyalspin119.com /www/wwwroot/vegasroyalspin119.com; do
  if [ -d "$D" ] && [ "$(readlink -f "$D" 2>/dev/null || echo "$D")" != "$(readlink -f /www/wwwroot/vegasroyalspin.com)" ]; then
    for f in shared/services/WageringService.php core/helpers.php pages/play.php; do
      src="/www/wwwroot/vegasroyalspin.com/$f"
      if [ -f "$src" ]; then mkdir -p "$(dirname "$D/$f")"; cp -a "$src" "$D/$f"; echo "MIRROR $f -> $D"; fi
    done
  fi
done
php -l /www/wwwroot/admin.vegasroyalspin.com/api/v2/includes/member_bonus_helpers.php
php -l /www/wwwroot/admin.vegasroyalspin.com/api/v2/routes/member_bonuses.php
php -l /www/wwwroot/admin.vegasroyalspin.com/app/Controllers/AdminBonusClaimController.php
php -l /www/wwwroot/vegasroyalspin.com/shared/services/WageringService.php
php -l /www/wwwroot/vegasroyalspin.com/pages/play.php
grep -n "memberInsertBonusClaimRequestV2\|ORDER BY granted_at\|playIsMobileClient) {" \
  /www/wwwroot/admin.vegasroyalspin.com/api/v2/includes/member_bonus_helpers.php \
  /www/wwwroot/vegasroyalspin.com/shared/services/WageringService.php \
  /www/wwwroot/vegasroyalspin.com/pages/play.php | head -20
echo DEPLOY_OK
'@
Write-Host 'DEPLOY OK'
