<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';

// Hata loglama
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', 1);
ini_set('error_log', (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs/error.log');

// Veritabanı bağlantısı (PDO)
include 'database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit();
}

$username = $_SESSION['username'];
$initial = strtoupper(substr($username, 0, 1));

$user = [];
$profileV1 = null;
$jwtSession = isset($_SESSION['member_jwt']) ? trim((string) $_SESSION['member_jwt']) : '';
if ($jwtSession !== '') {
    $envProfile = ApiProfileDetail::fetchEnvelope($jwtSession);
    if ($envProfile !== null && ApiEnvelope::isOk($envProfile)) {
        $rawProfile = $envProfile['data']['profile'] ?? null;
        if (is_array($rawProfile)) {
            $profileV1 = $rawProfile;
        }
    }
}
if ($profileV1 !== null) {
    $prefill = ApiProfileDetail::formPrefillFromProfile($profileV1);
    $sessId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    $idFromProfile = (int) ($profileV1['user_id'] ?? $profileV1['id'] ?? 0);
    $resolvedId = $sessId > 0 ? $sessId : $idFromProfile;
    $user = [
        'id' => $resolvedId,
        'first_name' => $prefill['first_name'],
        'surname' => $prefill['surname'],
    ];
}
if ($user === [] || empty($user['id'])) {
    $fromApi = ProfileApiHelper::profileByUsernameCached($username);
    if ($fromApi !== [] && !empty($fromApi['id'])) {
        $user = $fromApi;
    }
}
if ($user === [] || empty($user['id'])) {
    echo 'Kullanıcı bulunamadı.';
    exit();
}

$userId = (int) $user['id'];

// Sağlayıcı adlarını oyun adlarına çevirme fonksiyonu
function getGameNameFromProvider($provider) {
    $providerGameMap = [
        'pragmatic' => 'Pragmatic Play',
        'netent' => 'NetEnt',
        'microgaming' => 'Microgaming',
        'playtech' => 'Playtech',
        'evolution' => 'Evolution Gaming',
        'betsoft' => 'Betsoft',
        'quickspin' => 'Quickspin',
        'yggdrasil' => 'Yggdrasil',
        'redtiger' => 'Red Tiger',
        'playngo' => 'Play\'n GO',
        'bgaming' => 'BGaming',
        'hacksaw' => 'Hacksaw Gaming',
        'relax' => 'Relax Gaming',
        'thunderkick' => 'Thunderkick',
        'nolimit' => 'NoLimit City',
    ];
    
    $providerLower = strtolower($provider);
    return $providerGameMap[$providerLower] ?? $provider . ' Oyunu';
}

// Spor bahis sağlayıcılarını çevirme fonksiyonu
function getSporProviderName($providerCode) {
    $providerMap = [
        1 => 'TLT',
        2 => 'Nexsus',
        3 => 'TBS2',
        4 => 'LX',
        'sports-betby' => 'BetBy Sportsbook',
        'betby' => 'BetBy Sportsbook',
        'sports' => 'Sportsbook',
    ];
    if (is_numeric($providerCode)) {
        $key = (int) $providerCode;
        return $providerMap[$key] ?? 'Bilinmeyen Sağlayıcı';
    }

    $key = strtolower(trim((string) $providerCode));
    return $providerMap[$key] ?? ((string) $providerCode !== '' ? (string) $providerCode : 'Bilinmeyen Sağlayıcı');
}

// Spor bahis durumlarını çevirme fonksiyonu
function getSporStatusName($statusCode) {
    $statusMap = [
        1 => 'Aktif',
        2 => 'Tamamlandı',
        3 => 'İptal Edildi',
        4 => 'Beklemede'
    ];
    return $statusMap[$statusCode] ?? 'Bilinmeyen Durum';
}

// Spor bahis durum renklerini belirleme fonksiyonu
function getSporStatusColor($statusCode) {
    $colorMap = [
        1 => '#00c9a7',   // Aktif - Turkuaz
        2 => '#28a745',   // Tamamlandı - Yeşil
        3 => '#dc3545',   // İptal Edildi - Kırmızı
        4 => '#ffc107'    // Beklemede - Sarı
    ];
    return $colorMap[$statusCode] ?? '#6c757d'; // Gri - Varsayılan
}

/**
 * GET /api/v2/game_history.php satırını bahis geçmişi tablosu satırına çevirir.
 *
 * @param array<string, mixed> $t
 * @return array<string, mixed>
 */
function mapGamesTransactionRowToBetHistoryItem(array $t): array
{
    $betAmount = (float) ($t['betAmount'] ?? $t['bet_amount'] ?? 0);
    $winAmount = (float) ($t['winAmount'] ?? $t['win_amount'] ?? 0);
    $txnType = strtolower((string) ($t['txnType'] ?? $t['txn_type'] ?? 'bet'));

    switch ($txnType) {
        case 'win':
            $displayType = 'win';
            $amount = $winAmount;
            break;
        case 'cancel':
        case 'refund':
            $displayType = 'win';
            $amount = $winAmount > 0 ? $winAmount : $betAmount;
            break;
        case 'adjustment':
            $net = $winAmount - $betAmount;
            $displayType = $net >= 0 ? 'win' : 'bet';
            $amount = abs($net);
            break;
        case 'bet':
        default:
            $displayType = 'bet';
            $amount = $betAmount;
            break;
    }

    $gName = (string) ($t['gameName'] ?? $t['game_name'] ?? '');
    $pName = (string) ($t['providerName'] ?? $t['provider_name'] ?? '');
    $finalName = $gName !== ''
        ? ($pName !== '' ? $gName . ' (' . $pName . ')' : $gName)
        : ($pName !== '' ? $pName : 'Oyun');

    $sessionId = (string) ($t['sessionToken'] ?? $t['session_id'] ?? $t['providerTxnId'] ?? $t['provider_txn_id'] ?? $t['roundId'] ?? $t['round_id'] ?? '');
    $createdRaw = (string) ($t['createdAt'] ?? $t['created_at'] ?? '');
    $createdAt = $createdRaw !== '' ? $createdRaw : date('Y-m-d H:i:s');

    return [
        'id'               => 'GH_' . ($t['id'] ?? ''),
        'transaction_id'   => (string) ($t['providerTxnId'] ?? $t['provider_txn_id'] ?? $t['transaction_id'] ?? ''),
        'type'             => $displayType,
        'amount'           => $amount,
        'final_game_name'  => $finalName,
        'provider_name'    => $pName,
        'round_id'         => (string) ($t['roundId'] ?? $t['round_id'] ?? ''),
        'created_at'       => $createdAt,
        'bet_type'         => 'game_history',
        'gh_txn_type'      => $txnType,
        'gh_status'        => (string) ($t['status'] ?? ''),
        'game_history_data'=> [
            'id'              => $t['id'] ?? '',
            'session_id'      => $sessionId,
            'round_id'        => (string) ($t['roundId'] ?? ''),
            'game_name'       => $gName,
            'provider_name'   => $pName,
            'bet_amount'      => $betAmount,
            'win_amount'      => $winAmount,
            'status'          => (string) ($t['status'] ?? ''),
            'txn_type'        => $txnType,
            'source'          => (string) ($t['source'] ?? ''),
            'wallet'          => (string) ($t['wallet'] ?? ''),
            'balance_after'   => $t['balanceAfter'] ?? null,
            'game_id'         => (string) ($t['gameId'] ?? ''),
            'game_code'       => $t['gameCode'] ?? null,
            'provider_code'   => (string) ($t['providerCode'] ?? ''),
            'provider_txn_id' => (string) ($t['providerTxnId'] ?? ''),
            'created_at'      => $createdRaw,
        ],
    ];
}

// Sayfa filtresi (tek şablon, farklı view)
$filter = $_GET['filter'] ?? 'tumu';
$filterTitles = [
    'tumu' => 'TÜMÜ',
    'acik' => 'AÇIK BAHİSLER',
    'nakde' => 'NAKDE ÇEVRİLDİ',
    'kazanc' => 'KAZANÇ',
    'kayip' => 'KAYIP',
    'iade' => 'İADE EDİLDİ',
    'kazanan-iade' => 'KAZANAN İADE',
    'kayip-iade' => 'KAYIP-İADE',
];
$pageTitle = $filterTitles[$filter] ?? 'TÜMÜ';

// Filtreleme verileri
$game_name = $_GET['game_name'] ?? '';
$type = $_GET['type'] ?? '';
$min_amount = $_GET['min_amount'] ?? '';
$max_amount = $_GET['max_amount'] ?? '';
$spor_status = $_GET['spor_status'] ?? '';
$bet_id = $_GET['bet_id'] ?? '';
$sport_name = $_GET['sport_name'] ?? '';
$bet_type_filter = $_GET['bet_type'] ?? 'ALL'; // TÜMÜ, Tekli, Kombine, Sistem, Bahis Oluşturucu
$period = $_GET['period'] ?? '24'; // 24, 72, 168, 720, custom
$period_start = $_GET['period_start'] ?? '';
$period_end = $_GET['period_end'] ?? '';

$pack = ProfileApiHelper::profileSection('/profile/bet-history-pack', [
    'username'     => $username,
    'user_id'      => $userId,
    'filter'       => $filter,
    'game_name'    => $game_name,
    'type'         => $type,
    'min_amount'   => $min_amount,
    'max_amount'   => $max_amount,
    'spor_status'  => $spor_status,
    'bet_id'       => $bet_id,
    'sport_name'   => $sport_name,
    'bet_type'     => $bet_type_filter,
    'period'       => $period,
    'period_start' => $period_start,
    'period_end'   => $period_end,
]);

$sporTransactions   = is_array($pack['spor_transactions'] ?? null) ? $pack['spor_transactions'] : [];

// Spor bahislerini işle
$processedSporTransactions = [];
foreach ($sporTransactions as $transaction) {
    $txnType = strtolower((string) ($transaction['txn_type'] ?? $transaction['type'] ?? 'bet'));
    $isWin = in_array($txnType, ['win', 'cancel'], true);
    $defaultAmount = $isWin
        ? (float) ($transaction['get_amount'] ?? $transaction['amount'] ?? 0)
        : (float) ($transaction['bet_amount'] ?? $transaction['amount'] ?? 0);

    $transaction['bet_type'] = 'spor';
    $transaction['final_game_name'] = (string) ($transaction['sport_name'] ?? $transaction['game_name'] ?? $transaction['game_code'] ?? 'Spor Kuponu');
    $transaction['type'] = $isWin ? 'win' : 'bet';
    $transaction['amount'] = abs($defaultAmount);
    $transaction['provider_name'] = getSporProviderName($transaction['game_provider'] ?? $transaction['provider_name'] ?? '');
    $transaction['status_name'] = getSporStatusName($transaction['status']);
    $transaction['status_color'] = getSporStatusColor($transaction['status']);

    $processedSporTransactions[] = $transaction;
}

// Bu panel sadece spor bahis geçmişi gösterir.
$allTransactions = $processedSporTransactions;

// Tarihe göre sırala
usort($allTransactions, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// View filtresi (tek şablon farklı sorgu mantığı)
function matchViewFilter($transaction, $view) {
    if ($view === 'tumu') return true;
    $betType = $transaction['bet_type'] ?? 'casino';
    $ghTxn = $transaction['gh_txn_type'] ?? null;
    if ($betType === 'game_history' && is_string($ghTxn) && $ghTxn !== '') {
        $statusApi = strtolower((string) ($transaction['gh_status'] ?? ''));
        if ($view === 'acik') {
            return $statusApi === 'pending';
        }
        if ($view === 'nakde') {
            return in_array($ghTxn, ['win', 'cancel', 'refund'], true);
        }
        if ($view === 'kazanc') {
            return $ghTxn === 'win'
                || ($ghTxn === 'adjustment' && ($transaction['type'] ?? '') === 'win');
        }
        if ($view === 'kayip') {
            return $ghTxn === 'bet';
        }
        if ($view === 'iade' || $view === 'kazanan-iade' || $view === 'kayip-iade') {
            return in_array($ghTxn, ['cancel', 'refund'], true);
        }
        return true;
    }
    $status = (int)($transaction['status'] ?? 0);
    $txType = $transaction['type'] ?? '';
    $getAmount = (float)($transaction['get_amount'] ?? 0);
    if ($view === 'acik') {
        if ($betType === 'spor') return in_array($status, [1, 4], true); // Aktif, Beklemede
        return true;
    }
    if ($view === 'nakde') {
        if ($betType === 'spor') return $status === 2 && $getAmount > 0; // Tamamlandı + kazanç (nakde çevrilmiş sayılır)
        return $txType === 'win';
    }
    if ($view === 'kazanc') {
        if ($txType === 'win') return true;
        if ($betType === 'spor' && $getAmount > 0) return true;
        return false;
    }
    if ($view === 'kayip') {
        if ($betType === 'spor') return $status === 2 && $getAmount <= 0;
        return $txType === 'bet';
    }
    if ($view === 'iade') {
        if ($betType === 'spor') return $status === 3; // İptal Edildi
        return false;
    }
    if ($view === 'kazanan-iade') {
        if ($betType === 'spor') return $status === 3 && $getAmount > 0;
        return false;
    }
    if ($view === 'kayip-iade') {
        if ($betType === 'spor') return $status === 3 && $getAmount <= 0;
        return false;
    }
    return true;
}

// Periyot tarih aralığı (saat cinsinden: 24, 72, 168, 720)
$periodHours = ['24' => 24, '72' => 72, '168' => 168, '720' => 720];
$periodStartTs = null;
$periodEndTs = null;
if ($period === 'custom' && $period_start && $period_end) {
    $periodStartTs = strtotime($period_start . ' 00:00:00');
    $periodEndTs = strtotime($period_end . ' 23:59:59');
} elseif (isset($periodHours[$period])) {
    $periodEndTs = time();
    $periodStartTs = $periodEndTs - ($periodHours[$period] * 3600);
}

// Filtreleme uygula
$filteredTransactions = [];
foreach ($allTransactions as $transaction) {
    $include = true;

    if (!matchViewFilter($transaction, $filter)) $include = false;

    if ($include && $periodStartTs !== null && $periodEndTs !== null) {
        $ts = strtotime($transaction['created_at'] ?? '');
        if ($ts < $periodStartTs || $ts > $periodEndTs) $include = false;
    }

    if ($include && $bet_id !== '') {
        $id = (string)($transaction['id'] ?? '');
        $txId = (string)($transaction['transaction_id'] ?? '');
        if (stripos($id, $bet_id) === false && stripos($txId, $bet_id) === false) $include = false;
    }

    if ($include && $sport_name !== '') {
        $name = $transaction['final_game_name'] ?? $transaction['game_name'] ?? $transaction['game_code'] ?? '';
        if (stripos($name, $sport_name) === false) $include = false;
    }
    
    // Oyun adı filtresi
    if ($include && !empty($game_name)) {
        $currentGameName = $transaction['final_game_name'] ?? $transaction['game_name'] ?? $transaction['game'] ?? '-';
        $gameMatch = stripos($currentGameName, $game_name) !== false;
        if (!$gameMatch) {
            $include = false;
        }
    }
    
    // Tür filtresi (spor bahisleri için özel kontrol)
    if (!empty($type)) {
        if ($transaction['bet_type'] === 'spor') {
            // Spor bahisleri için type kontrolü
            if ($type !== 'ALL' && ($transaction['type'] ?? '') !== $type) {
                $include = false;
            }
        } else {
            // Casino işlemleri için normal kontrol
            if ($type !== 'ALL' && ($transaction['type'] ?? '') !== $type) {
                $include = false;
            }
        }
    }
    
    // Spor bahis durum filtresi
    if (!empty($spor_status) && isset($transaction['bet_type']) && $transaction['bet_type'] === 'spor') {
        if ($transaction['status'] != $spor_status) {
            $include = false;
        }
    }
    
    // Tutar filtresi
    if (is_numeric($min_amount) && $transaction['amount'] < $min_amount) {
        $include = false;
    }
    if (is_numeric($max_amount) && $transaction['amount'] > $max_amount) {
        $include = false;
    }
    
    if ($include) {
        $filteredTransactions[] = $transaction;
    }
}

// Sadece ilk 50'yi al
$transactions = array_slice($filteredTransactions, 0, 50);

// Tüm benzersiz oyun adlarını topla (filtre için)
$allGameNames = [];
foreach ($allTransactions as $transaction) {
    $gameName = $transaction['final_game_name'] ?? $transaction['game_name'] ?? '-';
    if ($gameName !== '-' && !in_array($gameName, $allGameNames)) {
        $allGameNames[] = $gameName;
    }
}
sort($allGameNames);

$user_id = $_SESSION['user_id'] ?? $userId;
$user_info = ['username' => $username, 'id' => $user_id, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$profileActiveTab = 'bet-history';
$betHistoryFilter = $filter;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php endif; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css">

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content bet-history-main">
        <?php
        $profile_content_title = $pageTitle;
        $profile_content_page_class = 'personal-details-page--bet-history';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

            <form method="get" action="" class="bet-history-filters" id="betHistoryFilterForm">
                <?php if ($profile_modal): ?>
                <input type="hidden" name="modal" value="1">
                <?php endif; ?>
                <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
                <div class="bhf-group">
                    <input type="text" name="bet_id" class="bhf-input" placeholder="BAHİS KİMLİĞİ" value="<?= htmlspecialchars($bet_id) ?>">
                </div>
                <div class="bhf-group">
                    <div class="bhf-input-wrap">
                        <input type="text" name="sport_name" class="bhf-input" placeholder="Spor Adı" value="<?= htmlspecialchars($sport_name) ?>">
                        <i class="fa-solid fa-magnifying-glass bhf-icon" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="bhf-group">
                    <label class="bhf-label">BAHİS TÜRÜ</label>
                    <select name="bet_type" class="bhf-select">
                        <option value="ALL" <?= $bet_type_filter === 'ALL' ? 'selected' : '' ?>>TÜMÜ</option>
                        <option value="tekli" <?= $bet_type_filter === 'tekli' ? 'selected' : '' ?>>Tekli</option>
                        <option value="kombine" <?= $bet_type_filter === 'kombine' ? 'selected' : '' ?>>Kombine</option>
                        <option value="sistem" <?= $bet_type_filter === 'sistem' ? 'selected' : '' ?>>Sistem</option>
                        <option value="builder" <?= $bet_type_filter === 'builder' ? 'selected' : '' ?>>Bahis Oluşturucu</option>
                    </select>
                </div>
                <div class="bhf-group">
                    <label class="bhf-label">PERİYOT</label>
                    <select name="period" class="bhf-select" id="bhPeriod">
                        <option value="24" <?= $period === '24' ? 'selected' : '' ?>>24 saat</option>
                        <option value="72" <?= $period === '72' ? 'selected' : '' ?>>72 saat</option>
                        <option value="168" <?= $period === '168' ? 'selected' : '' ?>>Bir hafta</option>
                        <option value="720" <?= $period === '720' ? 'selected' : '' ?>>30 Gün</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Özel</option>
                    </select>
                </div>
                <div class="bhf-group bhf-period-custom" id="bhPeriodCustomWrap" style="<?= $period !== 'custom' ? 'display:none' : '' ?>">
                    <label class="bhf-label">BAŞLANGIÇ – BİTİŞ</label>
                    <div class="bhf-date-row bhf-date-range-row">
                        <div class="bhf-date-input-wrap">
                            <i class="fa-regular fa-calendar bhf-date-icon" aria-hidden="true"></i>
                            <input type="date" id="bhPeriodStart" name="period_start" class="bhf-input bhf-input-date" value="<?= htmlspecialchars($period_start) ?>" aria-label="Başlangıç tarihi">
                        </div>
                        <span class="bhf-date-sep">–</span>
                        <div class="bhf-date-input-wrap">
                            <i class="fa-regular fa-calendar bhf-date-icon" aria-hidden="true"></i>
                            <input type="date" id="bhPeriodEnd" name="period_end" class="bhf-input bhf-input-date" value="<?= htmlspecialchars($period_end) ?>" aria-label="Bitiş tarihi">
                        </div>
                    </div>
                    <div class="bhf-date-presets" id="bhDatePresets" role="group" aria-label="Hızlı tarih aralıkları">
                        <button type="button" class="bhf-date-preset" data-range="today">Bugün</button>
                        <button type="button" class="bhf-date-preset" data-range="last7">Son 7 gün</button>
                        <button type="button" class="bhf-date-preset" data-range="last30">Son 30 gün</button>
                    </div>
                </div>
                <div class="bhf-group bhf-actions">
                    <button type="submit" class="bhf-btn-show">GÖSTER</button>
                </div>
            </form>

            <div class="bet-history-content">
                <?php if (empty($transactions)): ?>
                <p class="bet-history-empty">Gösterilecek bir şey yok</p>
                <?php else: ?>
                <div class="table-responsive">
                <table class="bet-history-table" id="betHistoryTable">    
                    <thead>    
                        <tr>    
                            <th>ID</th>    
                            <th>Tür</th>    
                            <th>Oyun Adı</th>    
                            <th>İşlem</th>    
                            <th>Miktar</th>    
                            <th>Detaylar</th>    
                            <th>Tarih</th>    
                        </tr>    
                    </thead>    
                    <tbody id="betHistoryTableBody">    
                <?php $counter = 1; foreach ($transactions as $transaction): ?>    
                <?php    
                    // Spor bahisi mi casino mu?
                    $betType = $transaction['bet_type'] ?? 'casino';
                    
                    if ($betType === 'spor') {
                        // Spor bahisleri için özel işlemler
                        $typeText = ($transaction['type'] === 'win') ? 'Spor Kazanç' : 'Spor Bahis';
                        $islemText = ($transaction['type'] === 'win') ? 'Kazanç' : 'Bahis';
                        $badgeColor = $transaction['status_color'] ?? '#00c9a7';
                        $gameName = $transaction['final_game_name'] ?? $transaction['game_code'] ?? '-';
                        $providerName = $transaction['provider_name'] ?? 'Bilinmiyor';
                        $statusName = $transaction['status_name'] ?? 'Bilinmiyor';
                        $infoText = $providerName . ' - ' . $statusName;
                        $roundId = $transaction['round_id'] ?? '-';
                        
                        // Spor bahis detay butonu için
                        $detailsButton = '';
                        if (!empty($transaction['id'])) {
                            $detailsButton = '<button class="btn btn-xs btn-info ms-2 spor-details-btn" data-bet-id="' . htmlspecialchars((string) $transaction['id'], ENT_QUOTES, 'UTF-8') . '">Detay</button>';
                        }
                        
                        $detailsHtml = $infoText . $detailsButton;
                        
                    } else if ($betType === 'game_history') {
                        $ghTxn = $transaction['gh_txn_type'] ?? 'bet';
                        switch ($ghTxn) {
                            case 'win':
                                $typeText = 'Oyun Kazanç';
                                $islemText = 'Kazanç';
                                $badgeColor = '#28a745';
                                break;
                            case 'cancel':
                            case 'refund':
                                $typeText = 'İptal / İade';
                                $islemText = 'İade';
                                $badgeColor = '#fd7e14';
                                break;
                            case 'adjustment':
                                $typeText = 'Düzeltme';
                                $islemText = 'Düzeltme';
                                $badgeColor = '#6f42c1';
                                break;
                            case 'bet':
                            default:
                                $typeText = 'Oyun Bahis';
                                $islemText = 'Bahis';
                                $badgeColor = '#dc3545';
                                break;
                        }
                        $gameName = $transaction['final_game_name'] ?? '-';
                        
                        // Oyun geçmişi detaylarını hazırla
                        $gameHistoryData = $transaction['game_history_data'] ?? [];
                        $detailsHtml = '';
                        
                        if (!empty($gameHistoryData)) {
                            $detailsHtml = '
                                <div class="game-history-details">
                                    <div class="small">
                                        <span class="badge bg-secondary me-1">ID: ' . substr($gameHistoryData['session_id'] ?? '-', 0, 8) . '...</span>
                                        <span class="badge bg-info me-1">Round: ' . ($gameHistoryData['round_id'] ?? '-') . '</span>
                                    </div>
                                    <div class="mt-1">
                                        <button class="btn btn-xs btn-outline-info game-history-details-btn" data-history-id="' . ($gameHistoryData['id'] ?? '') . '">
                                            <i class="fas fa-info-circle"></i> Detaylar
                                        </button>
                                    </div>
                                </div>
                            ';
                        }
                        
                    } else {
                        // Casino işlemleri
                        $typeText = ($transaction['type'] === 'win') ? 'Kazanç' : 'Bahis';
                        $islemText = ($transaction['type'] === 'win') ? 'Kazanç' : 'Bahis';
                        $badgeColor = $transaction['type'] === 'win' ? '#28a745' : '#dc3545';
                        $gameName = $transaction['final_game_name'] ?? $transaction['game_name'] ?? '-';
                        $providerName = $transaction['providers'] ?? '';
                        $detailsHtml = '<span class="badge bg-secondary">' . ($transaction['round_id'] ?? '-') . '</span>';
                    }
                    
                    $badgeStyle = "background-color:{$badgeColor};color:#fff;padding:3px 8px;border-radius:12px;font-size:0.75rem;";
                    
                    // Tutar rengi ve işareti
                    $amountColor = $transaction['type'] === 'win' ? 'text-success' : 'text-danger';
                    $amountSign = $transaction['type'] === 'win' ? '+' : '-';
                    $amountValue = $transaction['amount'] ?? 0;
                    
                    // Tarih formatı
                    $dateText = $transaction['created_at'] ? date('d.m.Y H:i:s', strtotime($transaction['created_at'])) : 'N/A';
                ?>    
                <tr class="transaction-row" data-bet-type="<?= $betType ?>" data-transaction-id="<?= $transaction['id'] ?>">    
                    <td data-label="ID"><?= $counter++ ?></td>    
                    <td data-label="Tür"><span style="<?= $badgeStyle ?>"><?= $typeText ?></span></td>    
                    <td data-label="Oyun Adı"><?= htmlspecialchars($gameName) ?></td>    
                    <td data-label="İşlem"><?= htmlspecialchars($islemText) ?></td>    
                    <td data-label="Miktar" class="<?= $amountColor ?> fw-bold"><?= $amountSign ?><?= number_format((float)$amountValue, 2) ?> ₺</td>    
                    <td data-label="Detaylar">
                        <?= $detailsHtml ?>
                    </td>    
                    <td data-label="Tarih"><?= $dateText ?></td>    
                </tr>    
                <?php endforeach; ?>    
                    </tbody>    
                </table>
                </div>
                <?php endif; ?>
            </div>
<?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>
<?php endif; ?>

<!-- Spor Bahis Detayları Modalı -->
<div class="modal fade" id="sporDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content profile-detail-modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Spor Bahis Detayları</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="sporDetailsContent">
                <!-- Detaylar buraya yüklenecek -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Oyun Geçmişi Detayları Modalı -->
<div class="modal fade" id="gameHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content profile-detail-modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Oyun Geçmişi Detayları</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="gameHistoryContent">
                <!-- Oyun geçmişi detayları buraya yüklenecek -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>