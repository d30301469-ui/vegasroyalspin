# Deploy risk-hold trap for withdrawals over RISK_WITHDRAW_HOLD_THRESHOLD (default 10000 TRY).
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'admin\app\Services\AdminAuth.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Services/AdminAuth.php' },
    @{ Local = Join-Path $root 'shared\services\MegaPayzService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/MegaPayzService.php' },
    @{ Local = Join-Path $root 'admin\app\Controllers\AdminMegaPayzController.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Controllers/AdminMegaPayzController.php' },
    @{ Local = Join-Path $root 'admin\app\Controllers\AdminRiskController.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Controllers/AdminRiskController.php' },
    @{ Local = Join-Path $root 'admin\app\Core\AdminRoutePermission.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Core/AdminRoutePermission.php' },
    @{ Local = Join-Path $root 'admin\index.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/index.php' },
    @{ Local = Join-Path $root 'admin\app\Views\tables\show.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Views/tables/show.php' },
    @{ Local = Join-Path $root 'admin\app\Views\compliance\risk-analysis.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Views/compliance/risk-analysis.php' },
    @{ Local = Join-Path $root 'admin\api\v2\includes\admin_routes.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/api/v2/includes/admin_routes.php' },
    @{ Local = Join-Path $root 'ENV.example'; Remote = '/www/wwwroot/vegasroyalspin.com/ENV.example' }
)
foreach ($f in $files) {
    if (-not (Test-Path $f.Local)) { throw "Missing $($f.Local)" }
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed $($f.Local)" }
}

$cmd = @'
ENVF=/www/wwwroot/vegasroyalspin.com/.env
AENV=/www/wwwroot/vegasroyalspin.com/admin/.env
ensure_env() {
  local file="$1" key="$2" val="$3"
  if grep -qE "^${key}=" "$file" 2>/dev/null; then
    sed -i -E "s|^${key}=.*|${key}=${val}|" "$file"
  else
    printf "\n%s=%s\n" "$key" "$val" >> "$file"
  fi
}
ensure_env "$ENVF" RISK_WITHDRAW_HOLD_THRESHOLD 10000
ensure_env "$AENV" RISK_WITHDRAW_HOLD_THRESHOLD 10000
ensure_env "$ENVF" RISK_WITHDRAW_SERIAL_HOURS 72
ensure_env "$AENV" RISK_WITHDRAW_SERIAL_HOURS 72
ensure_env "$ENVF" RISK_WITHDRAW_RELEASE_EMAIL "zonelix@proton.me"
ensure_env "$AENV" RISK_WITHDRAW_RELEASE_EMAIL "zonelix@proton.me"
grep -E "^RISK_WITHDRAW_" "$ENVF" "$AENV" | sed "s/=.*/=***/"

php -r '
$env=[];
foreach (file("/www/wwwroot/vegasroyalspin.com/admin/.env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
  $line=trim($line); if ($line===""||($line[0]??"")==="#"||!str_contains($line,"=")) continue;
  [$k,$v]=explode("=",$line,2); $env[trim($k)]=trim($v," \t\"\x27");
}
$pdo=new PDO("mysql:host=".($env["DB_HOST"]??"127.0.0.1").";dbname=".$env["DB_DATABASE"].";charset=utf8mb4",$env["DB_USERNAME"]??"",$env["DB_PASSWORD"]??"",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$thr=(float)($env["RISK_WITHDRAW_HOLD_THRESHOLD"] ?? 10000);
if ($thr <= 0) { $thr = 10000.0; }
$noteOver="Backfill risk tutma (tek cekim esik ustu)";
$noteSerial="Backfill risk tutma (ardisik/parcali cekim)";
$n1=$pdo->prepare("UPDATE megapayz_transactions SET status=\"risk_hold\", failure_message=CONCAT(IFNULL(failure_message,\"\"), IF(IFNULL(failure_message,\"\")=\"\",\"\",\"\n\"), :note), updated_at=NOW() WHERE type=\"withdraw\" AND status=\"pending\" AND amount > :thr");
$n1->execute(["note"=>$noteOver,"thr"=>number_format($thr,2,".","")]);
echo "BACKFILL_OVER=".$n1->rowCount()."\n";
$rows=$pdo->query("SELECT user_id, GROUP_CONCAT(id) AS ids, COUNT(*) AS cnt, SUM(amount) AS total FROM megapayz_transactions WHERE type=\"withdraw\" AND status=\"pending\" GROUP BY user_id HAVING cnt >= 2 OR total >= ".$thr)->fetchAll(PDO::FETCH_ASSOC);
$serial=0;
foreach ($rows as $row) {
  $st=$pdo->prepare("UPDATE megapayz_transactions SET status=\"risk_hold\", failure_message=CONCAT(IFNULL(failure_message,\"\"), IF(IFNULL(failure_message,\"\")=\"\",\"\",\"\n\"), :note), updated_at=NOW() WHERE type=\"withdraw\" AND status=\"pending\" AND user_id=:uid");
  $st->execute(["note"=>$noteSerial,"uid"=>(int)$row["user_id"]]);
  $serial += $st->rowCount();
}
echo "BACKFILL_SERIAL=".$serial."\n";
$c=$pdo->query("SELECT COUNT(*) FROM megapayz_transactions WHERE type=\"withdraw\" AND status=\"risk_hold\"")->fetchColumn();
echo "RISK_HOLD_COUNT=".$c."\n";
$adm=$pdo->query("SELECT id,email,role FROM admins WHERE LOWER(email)=LOWER(\"zonelix@proton.me\") LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "RELEASE_ADMIN=".json_encode($adm, JSON_UNESCAPED_UNICODE)."\n";
'
echo DEPLOY_RISK_WITHDRAW_HOLD_OK
'@
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($cmd))
& plink -batch -hostkey $hostKey -pw $pass $remote "echo $b64 | base64 -d | bash"
if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed' }
Write-Host 'DEPLOY_RISK_WITHDRAW_HOLD_OK'
