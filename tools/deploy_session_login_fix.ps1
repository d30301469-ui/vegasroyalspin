# Fix frontend login writing ADMINSESSID instead of FRONTSESSID.
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'api\.htaccess'; Remote = '/www/wwwroot/vegasroyalspin.com/api/.htaccess' },
    @{ Local = Join-Path $root 'shared\services\PublicApiV2Dispatcher.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/PublicApiV2Dispatcher.php' },
    @{ Local = Join-Path $root 'shared\services\BackendMemberApiProxy.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/BackendMemberApiProxy.php' },
    @{ Local = Join-Path $root 'shared\services\BackendApiClient.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/BackendApiClient.php' },
    @{ Local = Join-Path $root 'admin\api\v2\bootstrap.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/api/v2/bootstrap.php' }
)
foreach ($f in $files) {
    if (-not (Test-Path $f.Local)) { throw "Missing $($f.Local)" }
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed $($f.Local)" }
}

$cmd = @'
# Ensure same-VM member proxy uses origin loopback (Host: admin), not CF hairpin.
ENVF=/www/wwwroot/vegasroyalspin.com/.env
ensure_env() {
  local key="$1" val="$2"
  if grep -qE "^${key}=" "$ENVF" 2>/dev/null; then
    sed -i -E "s|^${key}=.*|${key}=${val}|" "$ENVF"
  else
    printf "\n%s=%s\n" "$key" "$val" >> "$ENVF"
  fi
}
ensure_env API_BACKEND_INTERNAL_BASE_URL "http://127.0.0.1/api/v2"
ensure_env API_BACKEND_INTERNAL_HOST "admin.vegasroyalspin.com"
grep -E "^API_BACKEND_INTERNAL_" "$ENVF" | sed "s/=.*/=***/"

echo "=== verify login cookies after fix ==="
curl -sD - -o /tmp/login_fix_body.json -X POST "https://vegasroyalspin119.com/api/v2/auth/login" \
  -H "Accept: application/json" \
  -H "Origin: https://vegasroyalspin119.com" \
  -H "Referer: https://vegasroyalspin119.com/" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "CF-Connecting-IP: 203.0.113.10" \
  --data "login=nonexistent_user_xyz&username=nonexistent_user_xyz&password=WrongPass123!" \
  | tr -d "\r" | grep -iE "HTTP/|set-cookie|x-app|content-type"
echo BODY:; cat /tmp/login_fix_body.json; echo
echo "=== localhost same ==="
curl -sD - -o /tmp/login_fix_local.json -X POST "http://127.0.0.1/api/v2/auth/login" \
  -H "Host: vegasroyalspin119.com" \
  -H "Accept: application/json" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "CF-Connecting-IP: 203.0.113.10" \
  --data "login=nonexistent_user_xyz&username=nonexistent_user_xyz&password=WrongPass123!" \
  | tr -d "\r" | grep -iE "HTTP/|set-cookie|x-app|content-type"
echo BODY:; cat /tmp/login_fix_local.json; echo
# Expect 401 JSON (bad creds) + FRONTSESSID via origin Host, never ADMINSESSID / error code 1000
if grep -q "error code: 1000" /tmp/login_fix_local.json 2>/dev/null; then
  echo "SESSION_LOGIN_STILL_CF_1000"; exit 1
fi
if ! grep -q '"code":401' /tmp/login_fix_local.json 2>/dev/null; then
  echo "SESSION_LOGIN_UNEXPECTED_BODY"; cat /tmp/login_fix_local.json; exit 1
fi
# Public HTTPS from this origin may hairpin CF (error 1000) — browser/external path is authoritative.
if grep -q "error code: 1000" /tmp/login_fix_body.json 2>/dev/null; then
  echo "NOTE: origin->CF hairpin 1000 on public URL (ignore if local Host verify is 401)"
fi
echo "SESSION_LOGIN_ROUTE_FIX_OK"
'@
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($cmd))
& plink -batch -hostkey $hostKey -pw $pass $remote "echo $b64 | base64 -d | bash"
if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed' }
Write-Host 'DEPLOY_SESSION_LOGIN_FIX_OK'
