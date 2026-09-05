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
    u.name AS isim,
    u.surname AS soyisim,
    u.email AS mail,
    u.phone AS telefon,
    ROUND(SUM(t.amount), 2) AS toplam_yatirim_try
FROM megapayz_transactions t
INNER JOIN users u ON u.id = t.user_id
WHERE t.type = 'deposit'
  AND t.status IN {$confirmed}
GROUP BY u.id, u.name, u.surname, u.email, u.phone
ORDER BY toplam_yatirim_try DESC
LIMIT {$limit}
";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$out = [
    'generated_at' => gmdate('c'),
    'count' => count($rows),
    'users' => array_map(static function (array $row): array {
        return [
            'isim' => (string) ($row['isim'] ?? ''),
            'soyisim' => (string) ($row['soyisim'] ?? ''),
            'mail' => (string) ($row['mail'] ?? ''),
            'telefon' => (string) ($row['telefon'] ?? ''),
            'toplam_yatirim_try' => (float) ($row['toplam_yatirim_try'] ?? 0),
        ];
    }, $rows),
];

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
