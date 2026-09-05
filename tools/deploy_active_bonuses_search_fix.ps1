# Fix Aktif Bonuslar search/filter: username match + status filter + client filter values.
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'admin\app\Repositories\AdminTableRepository.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Repositories/AdminTableRepository.php' },
    @{ Local = Join-Path $root 'admin\app\Config\admin.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Config/admin.php' },
    @{ Local = Join-Path $root 'admin\app\Views\tables\show.php'; Remote = '/www/wwwroot/vegasroyalspin.com/admin/app/Views/tables/show.php' }
)
foreach ($f in $files) {
    if (-not (Test-Path $f.Local)) { throw "Missing $($f.Local)" }
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed $($f.Local)" }
}

$cmd = @'
php -r '
$e=[];
foreach (file("/www/wwwroot/vegasroyalspin.com/admin/.env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
  $line=trim($line); if ($line===""||($line[0]??"")==="#"||!str_contains($line,"=")) continue;
  [$k,$v]=explode("=",$line,2); $e[trim($k)]=trim($v," \t\"\x27");
}
$pdo=new PDO(
  "mysql:host=".($e["DB_HOST"]??"127.0.0.1").";dbname=".$e["DB_DATABASE"].";charset=utf8mb4",
  $e["DB_USERNAME"]??"",
  $e["DB_PASSWORD"]??"",
  [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]
);
$q="SELECT COUNT(*) FROM user_active_bonuses WHERE user_id IN (
  SELECT u.id FROM users u
  WHERE u.username LIKE :p1 OR u.name LIKE :p2 OR u.surname LIKE :p3
     OR CONCAT(COALESCE(u.name,\"\"), \" \", COALESCE(u.surname,\"\")) LIKE :p4
)";
$st=$pdo->prepare($q);
$like="%a%";
$st->bindValue(":p1",$like); $st->bindValue(":p2",$like); $st->bindValue(":p3",$like); $st->bindValue(":p4",$like);
$st->execute();
echo "SEARCH_SMOKE=".$st->fetchColumn()."\n";
$st2=$pdo->query("SELECT status, COUNT(*) c FROM user_active_bonuses GROUP BY status ORDER BY c DESC");
echo "STATUSES=";
foreach ($st2 as $r) { echo $r["status"].":".$r["c"]." "; }
echo "\nDEPLOY_ACTIVE_BONUSES_SEARCH_OK\n";
'
'@
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($cmd))
& plink -batch -hostkey $hostKey -pw $pass $remote "echo $b64 | base64 -d | bash"
if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed' }
Write-Host 'DEPLOY_ACTIVE_BONUSES_SEARCH_OK'
