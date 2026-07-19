<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
include __DIR__ . '/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$username = (string) ($_SESSION['username'] ?? '');
$user = ProfileApiHelper::profileByUsernameCached($username);
if ($user === []) {
    $user = ['id' => null, 'first_name' => '', 'surname' => ''];
}

$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
$profileActiveTab = 'loyalty-points';
$initial = strtoupper(substr($username, 0, 2));
$user_info = [
    'username' => $username,
    'id' => $user['id'] ?? null,
    'first_name' => $user['first_name'] ?? '',
    'surname' => $user['surname'] ?? '',
];

$buildCurrentUrl = static function () use ($profile_modal): string {
    return '/profile/sadakat-puanlari' . ($profile_modal ? '?modal=1' : '');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pointsToRedeem = (int) ($_POST['redeem_points'] ?? 0);
    $flashType = 'error';
    $flashMessage = 'Geçerli bir puan miktarı giriniz.';

    if ($pointsToRedeem > 0) {
        try {
            $response = ProfileApiHelper::postProfile('/loyalty/redeem', ['points' => $pointsToRedeem]);
            $payload = is_array($response['data'] ?? null) ? $response['data'] : [];
            $isSuccess = !empty($response['success']) || !empty($payload['success']);
            $flashType = $isSuccess ? 'success' : 'error';
            $flashMessage = (string) (
                $payload['message']
                ?? $response['message']
                ?? ($isSuccess ? 'Puanlar başarıyla kullanıldı.' : 'Puan kullanımı başarısız.')
            );
        } catch (Throwable) {
            $flashType = 'error';
            $flashMessage = 'Puan kullanımı sırasında beklenmeyen bir hata oluştu.';
        }
    }

    $_SESSION['profile_loyalty_flash'] = [
        'type' => $flashType,
        'message' => $flashMessage,
    ];

    header('Location: ' . $buildCurrentUrl(), true, 303);
    exit;
}

$flash = $_SESSION['profile_loyalty_flash'] ?? null;
unset($_SESSION['profile_loyalty_flash']);

$loyalty = ProfileApiHelper::profileSection('/loyalty.php');
$account = is_array($loyalty['account'] ?? null) ? $loyalty['account'] : [];
$level = is_array($loyalty['level'] ?? null) ? $loyalty['level'] : [];
$nextLevel = is_array($loyalty['next_level'] ?? null) ? $loyalty['next_level'] : null;
$progress = is_array($loyalty['progress'] ?? null) ? $loyalty['progress'] : [];
$levels = is_array($loyalty['levels'] ?? null) ? $loyalty['levels'] : [];

$historyPayload = ProfileApiHelper::profileSection('/loyalty/history', ['limit' => 30]);
$historyItems = [];
if (is_array($historyPayload['items'] ?? null)) {
    $historyItems = $historyPayload['items'];
} elseif (is_array($historyPayload['data']['items'] ?? null)) {
    $historyItems = $historyPayload['data']['items'];
}

$points = (int) ($account['points'] ?? 0);
$redeemablePoints = (int) ($account['redeemable_points'] ?? 0);
$lifetimePoints = (int) ($account['lifetime_points'] ?? $points);
$canRedeem = $redeemablePoints >= 100;
$progressPercent = max(0, min(100, (int) ($progress['percent'] ?? 0)));
$nextLevelName = $nextLevel['name'] ?? null;
$pointsToNext = max(0, (int) ($progress['points_to_next'] ?? 0));

$profile_content_title = 'SADAKAT PUANLARI';
$profile_content_page_class = 'personal-details-page--loyalty-points';
$profile_close_href_full = '/profile/details';

$formatAction = static function (string $action): string {
    return match (strtolower($action)) {
        'earn' => 'Kazanım',
        'redeem' => 'Kullanım',
        'adjust' => 'Düzeltme',
        default => $action !== '' ? ucfirst($action) : '—',
    };
};

$formatDate = static function (string $value): string {
    if ($value === '') {
        return '—';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return '—';
    }
    return date('d.m.Y H:i', $ts);
};
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content loyalty-points-main">
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-open.php'; ?>

            <?php if (is_array($flash) && !empty($flash['message'])): ?>
            <div class="lp-alert lp-alert--<?= htmlspecialchars((string) ($flash['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>" role="status" aria-live="polite">
                <?= htmlspecialchars((string) $flash['message'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <div class="lp-stats-grid">
                <article class="lp-stat-card">
                    <span class="lp-stat-label">Mevcut Seviye</span>
                    <strong class="lp-stat-value"><?= htmlspecialchars((string) ($level['name'] ?? 'Bronze'), ENT_QUOTES, 'UTF-8') ?></strong>
                    <small class="lp-stat-sub">Kod: <?= htmlspecialchars((string) ($level['code'] ?? 'bronze'), ENT_QUOTES, 'UTF-8') ?></small>
                </article>
                <article class="lp-stat-card">
                    <span class="lp-stat-label">Toplam Puan</span>
                    <strong class="lp-stat-value"><?= number_format($points, 0, ',', '.') ?></strong>
                    <small class="lp-stat-sub">Ömür boyu: <?= number_format($lifetimePoints, 0, ',', '.') ?></small>
                </article>
                <article class="lp-stat-card">
                    <span class="lp-stat-label">Kullanılabilir Puan</span>
                    <strong class="lp-stat-value"><?= number_format($redeemablePoints, 0, ',', '.') ?></strong>
                    <small class="lp-stat-sub">100 puan = 1 TRY bonus</small>
                </article>
            </div>

            <div class="lp-progress-wrap" aria-label="Sadakat ilerlemesi">
                <div class="lp-progress-head">
                    <span>Seviye İlerlemesi</span>
                    <span>%<?= $progressPercent ?></span>
                </div>
                <div class="lp-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressPercent ?>">
                    <span class="lp-progress-fill" style="width: <?= $progressPercent ?>%"></span>
                </div>
                <p class="lp-progress-note">
                    <?php if ($nextLevelName !== null && $nextLevelName !== ''): ?>
                        Sonraki seviye: <strong><?= htmlspecialchars((string) $nextLevelName, ENT_QUOTES, 'UTF-8') ?></strong>
                        (kalan <?= number_format($pointsToNext, 0, ',', '.') ?> puan)
                    <?php else: ?>
                        En üst seviyedesiniz.
                    <?php endif; ?>
                </p>
            </div>

            <section class="lp-redeem-box" aria-labelledby="lpRedeemTitle">
                <h2 id="lpRedeemTitle">Puan Kullan</h2>
                <p>Sadakat puanlarınızı bonus bakiyesine dönüştürebilirsiniz.</p>
                <form method="post" action="<?= htmlspecialchars($buildCurrentUrl(), ENT_QUOTES, 'UTF-8') ?>" class="lp-redeem-form" data-redeemable-points="<?= (int) $redeemablePoints ?>">
                    <label for="lpRedeemPoints">Kullanılacak puan</label>
                    <div class="lp-redeem-row">
                        <input
                            id="lpRedeemPoints"
                            name="redeem_points"
                            type="number"
                            min="100"
                            step="100"
                            value="<?= $canRedeem ? 100 : 0 ?>"
                            required
                            inputmode="numeric"
                            <?= $canRedeem ? '' : 'disabled' ?>
                        >
                        <button type="submit" class="lp-redeem-btn" <?= $canRedeem ? '' : 'disabled' ?>>Puanı Kullan</button>
                    </div>
                </form>
                <?php if (!$canRedeem): ?>
                <p class="lp-empty" style="margin-top:8px;">Puan kullanımı için en az 100 kullanılabilir puan gerekir.</p>
                <?php endif; ?>
            </section>

            <section class="lp-history" aria-labelledby="lpHistoryTitle">
                <div class="lp-history-head">
                    <h2 id="lpHistoryTitle">Puan Geçmişi</h2>
                    <span>Son 30 kayıt</span>
                </div>
                <?php if ($historyItems === []): ?>
                <p class="lp-empty">Henüz sadakat puanı hareketi bulunmuyor.</p>
                <?php else: ?>
                <div class="lp-table-wrap">
                    <table class="lp-table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>İşlem</th>
                                <th>Puan</th>
                                <th>Açıklama</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historyItems as $row): ?>
                            <?php
                                $rowAction = strtolower((string) ($row['action'] ?? ''));
                                $rowPoints = (int) ($row['points'] ?? 0);
                                $rowClass = $rowPoints < 0 ? 'is-negative' : 'is-positive';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($formatDate((string) ($row['created_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($formatAction($rowAction), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="<?= htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8') ?>"><?= $rowPoints > 0 ? '+' : '' ?><?= number_format($rowPoints, 0, ',', '.') ?></td>
                                <td><?= htmlspecialchars((string) ($row['description'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </section>

            <?php if ($levels !== []): ?>
            <section class="lp-levels" aria-labelledby="lpLevelsTitle">
                <h2 id="lpLevelsTitle">Sadakat Seviyeleri</h2>
                <div class="lp-level-list">
                    <?php foreach ($levels as $row): ?>
                    <div class="lp-level-item <?= ((string) ($row['code'] ?? '') === (string) ($level['code'] ?? '')) ? 'is-current' : '' ?>">
                        <strong><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                        <span>Min. <?= number_format((int) ($row['min_points'] ?? 0), 0, ',', '.') ?> puan</span>
                        <span>%<?= number_format((float) ($row['cashback_rate'] ?? 0), 2, ',', '.') ?> cashback</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>

<?php if (!$profile_modal): ?>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
