<?php
declare(strict_types=1);

$envFile = '/www/wwwroot/vegasroyalspin.com/admin/.env';
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim($v, " \t\"'");
}
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'] ?? '3306', $env['DB_DATABASE']),
    $env['DB_USERNAME'],
    $env['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "=== users columns ===\n";
foreach (['name','surname','city','country','address','dob','gender','phone','identity_number','password_changed_at','is_verified','banned','is_test'] as $col) {
    $st = $pdo->query("SHOW COLUMNS FROM users LIKE " . $pdo->quote($col));
    $r = $st->fetch();
    echo $col . ' => ' . ($r ? ($r['Type'] . ' null=' . $r['Null']) : 'MISSING') . "\n";
}

echo "=== sample user ===\n";
$u = $pdo->query('SELECT id,username,name,surname,city,country,address,dob,gender,phone,LEFT(password,20) pw FROM users ORDER BY id DESC LIMIT 3')->fetchAll();
foreach ($u as $row) echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";

echo "=== sql_mode ===\n";
echo $pdo->query('SELECT @@sql_mode')->fetchColumn() . "\n";

// Simulate update with Türkiye country
$testId = (int) ($u[0]['id'] ?? 0);
if ($testId > 0) {
    $before = $pdo->prepare('SELECT name,city,country FROM users WHERE id=?');
    $before->execute([$testId]);
    $b = $before->fetch();
    echo "before_update=" . json_encode($b, JSON_UNESCAPED_UNICODE) . "\n";
    try {
        $pdo->prepare('UPDATE users SET city = :city, country = :country WHERE id = :id')->execute([
            'city' => 'TestCityTmp',
            'country' => 'Türkiye',
            'id' => $testId,
        ]);
        $before->execute([$testId]);
        $a = $before->fetch();
        echo "after_test_update=" . json_encode($a, JSON_UNESCAPED_UNICODE) . "\n";
        // restore
        $pdo->prepare('UPDATE users SET city = :city, country = :country WHERE id = :id')->execute([
            'city' => $b['city'],
            'country' => $b['country'],
            'id' => $testId,
        ]);
        echo "restored\n";
    } catch (Throwable $e) {
        echo "UPDATE_ERROR=" . $e->getMessage() . "\n";
    }
}
