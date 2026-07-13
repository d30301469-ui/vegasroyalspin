<?php
/**
 * Tek seferlik araç: mobile_menu_settings içindeki "Oyunlar" öğesini
 * label=BGaming, href=/bgaming, badge=YENİ olarak günceller.
 * Çalıştıktan sonra silin veya web'den erişimi kapatın.
 */
define('BASE_PATH', dirname(__DIR__));

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
        echo "Aktif kayıt bulunamadı.\n";
        exit(1);
    }

    $data = json_decode((string) $row['payload'], true);
    if (!is_array($data)) {
        echo "Payload JSON çözümlenemedi.\n";
        exit(1);
    }

    $changed = 0;

    // Sections içindeki öğeleri tara
    if (isset($data['sections']) && is_array($data['sections'])) {
        foreach ($data['sections'] as $si => $section) {
            if (!isset($section['items']) || !is_array($section['items'])) {
                continue;
            }
            foreach ($section['items'] as $ii => $item) {
                $lbl = strtolower(trim((string) ($item['label'] ?? '')));
                if ($lbl === 'oyunlar') {
                    $data['sections'][$si]['items'][$ii]['label']  = 'BGaming';
                    $data['sections'][$si]['items'][$ii]['href']   = '/bgaming';
                    $data['sections'][$si]['items'][$ii]['badge']  = 'YENİ';
                    $data['sections'][$si]['items'][$ii]['icon']   = 'bc-i-tv-games';
                    $data['sections'][$si]['items'][$ii]['target'] = '_self';
                    $changed++;
                }
            }
        }
    }

    // Tab bar içinde de kontrol et
    if (isset($data['tab_bar']) && is_array($data['tab_bar'])) {
        foreach ($data['tab_bar'] as $ti => $tab) {
            $lbl = strtolower(trim((string) ($tab['label'] ?? '')));
            if ($lbl === 'oyunlar') {
                $data['tab_bar'][$ti]['label'] = 'BGaming';
                $data['tab_bar'][$ti]['href']  = '/bgaming';
                $data['tab_bar'][$ti]['badge'] = 'YENİ';
                $data['tab_bar'][$ti]['icon']  = 'bc-i-tv-games';
                $changed++;
            }
        }
    }

    if ($changed === 0) {
        echo "\"Oyunlar\" öğesi bulunamadı. Mevcut etiketler:\n";
        // Mevcut etiketleri listele
        foreach ($data['sections'] ?? [] as $s) {
            foreach ($s['items'] ?? [] as $item) {
                echo "  - " . ($item['label'] ?? '') . "\n";
            }
        }
        foreach ($data['tab_bar'] ?? [] as $t) {
            echo "  [tab] - " . ($t['label'] ?? '') . "\n";
        }
        exit(0);
    }

    $newPayload = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $upd = $pdo->prepare(
        "UPDATE mobile_menu_settings SET payload = :payload, updated_at = NOW() WHERE id = :id"
    );
    $upd->execute(['payload' => $newPayload, 'id' => $row['id']]);

    echo "Güncellendi ($changed öğe). ID: {$row['id']}\n";
} catch (Throwable $e) {
    echo "HATA: " . $e->getMessage() . "\n";
    exit(1);
}
