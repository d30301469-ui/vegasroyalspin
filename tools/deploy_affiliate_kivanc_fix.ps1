# Fix affiliate ref persistence + assign Kivanc (K7493) users.
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'shared\services\AffiliateService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php' },
    @{ Local = Join-Path $root 'shared\services\BackendMemberApiProxy.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/BackendMemberApiProxy.php' },
    @{ Local = Join-Path $root 'assets\js\auth-shared.js'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/js/auth-shared.js' },
    @{ Local = Join-Path $root 'assets\js\register.js'; Remote = '/www/wwwroot/vegasroyalspin.com/assets/js/register.js' },
    @{ Local = Join-Path $root 'tools\_assign_kivanc_users.php'; Remote = '/tmp/_assign_kivanc_users.php' }
)
foreach ($f in $files) {
    if (-not (Test-Path $f.Local)) { throw "Missing $($f.Local)" }
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed $($f.Local)" }
}

$cmd = @'
php -l /www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php
php /tmp/_assign_kivanc_users.php
php /tmp/_assign_kivanc_users.php --apply
php -r '
$e=[]; foreach(file("/www/wwwroot/vegasroyalspin.com/admin/.env", FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $l){ $l=trim($l); if($l===""||($l[0]??"")==="#"||!str_contains($l,"=")) continue; [$k,$v]=explode("=",$l,2); $e[trim($k)]=trim($v," \t\"\x27"); }
$pdo=new PDO("mysql:host=".($e["DB_HOST"]??"127.0.0.1").";dbname=".$e["DB_DATABASE"].";charset=utf8mb4",$e["DB_USERNAME"]??"",$e["DB_PASSWORD"]??"");
$in=["Karabahtim","Delirttiler07","Azem03"];
$st=$pdo->prepare("SELECT id,username,referred_by_affiliate_id,bonus_code FROM users WHERE username IN (?,?,?)");
$st->execute($in);
foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){ echo json_encode($r, JSON_UNESCAPED_UNICODE)."\n"; }
$st2=$pdo->prepare("SELECT ac.* FROM affiliate_commissions ac INNER JOIN users u ON u.id=ac.user_id WHERE u.username IN (?,?,?) ORDER BY ac.id");
$st2->execute($in);
foreach($st2->fetchAll(PDO::FETCH_ASSOC) as $r){ echo "COM ".json_encode($r, JSON_UNESCAPED_UNICODE)."\n"; }
'
echo DEPLOY_AFFILIATE_KIVANC_OK
'@
$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($cmd))
& plink -batch -hostkey $hostKey -pw $pass $remote "echo $b64 | base64 -d | bash"
if ($LASTEXITCODE -ne 0) { throw 'Remote verify failed' }
Write-Host 'DEPLOY_AFFILIATE_KIVANC_OK'
