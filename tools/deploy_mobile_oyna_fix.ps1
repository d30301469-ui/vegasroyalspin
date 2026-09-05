# Deploy mobile OYNA button fix (home overlay + CSS + footer double-toggle).
$ErrorActionPreference = 'Stop'
$pscp = 'C:\Program Files\PuTTY\pscp.exe'
$plink = 'C:\Program Files\PuTTY\plink.exe'
$hk = 'SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$hostName = 'root@13.140.159.112'
$root = Split-Path -Parent $PSScriptRoot
$pw = $env:VRS_DEPLOY_PASS
if (-not $pw) { throw 'Set VRS_DEPLOY_PASS' }

$uploads = @(
  @{ Local = 'assets/js/home.js'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/js/home.js' },
  @{ Local = 'assets/css/mobile-home.css'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/css/mobile-home.css' },
  @{ Local = 'views/partials/main-content.php'; Remote = '/www/wwwroot/vegasroyalspin.com/views/partials/main-content.php' },
  @{ Local = 'mobile/views/partials/footer.php'; Remote = '/www/wwwroot/vegasroyalspin.com/mobile/views/partials/footer.php' }
)

foreach ($item in $uploads) {
  $local = Join-Path $root ($item.Local -replace '/', '\')
  if (-not (Test-Path -LiteralPath $local)) { throw "Missing $local" }
  Write-Host "UPLOAD $($item.Local)"
  & $pscp -batch -pw $pw -hostkey $hk $local "${hostName}:$($item.Remote)"
  if ($LASTEXITCODE -ne 0) { throw "pscp failed $($item.Local)" }
}

& $plink -batch -pw $pw -hostkey $hk $hostName @"
grep -n '__homeGameCardActivate\|opacity: 1 !important\|__homeGameCardActivate ===' \
  /www/wwwroot/vegasroyalspin.com/assets/js/home.js \
  /www/wwwroot/vegasroyalspin.com/assets/css/mobile-home.css \
  /www/wwwroot/vegasroyalspin.com/views/partials/main-content.php \
  /www/wwwroot/vegasroyalspin.com/mobile/views/partials/footer.php | head -30
php -l /www/wwwroot/vegasroyalspin.com/views/partials/main-content.php
php -l /www/wwwroot/vegasroyalspin.com/mobile/views/partials/footer.php
echo DEPLOY_OK
"@
Write-Host 'DEPLOY OK'
