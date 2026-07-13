<?php
/**
 * Idempotent: mobile_menu_settings içindeki BGaming badge'ini YENİ yapar.
 * "Oyunlar" varsa BGaming'e çevirir, BGaming yoksa grid bölüme ekler.
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
        echo "Aktif kayıt bulunamadi.\n";
        exit(1);
    }

    $data = json_decode((string) $row['payload'], true);
    if (!is_array($data)) {
        echo "Payload JSON cozumlenemedi.\n";
        exit(1);
    }

    $bgamingItem = [
        'label'   => 'BGaming',
        'href'    => '/bgaming',
        'icon'    => 'bc-i-tv-games',
        'badge'   => 'YENİ',
        'target'  => '_self',
        'enabled' => true,
    ];

    $found   = false;
    $changed = 0;

    if (isset($data['sections']) && is_array($data['sections'])) {
        foreach ($data['sections'] as $si => $section) {
            foreach ($section['items'] ?? [] as $ii => $item) {
                $lbl = strtolower(trim((string) ($item['label'] ?? '')));
                if ($lbl === 'oyunlar' || $lbl === 'bgaming') {
                    $data['sections'][$si]['items'][$ii] = array_merge($item, $bgamingItem);
                    $found = true;
                    $changed++;
                }
            }
        }

        if (!$found) {
            foreach ($data['sections'] as $si => $section) {
                if (isset($section['items'])) {
                    array_unshift($data['sections'][$si]['items'], $bgamingItem);
                    $changed++;
                    break;
                }
            }
        }
    } else {
        $data['sections'] = [[
            'title'  => '',
            'layout' => 'grid',
            'items'  => [$bgamingItem],
        ]];
        $changed++;
    }

    $newPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $upd = $pdo->prepare(
        "UPDATE mobile_menu_settings SET payload = :payload, updated_at = NOW() WHERE id = :id"
    );
    $upd->execute(['payload' => $newPayload, 'id' => $row['id']]);

    echo ($found ? 'Guncellendi' : 'Eklendi') . " ($changed item). ID: {$row['id']}\n";
} catch (Throwable $e) {
    echo "HATA: " . $e->getMessage() . "\n";
    exit(1);
}
