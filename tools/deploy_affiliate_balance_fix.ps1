# Deploy affiliate balance reconcile fix + backfill stale balances.
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'shared\services\AffiliateService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php' },
    @{ Local = Join-Path $root 'shared\services\MegaPayzService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/MegaPayzService.php' },
    @{ Local = Join-Path $root 'admin\app\Controllers\AdminAffiliateController.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/app/Controllers/AdminAffiliateController.php' },
    @{ Local = Join-Path $root 'admin\services\AffiliateService.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/services/AffiliateService.php' },
    @{ Local = Join-Path $root 'admin\services\MegaPayzService.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/services/MegaPayzService.php' }
)

foreach ($f in $files) {
    if (-not (Test-Path $f.Local)) { throw "Missing $($f.Local)" }
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed for $($f.Local)" }
}

$reconcilePhp = @'
<?php
declare(strict_types=1);
$envFile = '/www/wwwroot/vegasroyalspin.com/admin/.env';
$e = parse_ini_file($envFile);
$pdo = new PDO(
    'mysql:host=' . $e['DB_HOST'] . ';dbname=' . $e['DB_DATABASE'] . ';charset=utf8mb4',
    $e['DB_USERNAME'],
    $e['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
require_once '/www/wwwroot/vegasroyalspin.com/shared/runtime.php';
require_once '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php';

$onlyCode = getenv('AFFILIATE_CODE') ?: '';
$sql = 'SELECT id, referral_code, balance FROM affiliates';
$params = [];
if ($onlyCode !== '') {
    $sql .= ' WHERE UPPER(referral_code) = UPPER(:code)';
    $params['code'] = $onlyCode;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$fixed = 0;
foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $before = (float) ($row['balance'] ?? 0);
    $after = AffiliateService::reconcileBalance($pdo, $id);
    if (abs($before - $after) >= 0.01) {
        echo ($row['referral_code'] ?? $id) . " {$before} -> {$after}\n";
        $fixed++;
    }
}
echo "AFFILIATE_BALANCE_FIX_OK fixed={$fixed}\n";
'@

$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($reconcilePhp))
$cmd = "echo $b64 | base64 -d > /tmp/reconcile_aff_balance.php && AFFILIATE_CODE=K7493 php /tmp/reconcile_aff_balance.php && rm -f /tmp/reconcile_aff_balance.php"
& plink -batch -hostkey $hostKey -pw $pass $remote $cmd
if ($LASTEXITCODE -ne 0) { throw 'Remote reconcile failed' }
Write-Host 'AFFILIATE_BALANCE_DEPLOY_OK'
