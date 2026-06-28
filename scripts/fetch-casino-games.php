<?php
/**
 * Casino API'den tüm vendor ve oyun listesini çeker, log dosyasına yazar (CLI).
 * Kullanım: php scripts/fetch-casino-games.php
 */
$baseDir = dirname(__DIR__);
require_once $baseDir . '/config/casino_api.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('max_execution_time', 0);

echo "========================================\n";
echo "🎮 TÜM OYUNLAR API'DEN ÇEKİLİYOR\n";
echo "========================================\n\n";

$log_dir = $baseDir . '/logs';
if (!is_dir($log_dir)) {
    @mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/oyunlar_tam_' . date('Ymd_His') . '.log';
$log_handle = fopen($log_file, 'w');

$timestamp = date('Y-m-d H:i:s');
fwrite($log_handle, "========================================\n");
fwrite($log_handle, "🎮 OYUN LİSTESİ - " . $timestamp . "\n");
fwrite($log_handle, "========================================\n\n");

$vendor_data = [
    'method' => 'GetVendors',
    'token' => API_TOKEN,
    'agentCode' => AGENT_CODE,
];

$ch = curl_init(CASINO_API_URL);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($vendor_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    fclose($log_handle);
    die("❌ API bağlantı hatası! HTTP $http_code\n");
}

$result = json_decode($response, true);
$vendors = $result['response']['vendors'] ?? $result['vendors'] ?? [];
$vendor_count = count($vendors);
echo "✅ Toplam $vendor_count vendor bulundu.\n\n";

fwrite($log_handle, "VENDOR LİSTESİ ($vendor_count adet):\n");
fwrite($log_handle, str_repeat("-", 100) . "\n");

foreach ($vendors as $vendor) {
    $vendor_code = $vendor['vendorCode'];
    $vendor_name_raw = $vendor['vendorName'] ?? $vendor_code;
    $vendor_name = $vendor_name_raw;
    if (is_string($vendor_name_raw) && strpos($vendor_name_raw, '{') === 0) {
        $name_data = json_decode($vendor_name_raw, true);
        $vendor_name = $name_data['en'] ?? $name_data['tr'] ?? $vendor_code;
    }
    $game_type = $vendor['gameType'] ?? 1;
    fwrite($log_handle, sprintf(
        "Vendor Kodu: %-20s | Adı: %-30s | Tip: %s\n",
        $vendor_code,
        $vendor_name,
        $game_type
    ));
}

fwrite($log_handle, str_repeat("=", 100) . "\n\n");

$toplam_oyun = 0;
$vendor_oyun_sayilari = [];

foreach ($vendors as $index => $vendor) {
    $vendor_code = $vendor['vendorCode'];
    $vendor_name_raw = $vendor['vendorName'] ?? $vendor_code;
    $vendor_name = $vendor_name_raw;
    if (is_string($vendor_name_raw) && strpos($vendor_name_raw, '{') === 0) {
        $name_data = json_decode($vendor_name_raw, true);
        $vendor_name = $name_data['en'] ?? $name_data['tr'] ?? $vendor_code;
    }

    echo "📌 İşleniyor (" . ($index + 1) . "/$vendor_count): $vendor_name [$vendor_code]\n";

    fwrite($log_handle, "\n" . str_repeat("=", 100) . "\n");
    fwrite($log_handle, "VENDOR: $vendor_code - $vendor_name\n");
    fwrite($log_handle, str_repeat("=", 100) . "\n");

    $game_data = [
        'method' => 'GetVendorGames',
        'token' => API_TOKEN,
        'agentCode' => AGENT_CODE,
        'vendorCode' => $vendor_code,
    ];

    $ch = curl_init(CASINO_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($game_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        echo "   ❌ API hatası: HTTP $http_code\n";
        fwrite($log_handle, "HATA: API yanıt vermedi (HTTP $http_code)\n");
        continue;
    }

    $result = json_decode($response, true);
    $games = $result['response']['vendorGames'] ?? $result['vendorGames'] ?? [];

    if (empty($games)) {
        echo "   ⚠️ Bu vendor için oyun bulunamadı\n";
        fwrite($log_handle, "Bu vendor için oyun bulunamadı.\n");
        continue;
    }

    $oyun_sayisi = count($games);
    $vendor_oyun_sayilari[$vendor_code] = $oyun_sayisi;
    $toplam_oyun += $oyun_sayisi;
    echo "   ✅ $oyun_sayisi oyun bulundu\n";

    fwrite($log_handle, "Toplam Oyun: $oyun_sayisi\n");
    fwrite($log_handle, str_repeat("-", 100) . "\n");

    $vendor_index = $index + 1;
    foreach ($games as $game) {
        $game_code = $game['gameCode'] ?? $game['code'] ?? '';
        $game_name_raw = $game['gameName'] ?? $game['name'] ?? $game_code;
        $game_name = $game_name_raw;
        if (is_string($game_name_raw) && strpos($game_name_raw, '{') === 0) {
            $name_data = json_decode($game_name_raw, true);
            $game_name = $name_data['en'] ?? $name_data['tr'] ?? $game_code;
        }
        $image_url_raw = $game['imageUrl'] ?? $game['image'] ?? '';
        $image_url = '';
        if (is_string($image_url_raw) && strpos($image_url_raw, '{') === 0) {
            $url_data = json_decode($image_url_raw, true);
            $image_url = $url_data['en'] ?? $url_data['tr'] ?? '';
        } else {
            $image_url = $image_url_raw;
        }
        $image_url = str_replace('\/', '/', $image_url);
        $game_type = $game['gameType'] ?? $game['type'] ?? 1;
        fwrite($log_handle, sprintf(
            "\"%s\",\"%s\",%d,\"%s\",%d\n",
            $game_code,
            str_replace('"', '""', $game_name),
            $vendor_index,
            $image_url,
            $game_type
        ));
    }

    fwrite($log_handle, str_repeat("-", 100) . "\n");
    sleep(1);
}

fwrite($log_handle, "\n" . str_repeat("=", 100) . "\n");
fwrite($log_handle, "ÖZET RAPOR\n");
fwrite($log_handle, str_repeat("=", 100) . "\n");
fwrite($log_handle, "Toplam Vendor: $vendor_count\n");
fwrite($log_handle, "Toplam Oyun: $toplam_oyun\n");
fwrite($log_handle, str_repeat("-", 100) . "\n");
fwrite($log_handle, "Vendor bazında oyun sayıları:\n");
foreach ($vendor_oyun_sayilari as $code => $count) {
    fwrite($log_handle, sprintf("  %-30s: %d oyun\n", $code, $count));
}
fwrite($log_handle, str_repeat("=", 100) . "\n");
fwrite($log_handle, "İşlem Tamamlandı: " . date('Y-m-d H:i:s') . "\n");
fclose($log_handle);

echo "\n========================================\n";
echo "✅ İŞLEM TAMAMLANDI\n";
echo "========================================\n";
echo "📊 Toplam vendor: $vendor_count\n";
echo "📊 Toplam oyun: $toplam_oyun\n";
echo "📁 Log dosyası: $log_file\n";
echo "========================================\n";
