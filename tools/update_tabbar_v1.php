<?php
/**
 * Idempotent: mobile_menu_settings.tab_bar'ı SPOR / CASİNO / CANLI CASİNO / MENÜ yapar.
 * KUPON kaldırılır, CANLI CASİNO eklenir. Deploy'da otomatik çalışır.
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/env.php';
if (is_file(BASE_PATH . '/config/database.php')) {
    require_once BASE_PATH . '/config/database.php';
}
require_once BASE_PATH . '/api/bootstrap.php';

try {
    $pdo = ApiCmsRemote::pdo();

    $stmt = $pdo->query(
        "SELECT id, payload FROM mobile_menu_settings
         WHERE is_active = 1
         ORDER BY updated_at DESC, id DESC LIMIT 1"
    );
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

    if (!$row) {
        echo "Aktif kayit bulunamadi.\n";
        exit(1);
    }

    $data = json_decode((string) $row['payload'], true);
    if (!is_array($data)) {
        echo "Payload JSON cozumlenemedi.\n";
        exit(1);
    }

    $data['tab_bar'] = [
        ['type' => 'link', 'label' => 'SPOR',         'href' => '/sportbook',  'icon' => 'bc-i-prematch',   'badge' => '', 'enabled' => true, 'aria_label' => 'SPOR'],
        ['type' => 'link', 'label' => 'CASİNO',       'href' => '/slot',       'icon' => 'bc-i-slots',      'badge' => '', 'enabled' => true, 'aria_label' => 'CASİNO'],
        ['type' => 'link', 'label' => 'CANLI CASİNO', 'href' => '/livecasino', 'icon' => 'bc-i-livecasino', 'badge' => '', 'enabled' => true, 'aria_label' => 'CANLI CASİNO'],
        ['type' => 'menu', 'label' => 'MENÜ',         'href' => '',            'icon' => 'bc-i-burger',     'badge' => '', 'enabled' => true, 'id' => 'menu-toggle', 'aria_label' => 'MENÜ'],
    ];

    $newPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $upd = $pdo->prepare(
        "UPDATE mobile_menu_settings SET payload = :payload, updated_at = NOW() WHERE id = :id"
    );
    $upd->execute(['payload' => $newPayload, 'id' => $row['id']]);

    echo "tab_bar guncellendi. ID: {$row['id']}\n";
} catch (Throwable $e) {
    echo "HATA: " . $e->getMessage() . "\n";
    exit(1);
}
