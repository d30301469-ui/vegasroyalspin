# Cancel duplicate K7493 commission #75 + deploy force-guard fix.
$ErrorActionPreference = 'Stop'
$hostKey = 'ssh-ed25519 255 SHA256:O0rbDpqfceXg4sMY5qfS+sGqnPOsM9e4fxQVbS3oZVI'
$remote = 'root@13.140.159.112'
$pass = $env:VRS_DEPLOY_PASS
if (-not $pass) { throw 'Set VRS_DEPLOY_PASS' }

$root = Split-Path -Parent $PSScriptRoot
$files = @(
    @{ Local = Join-Path $root 'shared\services\AffiliateCommissionEngine.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateCommissionEngine.php' },
    @{ Local = Join-Path $root 'admin\services\AffiliateCommissionEngine.php'; Remote = '/www/wwwroot/admin.vegasroyalspin.com/services/AffiliateCommissionEngine.php' },
    @{ Local = Join-Path $root 'shared\services\AffiliateService.php'; Remote = '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php' }
)
foreach ($f in $files) {
    & pscp -batch -hostkey $hostKey -pw $pass $f.Local "${remote}:$($f.Remote)"
    if ($LASTEXITCODE -ne 0) { throw "pscp failed $($f.Local)" }
}

$fixPhp = @'
<?php
declare(strict_types=1);
$e = parse_ini_file('/www/wwwroot/vegasroyalspin.com/admin/.env');
$pdo = new PDO(
    'mysql:host=' . $e['DB_HOST'] . ';dbname=' . $e['DB_DATABASE'] . ';charset=utf8mb4',
    $e['DB_USERNAME'],
    $e['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
require_once '/www/wwwroot/vegasroyalspin.com/shared/runtime.php';
require_once '/www/wwwroot/vegasroyalspin.com/shared/services/AffiliateService.php';

$affiliateId = 5;
$commissionId = 75;
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT * FROM affiliate_commissions WHERE id = :id AND affiliate_id = :aid FOR UPDATE');
    $stmt->execute(['id' => $commissionId, 'aid' => $affiliateId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        throw new RuntimeException('commission not found');
    }
    if ((string) ($row['status'] ?? '') !== 'approved') {
        throw new RuntimeException('commission already final: ' . ($row['status'] ?? ''));
    }
    $amount = (float) ($row['amount'] ?? 0);
    $pdo->prepare("UPDATE affiliate_commissions SET status = 'cancelled', description = CONCAT(description, ' [duplicate force-recalc]') WHERE id = :id")
        ->execute(['id' => $commissionId]);
    if ($amount > 0) {
        $pdo->prepare(
            'UPDATE affiliates
             SET balance = GREATEST(0, balance - :amount),
                 total_earned = GREATEST(0, total_earned - :amount2)
             WHERE id = :id'
        )->execute([
            'amount' => number_format($amount, 2, '.', ''),
            'amount2' => number_format($amount, 2, '.', ''),
            'id' => $affiliateId,
        ]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $ex->getMessage() . "\n");
    exit(1);
}
$after = AffiliateService::reconcileBalance($pdo, $affiliateId);
$aff = $pdo->query('SELECT referral_code, balance, total_earned, total_paid FROM affiliates WHERE id = 5')->fetch();
echo 'K7493 balance=' . ($aff['balance'] ?? '') . ' earned=' . ($aff['total_earned'] ?? '') . ' paid=' . ($aff['total_paid'] ?? '') . "\n";
echo "AFFILIATE_DUPLICATE_FIX_OK\n";
'@

$b64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($fixPhp))
$cmd = "echo $b64 | base64 -d > /tmp/fix_k7493_dup.php && php /tmp/fix_k7493_dup.php && rm -f /tmp/fix_k7493_dup.php"
& plink -batch -hostkey $hostKey -pw $pass $remote $cmd
if ($LASTEXITCODE -ne 0) { throw 'Remote fix failed' }
Write-Host 'DEPLOY_DUPLICATE_FIX_OK'
