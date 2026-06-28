<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
                <a href="/" class="freespin-close" aria-label="Kapat"><i class="fa-solid fa-times"></i></a>
            </div>
            <div class="freespin-tabs">
                <a href="/profile/freespin" class="freespin-tab <?= $tab === 'yeni' ? 'active' : '' ?>">YENİ FREE SPİNLER</a>
                <a href="/profile/freespin?tab=aktif" class="freespin-tab <?= $tab === 'aktif' ? 'active' : '' ?>">AKTİF</a>
            </div>
            <div class="freespin-content">
                <?php if ($freespinRows === []): ?>
                    <p class="freespin-empty">Seçilen tür için bonus yok</p>
                <?php else: ?>
                    <div class="freespin-list">
                        <?php foreach ($freespinRows as $row): ?>
                            <?php
                            $expiresAt = (int) ($row['expires_at'] ?? 0);
                            $beginsAt = (int) ($row['begins_at'] ?? 0);
                            $status = (string) ($row['status'] ?? 'active');
                            ?>
                            <article class="freespin-item">
                                <div>
                                    <strong><?= htmlspecialchars((string) ($row['campaign_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars((string) ($row['vendor'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <div>
                                    <b><?= (int) ($row['freespins_per_player'] ?? 0) ?></b>
                                    <span>Free Spin</span>
                                </div>
                                <div>
                                    <span>Durum: <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php if ($beginsAt > 0): ?><small>Başlangıç: <?= htmlspecialchars(date('d.m.Y H:i', $beginsAt), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                    <?php if ($expiresAt > 0): ?><small>Bitiş: <?= htmlspecialchars(date('d.m.Y H:i', $expiresAt), ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
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
