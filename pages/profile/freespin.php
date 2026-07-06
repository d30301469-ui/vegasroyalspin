<?php
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

$user = ProfileApiHelper::profileByUsername($username);
if ($user === []) {
    $user = ['id' => null, 'first_name' => '', 'surname' => ''];
}

$tab = isset($_GET['tab']) && $_GET['tab'] === 'aktif' ? 'aktif' : 'yeni';
$freespinRows = [];
if (!empty($user['id'])) {
    $jwt = trim((string) ($_SESSION['member_jwt'] ?? ''));
    if ($jwt !== '') {
        try {
            $response = BackendApiClient::requestWithMemberBearer(
                'GET',
                BackendApiClient::SVC_MAIN,
                'freespins.php',
                $jwt,
                ['tab' => $tab]
            );
            $data = BackendApiClient::unwrap($response);
            $items = $data['items'] ?? [];
            $freespinRows = is_array($items) ? $items : [];
        } catch (Throwable) {
            $freespinRows = [];
        }
    }
}

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'freespin';
$unread_count = 0;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
$tabYeniUrl = '/profile/freespin' . ($profile_modal ? '?modal=1' : '');
$tabAktifUrl = '/profile/freespin?tab=aktif' . ($profile_modal ? '&modal=1' : '');
$closeUrl = $profile_modal ? '#' : '/';
$freespinFallbackImages = [
    '/assets/games-img/sweet-bonanza-1000.svg',
    '/assets/games-img/40-super-hot.gif',
    '/assets/games-img/game-img3.jpg',
    '/assets/games-img/game-img4.svg',
    '/assets/games-img/game-img5.svg',
    '/assets/games-img/game-img6.jpeg',
    '/assets/games-img/game-img7.jpeg',
    '/assets/games-img/game-img9.svg',
];
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content freespin-main">
        <div class="freespin-card">
            <div class="freespin-header">
                <h1 class="freespin-title">CASİNO FREESPİNLERİ</h1>
                <a href="<?= htmlspecialchars($closeUrl, ENT_QUOTES, 'UTF-8') ?>" class="freespin-close" aria-label="Kapat"<?= $profile_modal ? ' data-profile-modal-close="1"' : '' ?>><i class="fa-solid fa-times"></i></a>
            </div>
            <div class="freespin-tabs">
                <a href="<?= htmlspecialchars($tabYeniUrl, ENT_QUOTES, 'UTF-8') ?>" class="freespin-tab <?= $tab === 'yeni' ? 'active' : '' ?>">YENİ FREE SPİNLER</a>
                <a href="<?= htmlspecialchars($tabAktifUrl, ENT_QUOTES, 'UTF-8') ?>" class="freespin-tab <?= $tab === 'aktif' ? 'active' : '' ?>">AKTİF</a>
            </div>
            <div class="freespin-content">
                <?php if ($freespinRows !== []): ?>
                    <div class="alert alert--warning" style="margin-bottom:12px">
                        Kullanılabilir freespin kampanyanız bulunuyor. Süre dolmadan kullanmanız önerilir.
                    </div>
                <?php endif; ?>
                <?php if ($freespinRows === []): ?>
                    <p class="freespin-empty">Seçilen tür için bonus yok</p>
                <?php else: ?>
                    <div class="freespin-list">
                        <?php foreach ($freespinRows as $row): ?>
                            <?php
                            $expiresAt = (int) ($row['expires_at'] ?? 0);
                            $beginsAt = (int) ($row['begins_at'] ?? 0);
                            $status = strtolower((string) ($row['status'] ?? 'active'));
                            $gameIdentifierRaw = trim((string) ($row['game_identifier'] ?? ''));
                            $gameTitle = trim((string) ($row['title'] ?? ''));
                            $gameImage = trim((string) ($row['image_url'] ?? ''));
                            if ($gameImage !== '' && str_starts_with($gameImage, 'assets/')) {
                                $gameImage = '/' . ltrim($gameImage, '/');
                            }
                            if ($gameImage === '' && $gameIdentifierRaw !== '') {
                                $fallbackIndex = abs(crc32($gameIdentifierRaw)) % count($freespinFallbackImages);
                                $gameImage = $freespinFallbackImages[$fallbackIndex] ?? '/assets/games-img/game-img3.jpg';
                            }
                            if ($gameImage === '') {
                                $gameImage = '/assets/games-img/game-img3.jpg';
                            }
                            $launchGameId = $gameIdentifierRaw;
                            if ($launchGameId !== '' && !str_starts_with($launchGameId, 'bgaming:')) {
                                $launchGameId = 'bgaming:' . $launchGameId;
                            }
                            $launchUrl = $launchGameId !== ''
                                ? '/play?game_id=' . rawurlencode($launchGameId) . '&mode=real&wallet=main'
                                : '';
                            $isPlayableStatus = in_array($status, ['active', 'new'], true);
                            $statusLabel = match ($status) {
                                'active' => 'Aktif',
                                'new' => 'Yeni',
                                'played' => 'Kullanıldı',
                                'expired' => 'Süresi Doldu',
                                'canceled' => 'İptal',
                                default => ucfirst($status),
                            };
                            ?>
                            <article class="freespin-item">
                                <div class="freespin-item-game">
                                    <img
                                        src="<?= htmlspecialchars($gameImage, ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars($gameTitle !== '' ? $gameTitle : ($gameIdentifierRaw !== '' ? $gameIdentifierRaw : 'Casino oyunu'), ENT_QUOTES, 'UTF-8') ?>"
                                        loading="lazy"
                                        onerror="this.onerror=null;this.src='/assets/games-img/game-img3.jpg';"
                                    >
                                </div>
                                <div class="freespin-item-col">
                                    <strong><?= htmlspecialchars((string) ($row['campaign_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string) ($row['vendor'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($gameTitle !== ''): ?>
                                        <small>Başlık: <?= htmlspecialchars($gameTitle, ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($row['game_identifier'])): ?>
                                        <small>Oyun: <?= htmlspecialchars((string) $row['game_identifier'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="freespin-item-col freespin-spins">
                                    <b><?= (int) ($row['freespins_per_player'] ?? 0) ?></b>
                                    <span>Free Spin</span>
                                </div>
                                <div class="freespin-item-col freespin-status-col">
                                    <span class="freespin-status freespin-status--<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($beginsAt > 0): ?><small>Başlangıç: <?= htmlspecialchars(date('d.m.Y H:i', $beginsAt), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    <?php if ($expiresAt > 0): ?><small>Bitiş: <?= htmlspecialchars(date('d.m.Y H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    <?php if ($launchUrl !== '' && $isPlayableStatus): ?>
                                        <a class="freespin-launch-btn" href="<?= htmlspecialchars($launchUrl, ENT_QUOTES, 'UTF-8') ?>">Oyuna Git</a>
                                    <?php elseif ($launchUrl === ''): ?>
                                        <small>Oyun bilgisi henüz tanımlı değil</small>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
