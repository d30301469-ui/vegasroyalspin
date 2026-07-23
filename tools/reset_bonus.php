<?php
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=vegasroyalspin;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "=== Bonus Tablo Durumu ===\n\n";

foreach (['bonus_claim_requests', 'user_active_bonuses', 'promocode_requests'] as $t) {
    echo "$t: " . $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . " kayit\n";
}
echo "users bonus_balance>0: " . $pdo->query("SELECT COUNT(*) FROM users WHERE bonus_balance > 0")->fetchColumn() . " kullanici\n";

echo "\nTemizleniyor...\n";

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('TRUNCATE TABLE bonus_claim_requests');
echo "  OK bonus_claim_requests\n";
$pdo->exec('TRUNCATE TABLE user_active_bonuses');
echo "  OK user_active_bonuses\n";
$pdo->exec('TRUNCATE TABLE promocode_requests');
echo "  OK promocode_requests\n";
$n = $pdo->exec("UPDATE users SET bonus_balance = 0, active_wallet_mode = 'main' WHERE bonus_balance > 0 OR active_wallet_mode = 'bonus'");
echo "  OK users ($n kullanici)\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

echo "\nDONE - Tum bonus talepleri sifirlandi.\n";
