<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hata loglama
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', 1);
ini_set('error_log', (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/logs/error.log');

// Veritabanı bağlantısı (PDO)
include 'database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    die('<div class="alert alert-danger">Yetkisiz erişim! Lütfen giriş yapın.</div>');
}

$betId = $_GET['bet_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!$betId || !$userId) {
    die('<div class="alert alert-danger">Geçersiz istek!</div>');
}

// Sağlayıcı adları
$providers = [
    1 => 'TLT',
    2 => 'Nexsus',
    3 => 'TBS2',
    4 => 'LX'
];

// Durum adları
$statuses = [
    1 => 'Aktif',
    2 => 'Tamamlandı',
    3 => 'İptal Edildi',
    4 => 'Beklemede'
];

// Durum renkleri
$statusColors = [
    1 => 'success',
    2 => 'info',
    3 => 'danger',
    4 => 'warning'
];

$wrap = ProfileApiHelper::profileSection('/profile/spor-bet-detail', [
    'bet_id'  => $betId,
    'user_id' => $userId,
]);
$bet  = $wrap['bet'] ?? (is_array($wrap) && isset($wrap['id']) ? $wrap : null);

if (!$bet || !is_array($bet)) {
    echo '<div class="alert alert-danger">Bahis bulunamadı veya erişim izniniz yok.</div>';
    exit();
}

$sporDetails = [];
if (!empty($bet['spor_details']) && $bet['spor_details'] !== 'null') {
    $details = json_decode($bet['spor_details'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $sporDetails = $details;
    } else {
        $sporDetails = ['JSON Parse Hatası' => json_last_error_msg()];
    }
}

$statusClass = $statusColors[$bet['status']] ?? 'secondary';
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Bahis Bilgileri</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <th style="width: 40%;">Bahis ID:</th>
                <td><strong><?= $bet['id'] ?></strong></td>
            </tr>
            <tr>
                <th>Transaction ID:</th>
                <td><code><?= htmlspecialchars($bet['transaction_id']) ?></code></td>
            </tr>
            <tr>
                <th>Round ID:</th>
                <td><code><?= htmlspecialchars($bet['round_id']) ?></code></td>
            </tr>
            <tr>
                <th>Oyun Kodu:</th>
                <td><?= htmlspecialchars($bet['game_code']) ?></td>
            </tr>
            <tr>
                <th>Sağlayıcı:</th>
                <td><?= isset($providers[$bet['game_provider']]) ? $providers[$bet['game_provider']] : 'Bilinmiyor' ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <h6 class="border-bottom pb-2">Finansal Bilgiler</h6>
        <table class="table table-sm table-bordered">
            <tr>
                <th style="width: 40%;">Bahis Miktarı:</th>
                <td class="fw-bold text-danger"><?= number_format($bet['bet_amount'], 2) ?> ₺</td>
            </tr>
            <tr>
                <th>Kazanç Miktarı:</th>
                <td class="fw-bold text-success"><?= number_format($bet['get_amount'], 2) ?> ₺</td>
            </tr>
            <tr>
                <th>Durum:</th>
                <td><span class="badge bg-<?= $statusClass ?>"><?= isset($statuses[$bet['status']]) ? $statuses[$bet['status']] : 'Bilinmiyor' ?></span></td>
            </tr>
            <tr>
                <th>Oluşturulma:</th>
                <td><?= date('d.m.Y H:i:s', strtotime($bet['created_at'])) ?></td>
            </tr>
            <tr>
                <th>Son Bakiye:</th>
                <td><?= $bet['balance_after'] ? number_format($bet['balance_after'], 2) . ' ₺' : '-' ?></td>
            </tr>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-12">
        <h6 class="border-bottom pb-2">Kullanıcı Bakiye Durumu</h6>
        <div class="row">
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-light">
                    <div class="card-body text-center py-2">
                        <small class="text-muted">Ana Bakiye</small>
                        <div class="fw-bold text-primary"><?= number_format($bet['ana_bakiye'] ?? 0, 2) ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-light">
                    <div class="card-body text-center py-2">
                        <small class="text-muted">Spor Bonus</small>
                        <div class="fw-bold text-success"><?= number_format($bet['spor_bonus'] ?? 0, 2) ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-light">
                    <div class="card-body text-center py-2">
                        <small class="text-muted">Spor Freebet</small>
                        <div class="fw-bold text-warning"><?= number_format($bet['spor_freebet'] ?? 0, 2) ?> ₺</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-2">
                <div class="card bg-light">
                    <div class="card-body text-center py-2">
                        <small class="text-muted">Kupon Bakiye</small>
                        <div class="fw-bold text-info"><?= number_format($bet['kupon_bakiye'] ?? 0, 2) ?> ₺</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($sporDetails)): ?>
<div class="mt-3">
    <h6 class="border-bottom pb-2">Spor Bahis Detayları</h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered">
            <tbody>
                <?php foreach ($sporDetails as $key => $value): ?>
                    <?php if (is_array($value)): ?>
                        <tr>
                            <th colspan="2" class="bg-light"><?= htmlspecialchars($key) ?></th>
                        </tr>
                        <?php foreach ($value as $subKey => $subValue): ?>
                            <tr>
                                <td style="width: 30%; padding-left: 30px;"><?= htmlspecialchars($subKey) ?></td>
                                <td>
                                    <?php if (is_array($subValue)): ?>
                                        <pre class="mb-0" style="font-size: 11px;"><?= htmlspecialchars(json_encode($subValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
                                    <?php else: ?>
                                        <?= htmlspecialchars($subValue) ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <th style="width: 30%;"><?= htmlspecialchars($key) ?></th>
                            <td><?= htmlspecialchars($value) ?></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="mt-3">
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>Bu bahis için detay bilgisi bulunmuyor.
    </div>
</div>
<?php endif; ?>

<?php if (!empty($bet['spor_results'])): ?>
<div class="mt-3">
    <h6 class="border-bottom pb-2">Sonuç Analizi</h6>
    <div class="alert alert-info">
        <?= nl2br(htmlspecialchars($bet['spor_results'])) ?>
    </div>
</div>
<?php endif; ?>