<?php
/**
 * Bonus talep tablolarını sıfırlama scripti.
 * Canlı yayın öncesi temizlik için.
 *
 * Kullanım: php tools/reset_bonus_claims.php
 */

$root = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = 'localhost';

// Bootstrap
require_once $root . '/config/env.php';
frontend_load_dotenv($root);
require_once $root . '/config/database.php';
$dbConfig = require $root . '/config/database.php';

if (empty($dbConfig['host']) || !empty($dbConfig['disabled'])) {
    // Admin DB config'i dene
    $adminDbConfig = require $root . '/admin/config/database.php';
    if (is_array($adminDbConfig)) {
        $dbConfig = $adminDbConfig;
    }
}

echo "Veritabanina baglaniliyor...\n";
$dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['port'], $dbConfig['database'], $dbConfig['charset']);
$pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Baglandi: {$dbConfig['database']}@{$dbConfig['host']}\n\n";

// Onay iste
echo "!!! DIKKAT: Bu islem asagidaki tablolari TAMAMEN BOSALTACAK:\n";
echo "   - bonus_claim_requests (tum bonus talepleri)\n";
echo "   - user_active_bonuses (tum aktif bonuslar)\n";
echo "   - promocode_requests (tum promosyon kodu talepleri)\n";
echo "   - users.bonus_balance (tum bonus bakiyeleri 0 yapilacak)\n\n";

// Sayimlari goster
foreach (['bonus_claim_requests', 'user_active_bonuses', 'promocode_requests'] as $table) {
    try {
        $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        echo "  $table: $count kayit\n";
    } catch (Throwable $e) {
        echo "  $table: TABLO YOK veya hata: " . $e->getMessage() . "\n";
    }
}
try {
    $bonusUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE bonus_balance > 0")->fetchColumn();
    echo "  users (bonus_balance > 0): $bonusUsers kullanici\n";
} catch (Throwable) {
    echo "  users: bonus_balance kolonu okunamadi\n";
}

echo "\nDevam etmek icin 'EVET' yazin: ";
$handle = fopen('php://stdin', 'r');
$line = trim(fgets($handle));
fclose($handle);

if (strtoupper($line) !== 'EVET') {
    echo "Iptal edildi.\n";
    exit(0);
}

echo "\nTablolar temizleniyor...\n";

$pdo->beginTransaction();
try {
    // Yabanci anahtar kisitlamasini gecici olarak devre disi birak
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $pdo->exec('TRUNCATE TABLE bonus_claim_requests');
    echo "  ✓ bonus_claim_requests temizlendi\n";

    $pdo->exec('TRUNCATE TABLE user_active_bonuses');
    echo "  ✓ user_active_bonuses temizlendi\n";

    $pdo->exec('TRUNCATE TABLE promocode_requests');
    echo "  ✓ promocode_requests temizlendi\n";

    $updated = $pdo->exec("UPDATE users SET bonus_balance = 0, active_wallet_mode = 'main' WHERE bonus_balance > 0 OR active_wallet_mode = 'bonus'");
    echo "  ✓ users.bonus_balance sifirlandi ($updated kullanici)\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->commit();
    echo "\n✅ Tum bonus talepleri basariyla sifirlandi.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n❌ HATA: " . $e->getMessage() . "\n";
    exit(1);
}
