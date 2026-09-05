# Full system audit + recommended steps deploy.
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
  # Frontend
  @{ Local = 'core/bootstrap.php'; Remote = "$frontendRoot/core/bootstrap.php" },
  @{ Local = 'core/helpers.php'; Remote = "$frontendRoot/core/helpers.php" },
  @{ Local = 'core/shell_nav.php'; Remote = "$frontendRoot/core/shell_nav.php" },
  @{ Local = 'pages/play.php'; Remote = "$frontendRoot/pages/play.php" },
  @{ Local = 'assets/js/auth-shared.js'; Remote = "$frontendRoot/assets/js/auth-shared.js" },
  @{ Local = 'assets/js/home.js'; Remote = "$frontendRoot/assets/js/home.js" },
  @{ Local = 'assets/js/slot.js'; Remote = "$frontendRoot/assets/js/slot.js" },
  @{ Local = 'assets/js/game-wallet-picker.js'; Remote = "$frontendRoot/assets/js/game-wallet-picker.js" },
  @{ Local = 'mobile/views/pages/home.php'; Remote = "$frontendRoot/mobile/views/pages/home.php" },
  @{ Local = 'shared/services/WageringService.php'; Remote = "$frontendRoot/shared/services/WageringService.php" },
  @{ Local = 'shared/services/MegaPayzService.php'; Remote = "$frontendRoot/shared/services/MegaPayzService.php" },
  # Admin / API
  @{ Local = 'shared/services/WageringService.php'; Remote = "$adminRoot/shared/services/WageringService.php" },
  @{ Local = 'shared/services/MegaPayzService.php'; Remote = "$adminRoot/shared/services/MegaPayzService.php" },
  @{ Local = 'admin/api/v2/includes/member_bonus_helpers.php'; Remote = "$adminRoot/api/v2/includes/member_bonus_helpers.php" },
  @{ Local = 'admin/api/v2/includes/member_login_rate_limit.php'; Remote = "$adminRoot/api/v2/includes/member_login_rate_limit.php" },
  @{ Local = 'admin/api/v2/includes/member_api_kernel.php'; Remote = "$adminRoot/api/v2/includes/member_api_kernel.php" },
  @{ Local = 'admin/api/v2/routes/member_bonuses.php'; Remote = "$adminRoot/api/v2/routes/member_bonuses.php" },
  @{ Local = 'admin/api/v2/routes/member_engagement.php'; Remote = "$adminRoot/api/v2/routes/member_engagement.php" },
  @{ Local = 'admin/api/v2/routes/member_games.php'; Remote = "$adminRoot/api/v2/routes/member_games.php" },
  @{ Local = 'admin/api/v2/routes/member_auth.php'; Remote = "$adminRoot/api/v2/routes/member_auth.php" },
  @{ Local = 'admin/app/Controllers/AdminBonusClaimController.php'; Remote = "$adminRoot/app/Controllers/AdminBonusClaimController.php" },
  @{ Local = 'admin/app/Controllers/AdminPromotionController.php'; Remote = "$adminRoot/app/Controllers/AdminPromotionController.php" }
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
    for f in core/bootstrap.php core/helpers.php core/shell_nav.php pages/play.php assets/js/auth-shared.js assets/js/home.js assets/js/slot.js assets/js/game-wallet-picker.js mobile/views/pages/home.php shared/services/WageringService.php shared/services/MegaPayzService.php; do
      [ -f "$FR/$f" ] && mkdir -p "$(dirname "$D/$f")" && cp -a "$FR/$f" "$D/$f" && echo MIRROR "$f"
    done
  fi
done
php -l "$AD/api/v2/includes/member_bonus_helpers.php"
php -l "$AD/api/v2/includes/member_login_rate_limit.php"
php -l "$AD/api/v2/routes/member_auth.php"
php -l "$AD/app/Controllers/AdminBonusClaimController.php"
php -l "$FR/assets/js/auth-shared.js" 2>/dev/null || true
php -l "$FR/shared/services/WageringService.php"
grep -n "memberInsertBonusClaimRequestV2\|memberLoginRateLimitCheck\|activeBonusWithdrawMessage\|supersedeActiveBonuses\|__MEMBER_JWT_MEMORY__\|app_member_restore" \
  "$AD/api/v2/includes/member_bonus_helpers.php" \
  "$AD/api/v2/includes/member_login_rate_limit.php" \
  "$AD/api/v2/routes/member_auth.php" \
  "$FR/assets/js/auth-shared.js" \
  "$FR/shared/services/WageringService.php" | head -30
UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
code=$(curl -skL -A "$UA" -o /tmp/vrs_home.html -w "%{http_code}" --max-time 25 https://vegasroyalspin119.com/ || echo ERR)
echo "HOME $code len=$(wc -c </tmp/vrs_home.html)"
grep -c "is-mobile" /tmp/vrs_home.html || true
echo DEPLOY_OK
'@

$verifyPath = Join-Path $env:TEMP 'vrs_deploy_verify.sh'
Set-Content -Path $verifyPath -Value $verifyScript -Encoding UTF8
& $pscp -batch -pw $pw -hostkey $hk $verifyPath "${hostName}:/tmp/vrs_deploy_verify.sh"
& $plink -batch -pw $pw -hostkey $hk root@13.140.159.112 'bash /tmp/vrs_deploy_verify.sh'
Write-Host 'DEPLOY OK'
