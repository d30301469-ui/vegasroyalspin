#!/bin/bash
# Verify ensureTable no longer kills an open transaction
php -r '
require "/www/wwwroot/admin.vegasroyalspin.com/shared/runtime.php";
require "/www/wwwroot/admin.vegasroyalspin.com/shared/services/MemberJwtService.php";
// Load DB from admin .env via AdminDatabase if available
$envFile = "/www/wwwroot/admin.vegasroyalspin.com/.env";
if (!is_file($envFile)) { $envFile = "/www/wwwroot/vegasroyalspin.com/admin/.env"; }
if (!is_file($envFile)) { $envFile = "/www/wwwroot/vegasroyalspin.com/.env"; }
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
  if ($line[0]==="#") continue;
  if (!str_contains($line, "=")) continue;
  [$k,$v] = explode("=", $line, 2);
  $env[trim($k)] = trim($v, " \t\"'\''");
}
$dsn = sprintf("mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4", $env["DB_HOST"]??"127.0.0.1", $env["DB_PORT"]??"3306", $env["DB_DATABASE"]??"");
$pdo = new PDO($dsn, $env["DB_USERNAME"]??"", $env["DB_PASSWORD"]??"", [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$before = $pdo->inTransaction();
MemberJwtService::ensureTable($pdo);
$after = $pdo->inTransaction();
if ($pdo->inTransaction()) { $pdo->rollBack(); }
echo "before=$before after=$after env=".basename(dirname($envFile))."/".basename($envFile)."\n";
if ($before && $after) { echo "OK transaction preserved\n"; exit(0); }
echo "FAIL transaction lost\n"; exit(1);
'
