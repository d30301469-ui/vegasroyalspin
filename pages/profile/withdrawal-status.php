<?php
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
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

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content withdrawal-status-main">
        <?php
        $profile_content_title = 'PARA ÇEKME DURUMU';
        $profile_content_page_class = 'personal-details-page--withdrawal-status';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

            <div class="withdrawal-status-table-bar">
                <span class="wsc th">Tarih Ve İD</span>
                <span class="wsc th">Ödeme Tarihi</span>
                <span class="wsc th">Sistem</span>
                <span class="wsc th">Kupon Kodu</span>
                <span class="wsc th">Tutar</span>
                <span class="wsc th">Durum</span>
                <span class="wsc th">İptal</span>
            </div>

            <div class="withdrawal-status-body">
                <?php if (empty($withdrawals)): ?>
                <p class="withdrawal-status-empty">Para Çekme Bilgisi Yok</p>
                <?php else: ?>
                <table class="withdrawal-status-table">
                    <tbody>
                        <?php foreach ($withdrawals as $w): ?>
                        <tr>
                            <td data-label="Tarih Ve İD"><?= date('d.m.Y H:i', strtotime($w['created_at'])) ?> #<?= (int)$w['id'] ?></td>
                            <td data-label="Ödeme Tarihi">—</td>
                            <td data-label="Sistem"><?= htmlspecialchars($w['provider'] ?? $w['method'] ?? '—') ?></td>
                            <td data-label="Kupon Kodu"><?= htmlspecialchars($w['trx'] ?? '—') ?></td>
                            <td data-label="Tutar"><?= number_format((float)$w['amount'], 2, ',', '.') ?> ₺</td>
                            <td data-label="Durum"><span class="wst-badge wst-<?= htmlspecialchars($w['status'] ?? '') ?>"><?= statusText($w['status'] ?? '') ?></span></td>
                            <td data-label="İptal">
                                <?php if (($w['status'] ?? '') === 'pending'): ?>
                                <button type="button" class="wst-cancel" data-id="<?= (int)$w['id'] ?>" aria-label="İptal">İptal</button>
                                <?php else: ?>
                                —
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
<?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
