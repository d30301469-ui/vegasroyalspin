# Sportbook page load perf: lightweight shell, deferred wallet sync, launch timeout.
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'pages\sportbook.php'; Remote = '/www/wwwroot/vegasroyalspin.com/pages/sportbook.php' },
    @{ Local = Join-Path $root 'core\bootstrap.php'; Remote = '/www/wwwroot/vegasroyalspin.com/core/bootstrap.php' },
    @{ Local = Join-Path $root 'views\partials\header-init.php'; Remote = '/www/wwwroot/vegasroyalspin.com/views/partials/header-init.php' },
    @{ Local = Join-Path $root 'views\layouts\head.php'; Remote = '/www/wwwroot/vegasroyalspin.com/views/layouts/head.php' },
    @{ Local = Join-Path $root 'views\partials\layout-after-header.php'; Remote = '/www/wwwroot/vegasroyalspin.com/views/partials/layout-after-header.php' },
    @{ Local = Join-Path $root 'shared\services\SportsbookService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/SportsbookService.php' },
    @{ Local = Join-Path $root 'shared\services\BackendMemberApiProxy.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/BackendMemberApiProxy.php' }
)
foreach ($f in $files) {
    if (-not (Test-Path $f.Local)) { throw "Missing $($f.Local)" }
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed $($f.Local)" }
}

$cmd = @'
php -l /www/wwwroot/vegasroyalspin.com/pages/sportbook.php
php -l /www/wwwroot/vegasroyalspin.com/shared/services/SportsbookService.php
t0=$(date +%s%3N)
code=$(curl -s -o /tmp/sb_launch.json -w "%{http_code}" -X POST http://127.0.0.1/api/v2/sportsbook-launch \
  -H "Host: vegasroyalspin119.com" -H "Content-Type: application/json" \
  -d '{"lang":"tr","channel":"desktop"}')
t1=$(date +%s%3N)
echo "LAUNCH_HTTP=$code ms=$((t1-t0))"
head -c 180 /tmp/sb_launch.json; echo
echo DEPLOY_SPORTBOOK_PERF_OK
'@
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($cmd))
& plink -batch -hostkey $hostKey -pw $pass $remote "echo $b64 | base64 -d | bash"
if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed' }
Write-Host 'DEPLOY_SPORTBOOK_PERF_OK'
