<?php
/**
 * MaltaBet Spor Entegrasyonu - Bakiye Sorunu Çözümlü
 */

// Hata yönetimi
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Log dosyası (proje kökü logs/)
$logFile = (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__)) . '/logs/spor_entegrasyon.log';

function pcsport_client_ip(): string
{
    if (function_exists('metropol_cloudflare_client_ip')) {
        $ip = metropol_cloudflare_client_ip();
        if ($ip !== '') {
            return $ip;
        }
    }
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        $value = trim((string) ($_SERVER[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        $candidate = trim(explode(',', $value)[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) {
            return $candidate;
        }
    }
    return '';
}

function writeLog($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = pcsport_client_ip() ?: 'Unknown IP';
    
    $logMessage = "[$timestamp] [$level] $message - IP: $ip\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

// Oturumu başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// HTML çıktısı için UTF-8 charset gönder
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

writeLog('Spor entegrasyonu başlatıldı');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../services/BackendApiClient.php';

if (empty($_SESSION['loggedin']) || empty($_SESSION['username'])) {
    http_response_code(401);
    exit('Oturum bulunamadı.');
}

$username = (string) $_SESSION['username'];
require_once dirname(__DIR__) . '/services/ProfileApiHelper.php';
$u        = ProfileApiHelper::profileByUsername($username);
if ($u === [] || !isset($u['id'])) {
    writeLog("Kullanıcı API'den bulunamadı: $username", 'WARNING');
    http_response_code(403);
    exit('Kullanıcı doğrulanamadı.');
}

$userId      = (int) $u['id'];
$rawBalance  = (float) ($u['ana_bakiye'] ?? 0);
$userBalance = max(0.01, $rawBalance);
if ($rawBalance <= 0) {
    writeLog("Kullanıcı bakiyesi 0 - Minimum bakiye atandı: $userBalance", 'WARNING');
} else {
    writeLog("Gerçek kullanıcı: $username, ID: $userId, Bakiye: $userBalance");
}

// API kimlik bilgileri
$API_KEY = trim((string) getenv('OKKO_SPORTS_API_KEY'));
$API_SECRET = trim((string) getenv('OKKO_SPORTS_API_SECRET'));
if ($API_KEY === '' || $API_SECRET === '') {
    http_response_code(503);
    exit('Spor servisi yapılandırması eksik.');
}

// Parametreler
$type = isset($_GET['type']) ? $_GET['type'] : 'match';
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'tr';

writeLog("İstek tipi: $type, Dil: $lang");

// API'ye gönderilecek veri - BAKİYE MUTLAKA 0'DAN BÜYÜK OLMALI
$postData = [
    'api_key'    => $API_KEY,
    'api_secret' => $API_SECRET,
    'user_id'    => (string)$userId,
    'username'   => $username,
    'balance'    => (string)max(0.01, $userBalance), // Minimum 0.01 garanti et
    'type'       => $type,
    'lang'       => $lang,
    'currency'   => 'TRY',
    'country'    => 'TR',
    'ip'         => pcsport_client_ip() ?: 'unknown',
    'timestamp'  => time()
];

writeLog("Spor API isteği hazırlanıyor: user_id={$postData['user_id']}, type={$postData['type']}, lang={$postData['lang']}", 'DEBUG');

// Spor API isteği
$sporApiUrl = trim((string) (getenv('OKKO_SPORTS_LAUNCH_URL') ?: (defined('OKKO_SPORTS_LAUNCH_URL') ? OKKO_SPORTS_LAUNCH_URL : 'https://my.okkogaming.com/spor-launch')));
$ch = curl_init($sporApiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($postData),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Hata kontrolü
if ($response === false) {
    writeLog("CURL Error: $curlError", 'ERROR');
    die('Spor sistemine bağlanılamıyor. Lütfen daha sonra tekrar deneyin.');
}

writeLog("API Response - HTTP Code: $httpCode");

if ($httpCode !== 200) {
    writeLog("HTTP Error: $httpCode - Response: $response", 'ERROR');
    
    // Özel hata mesajları
    if ($httpCode == 400) {
        die('Geçersiz istek. Lütfen bakiyenizi kontrol edin ve tekrar deneyin.');
    } else {
        die('Spor sistemi geçici olarak hizmet veremiyor. Lütfen daha sonra tekrar deneyin.');
    }
}

$responseData = json_decode($response, true);

if (!$responseData) {
    writeLog("JSON decode failed - Response: $response", 'ERROR');
    die('Spor sistemi yanıtı işlenemedi.');
}

if (!isset($responseData['success']) || $responseData['success'] !== true) {
    $errorMsg = isset($responseData['message']) ? $responseData['message'] : 'Bilinmeyen hata';
    writeLog("API Error: $errorMsg", 'ERROR');
    die("Spor sistemi hatası: $errorMsg");
}

if (!isset($responseData['iframe_url'])) {
    writeLog("iframe_url missing in response", 'ERROR');
    die('Spor sistemi yanıtı eksik.');
}

$iframeUrl = $responseData['iframe_url'];
writeLog("Başarılı - Iframe URL alındı");

// Iframe URL'sini kontrol et ve düzelt
if (strpos($iframeUrl, 'spor.okkogaming.com') === false) {
    writeLog("Geçersiz iframe URL: $iframeUrl", 'ERROR');
    die('Geçersiz spor URL alındı.');
}

// URL'deki gereksiz virgülü temizle
$iframeUrl = rtrim($iframeUrl, ',');
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaltaBet Spor Bahisleri</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #000;
            overflow: hidden;
            padding-bottom: constant(safe-area-inset-bottom);
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        .iframe-container {
            width: 100%;
            height: calc(100vh - 60px); /* Mobilde alttan boşluk için */
            position: relative;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
        }
        
        .balance-warning {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            background: #ff9800;
            color: #000;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-family: var(--font-sans);
            font-size: 14px;
            z-index: 1000;
            display: none;
        }

        /* Mobil için özel stiller */
        @media (max-width: 768px) {
            .iframe-container {
                height: calc(100vh - 80px); /* Mobilde daha fazla boşluk */
                padding-bottom: 20px; /* Ekstra padding */
            }
            
            body {
                padding-bottom: 20px; /* Safe area için ek padding */
            }
        }

        /* Çok küçük ekranlar için */
        @media (max-width: 480px) {
            .iframe-container {
                height: calc(100vh - 100px); /* Daha fazla boşluk */
                padding-bottom: 30px;
            }
        }

        /* Yatay mod için */
        @media (max-height: 500px) and (orientation: landscape) {
            .iframe-container {
                height: calc(100vh - 40px); /* Yatay modda daha az boşluk */
                padding-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <?php if ($userBalance <= 10 && !$isDemoMode): ?>
    <div class="balance-warning" id="balanceWarning">
        ⚠️ Bakiyeniz düşük. Spor bahislerini görüntüleyebilirsiniz ancak bahis yapmak için bakiyenizi yükseltin.
    </div>
    <script>
        document.getElementById('balanceWarning').style.display = 'block';
        setTimeout(() => {
            document.getElementById('balanceWarning').style.display = 'none';
        }, 5000);
    </script>
    <?php endif; ?>
    
    <div class="iframe-container">
        <iframe src="<?php echo htmlspecialchars($iframeUrl); ?>" 
                sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-presentation"
                allow="fullscreen"
                allowfullscreen>
        </iframe>
    </div>
    
    <script>
        // Iframe yükleme kontrolü
        document.querySelector('iframe').addEventListener('load', function() {
            console.log('Spor iframe başarıyla yüklendi');
            
            // Mobilde ekran boyutunu ayarla
            setTimeout(function() {
                adjustIframeHeight();
            }, 1000);
        });
        
        document.querySelector('iframe').addEventListener('error', function() {
            console.error('Spor iframe yüklenemedi');
            if (window.MaltabetToast) MaltabetToast.error('Spor sayfası yüklenirken bir hata oluştu. Lütfen sayfayı yenileyin.');
            else alert('Spor sayfası yüklenirken bir hata oluştu. Lütfen sayfayı yenileyin.');
        });

        // Ekran boyutu değiştiğinde iframe yüksekliğini ayarla
        window.addEventListener('resize', adjustIframeHeight);
        
        function adjustIframeHeight() {
            const iframeContainer = document.querySelector('.iframe-container');
            const viewportHeight = window.innerHeight;
            
            if (window.innerWidth <= 768) {
                // Mobilde alttan boşluk bırak
                iframeContainer.style.height = (viewportHeight - 80) + 'px';
            } else {
                // Masaüstünde normal yükseklik
                iframeContainer.style.height = (viewportHeight - 60) + 'px';
            }
        }

        // Sayfa yüklendiğinde boyutu ayarla
        window.addEventListener('load', adjustIframeHeight);
    </script>
</body>
</html>   