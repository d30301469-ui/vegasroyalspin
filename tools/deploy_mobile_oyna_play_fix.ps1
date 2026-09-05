# Deploy mobile SURFACE / OYNA / play redirect fixes.
$ErrorActionPreference = 'Stop'
$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$root = Split-Path -Parent $PSScriptRoot
$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'Set VRS_DEPLOY_PASS' }

$uploads = @(
  @{ Local = 'core/shell_nav.php'; Remote = '/www/wwwroot/vegasroyalspin.com/core/shell_nav.php' },
  @{ Local = 'core/bootstrap.php'; Remote = '/www/wwwroot/vegasroyalspin.com/core/bootstrap.php' },
  @{ Local = 'mobile/views/pages/home.php'; Remote = '/www/wwwroot/vegasroyalspin.com/mobile/views/pages/home.php' },
  @{ Local = 'assets/js/home.js'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/js/home.js' },
  @{ Local = 'assets/js/slot.js'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/js/slot.js' },
  @{ Local = 'assets/js/game-wallet-picker.js'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/js/game-wallet-picker.js' },
  @{ Local = 'pages/play.php'; Remote = '/www/wwwroot/vegasroyalspin.com/pages/play.php' }
)

foreach ($item in $uploads) {
  $local = Join-Path $root ($item.Local -replace '/', '\')
  if (-not (Test-Path -LiteralPath $local)) { throw "Missing $local" }
  Write-Host "UPLOAD $($item.Local)"
  & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
  if ($LASTEXITCODE -ne 0) { throw "pscp failed $($item.Local)" }
}

& $plink -batch -pw $pw -hostkey $hk $hostName @'
set -e
ROOT=/www/wwwroot/vegasroyalspin.com
# Mirror site if present
for D in /www/wwwroot/vegasroyalspin119.com /www/wwwroot/m.vegasroyalspin.com /www/wwwroot/m.vegasroyalspin119.com; do
  if [ -d "$D" ] && [ "$D" != "$ROOT" ]; then
    for f in core/bootstrap.php mobile/views/pages/home.php assets/js/home.js assets/js/slot.js assets/js/game-wallet-picker.js pages/play.php; do
      if [ -f "$ROOT/$f" ]; then
        mkdir -p "$(dirname "$D/$f")"
        cp -a "$ROOT/$f" "$D/$f"
        echo "MIRRORED $f -> $D"
      fi
    done
  fi
done

php -l "$ROOT/core/bootstrap.php"
php -l "$ROOT/mobile/views/pages/home.php"
php -l "$ROOT/pages/play.php"
grep -n "surfaceUa\|isMobilePlayLaunchMode\|wallet.empty\|playIsMobileClient\|include MOBILE_PATH . '/views/partials/footer.php'" \
  "$ROOT/core/bootstrap.php" "$ROOT/assets/js/home.js" "$ROOT/assets/js/game-wallet-picker.js" \
  "$ROOT/pages/play.php" "$ROOT/mobile/views/pages/home.php" | head -40

UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
for URL in https://vegasroyalspin119.com/ https://m.vegasroyalspin119.com/; do
  echo "==== $URL"
  html=$(curl -skL -A "$UA" --max-time 25 "$URL" || true)
  echo "len ${#html}"
  echo "$html" | tr '\n' ' ' | grep -oE 'class="[^"]*(is-mobile|is-web|mobile-site)[^"]*"' | head -5
  echo "$html" | grep -c 'mobileFooter\|footer-bc\|__homeHandlePlayIntent\|open_mode' | head -5 || true
  echo "$html" | tail -c 400
  echo
done
echo DEPLOY_OK
'@
Write-Host 'DEPLOY OK'
