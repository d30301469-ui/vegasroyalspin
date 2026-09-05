<?php
declare(strict_types=1);

$limit = max(1, min(100, (int) ($argv[1] ?? 25)));

$envFile = '/www/wwwroot/vegasroyalspin.com/admin/.env';
$e = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || ($line[0] ?? '') === '#' || !str_contains($line, '=')) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $e[trim($k)] = trim($v, " \t\"'");
}

$pdo = new PDO(
    'mysql:host=' . ($e['DB_HOST'] ?? '127.0.0.1') . ';dbname=' . $e['DB_DATABASE'] . ';charset=utf8mb4',
    $e['DB_USERNAME'] ?? '',
    $e['DB_PASSWORD'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$confirmed = "('confirmed','approved','success','completed')";

$sql = "
SELECT
    t.user_id,
    u.username,
    u.email,
    u.phone,
    u.name,
    u.surname,
    NULLIF(TRIM(CONCAT(COALESCE(u.name,''), ' ', COALESCE(u.surname,''))), '') AS full_name,
    u.balance,
    u.bonus_balance,
    u.created_at AS registered_at,
    u.last_login_at,
    COUNT(*) AS deposit_count,
    ROUND(SUM(t.amount), 2) AS total_deposited_try,
    ROUND(MAX(t.amount), 2) AS max_single_deposit_try,
    ROUND(MIN(t.amount), 2) AS min_single_deposit_try,
    ROUND(AVG(t.amount), 2) AS avg_deposit_try,
    MIN(t.created_at) AS first_deposit_at,
    MAX(t.created_at) AS last_deposit_at,
    (
        SELECT ROUND(COALESCE(SUM(w.amount), 0), 2)
        FROM megapayz_transactions w
        WHERE w.user_id = t.user_id
          AND w.type = 'withdraw'
          AND w.status IN {$confirmed}
    ) AS total_withdrawn_try,
    (
        SELECT COUNT(*)
        FROM megapayz_transactions w
        WHERE w.user_id = t.user_id
          AND w.type = 'withdraw'
          AND w.status IN {$confirmed}
    ) AS withdraw_count
FROM megapayz_transactions t
LEFT JOIN users u ON u.id = t.user_id
WHERE t.type = 'deposit'
  AND t.status IN {$confirmed}
GROUP BY t.user_id, u.username, u.email, u.phone, u.name, u.surname,
         u.balance, u.bonus_balance, u.created_at, u.last_login_at
ORDER BY total_deposited_try DESC
LIMIT {$limit}
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$out = [
    'generated_at' => gmdate('c'),
    'currency' => 'TRY',
    'deposit_statuses' => ['confirmed', 'approved', 'success', 'completed'],
    'limit' => $limit,
    'count' => count($rows),
    'top_depositors' => array_map(static function (array $row): array {
        $totalDep = (float) ($row['total_deposited_try'] ?? 0);
        $totalWd = (float) ($row['total_withdrawn_try'] ?? 0);
        $row['net_deposited_minus_withdrawn_try'] = round($totalDep - $totalWd, 2);
        foreach (['balance', 'bonus_balance', 'total_deposited_try', 'max_single_deposit_try', 'min_single_deposit_try', 'avg_deposit_try', 'total_withdrawn_try'] as $k) {
            if (isset($row[$k])) {
                $row[$k] = is_numeric($row[$k]) ? (float) $row[$k] : $row[$k];
            }
        }
        foreach (['deposit_count', 'withdraw_count', 'user_id'] as $k) {
            if (isset($row[$k])) {
                $row[$k] = (int) $row[$k];
            }
        }
        return $row;
    }, $rows),
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
