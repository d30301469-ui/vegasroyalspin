<?php

/**
 * users.bonus_code aktif bir ortak koduna eşit ama referred_by_affiliate_id boş olan
 * kayıtları geriye dönük bağlar. CLI: php tools/_backfill_affiliate_from_bonus.php [--apply]
 */
declare(strict_types=1);

$apply = in_array('--apply', $argv ?? [], true);

$roots = [
    dirname(__DIR__) . '/admin',
    dirname(__DIR__),
];

$pdo = null;
foreach ($roots as $root) {
    $bootstrap = $root . '/bootstrap.php';
    $dbFile = $root . '/config/database.php';
    if (is_readable($root . '/app/Support/AdminDatabase.php')) {
        require_once $root . '/bootstrap.php';
        if (class_exists('AdminDatabase', false)) {
            $pdo = AdminDatabase::pdo();
            break;
        }
    }
    if (is_readable($dbFile)) {
        $cfg = require $dbFile;
        if (is_array($cfg)) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $cfg['host'] ?? '127.0.0.1',
                $cfg['port'] ?? 3306,
                $cfg['database'] ?? $cfg['dbname'] ?? ''
            );
            $pdo = new PDO($dsn, (string) ($cfg['username'] ?? $cfg['user'] ?? ''), (string) ($cfg['password'] ?? ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            break;
        }
    }
}

if (!$pdo instanceof PDO) {
    // Env fallback (production PHP-FPM / cron)
    $host = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: '127.0.0.1';
    $name = getenv('DB_DATABASE') ?: getenv('MYSQL_DATABASE') ?: '';
    $user = getenv('DB_USERNAME') ?: getenv('MYSQL_USER') ?: '';
    $pass = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
    $port = getenv('DB_PORT') ?: '3306';
    if ($name === '' || $user === '') {
        fwrite(STDERR, "DB bağlantısı kurulamadı.\n");
        exit(1);
    }
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

$sql = <<<'SQL'
SELECT u.id, u.username, u.bonus_code, a.id AS affiliate_id, a.referral_code
FROM users u
INNER JOIN affiliates a
  ON UPPER(a.referral_code) = UPPER(TRIM(u.bonus_code))
 AND a.status = 'active'
WHERE u.bonus_code IS NOT NULL
  AND TRIM(u.bonus_code) <> ''
  AND COALESCE(u.referred_by_affiliate_id, 0) = 0
ORDER BY u.id DESC
SQL;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo 'Orphan eşleşme: ' . count($rows) . ($apply ? " (APPLY)\n" : " (dry-run, --apply ile yazılır)\n");

$upd = $pdo->prepare(
    'UPDATE users SET referred_by_affiliate_id = :aid WHERE id = :id AND COALESCE(referred_by_affiliate_id, 0) = 0'
);

$fixed = 0;
foreach ($rows as $row) {
    $line = sprintf(
        "#%d %s bonus=%s → affiliate #%d (%s)",
        (int) $row['id'],
        (string) $row['username'],
        (string) $row['bonus_code'],
        (int) $row['affiliate_id'],
        (string) $row['referral_code']
    );
    echo $line . "\n";
    if ($apply) {
        $upd->execute([
            'aid' => (int) $row['affiliate_id'],
            'id' => (int) $row['id'],
        ]);
        $fixed += $upd->rowCount();
    }
}

if ($apply) {
    echo "Güncellenen satır: {$fixed}\n";
}
