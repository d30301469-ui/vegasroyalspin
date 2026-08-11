<?php
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    frontend_session_start();
}
include __DIR__ . '/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$username = $_SESSION['username'];

$user = ProfileApiHelper::profileByUsernameCached($username);
if ($user === []) {
    $user         = ['id' => null, 'first_name' => '', 'surname' => ''];
    $withdrawals = [];
} else {
    $w = ProfileApiHelper::profileSection('/profile/withdrawals', ['username' => $username]);
    $withdrawals = $w['withdrawals'] ?? $w['items'] ?? [];
    if (!is_array($withdrawals)) {
        $withdrawals = [];
    }
}

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'withdrawal-status';

function statusText($s) {
    $m = ['pending' => 'Beklemede', 'approved' => 'Onaylandı', 'confirmed' => 'Onaylandı', 'completed' => 'Tamamlandı', 'rejected' => 'Reddedildi', 'cancelled' => 'İptal Edildi', 'processing' => 'İşleniyor'];
    return $m[$s] ?? $s;
}

$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content withdrawLayout">
        <?php
        $profile_content_title = 'PARA ÇEKME DURUMU';
        $profile_content_page_class = 'personal-details-page--withdrawal-status';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

            <div class="u-i-page-content">
                <div class="u-i-page-table historyList-table-details">
                    <?php if (empty($withdrawals)): ?>
                    <p class="empty-b-text-v-bc" role="status">Para Çekme Bilgisi Yok</p>
                    <?php else: ?>
                    <div class="historyList-thead" role="row">
                        <div class="historyListEl-list-item">Tarih Ve İD</div>
                        <div class="historyListEl-list-item">Ödeme Tarihi</div>
                        <div class="historyListEl-list-item">Sistem</div>
                        <div class="historyListEl-list-item">Kupon Kodu</div>
                        <div class="historyListEl-list-item">Tutar</div>
                        <div class="historyListEl-list-item">Durum</div>
                        <div class="historyListEl-list-item">İptal</div>
                    </div>
                    <div class="historyList-tbody">
                        <?php foreach ($withdrawals as $w): ?>
                        <?php
                        $status = (string) ($w['status'] ?? '');
                        $statusClass = preg_replace('/[^a-z0-9_-]+/i', '', strtolower($status)) ?: 'unknown';
                        ?>
                        <div class="historyListEl" role="row">
                            <div class="historyListEl-list-item"><?= date('d.m.Y H:i', strtotime($w['created_at'])) ?> #<?= (int) $w['id'] ?></div>
                            <div class="historyListEl-list-item">—</div>
                            <div class="historyListEl-list-item"><?= htmlspecialchars($w['provider'] ?? $w['method'] ?? '—') ?></div>
                            <div class="historyListEl-list-item"><?= htmlspecialchars($w['trx'] ?? '—') ?></div>
                            <div class="historyListEl-list-item"><?= number_format((float) $w['amount'], 2, ',', '.') ?> ₺</div>
                            <div class="historyListEl-list-item">
                                <span class="wst-badge wst-<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(statusText($status), ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="historyListEl-list-item">
                                <?php if ($status === 'pending'): ?>
                                <button type="button" class="btn a-color wst-cancel" data-id="<?= (int) $w['id'] ?>" aria-label="İptal"><span>İptal</span></button>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
<?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
