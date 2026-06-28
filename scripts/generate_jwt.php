<?php
declare(strict_types=1);

// Usage: php scripts/generate_jwt.php --user-id=123
// Or:   php scripts/generate_jwt.php --username=player1

$opts = getopt('', ['user-id::', 'username::']);
$userId = isset($opts['user-id']) && (int)$opts['user-id'] > 0 ? (int)$opts['user-id'] : 0;
$username = isset($opts['username']) ? (string)$opts['username'] : '';

if ($userId === 0 && $username === '') {
    fwrite(STDERR, "Usage: php scripts/generate_jwt.php --user-id=123 OR --username=player1\n");
    exit(2);
}

$config = require __DIR__ . '/../config/database.php';
$host = $config['host'] ?? '127.0.0.1';
$port = $config['port'] ?? 3306;
$dbname = $config['database'] ?? 'metropol_db';
$user = $config['username'] ?? 'root';
$pass = $config['password'] ?? '';
$charset = $config['charset'] ?? 'utf8mb4';

$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Throwable $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    exit(3);
}

if ($userId > 0) {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
} else {
    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE username = :u LIMIT 1');
    $stmt->execute(['u' => $username]);
}
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($userRow)) {
    fwrite(STDERR, "User not found.\n");
    exit(4);
}

require_once __DIR__ . '/../services/MemberJwtService.php';
try {
    $jwt = MemberJwtService::issue($pdo, $userRow);
    echo $jwt . "\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to issue JWT: " . $e->getMessage() . "\n");
    exit(5);
}
