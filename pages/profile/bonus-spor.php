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

$user = ProfileApiHelper::profileByUsernameCached($username);
if ($user === []) {
    $user = ['id' => null, 'first_name' => '', 'surname' => ''];
}

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'bonus-spor';
$bonusSubTab = 'spor';
$unread_count = 0;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
$profile_content_title = 'SPOR BONUSU';
$profile_content_page_class = 'personal-details-page--loyalty-points personal-details-page--bonus-unified personal-details-page--bonus-spor-unified';
$profile_close_href_full = '/profile/details';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-open.php'; ?>
        <div class="bonus-spor-card bonus-unified-card">
            <div class="bonus-spor-content js-profile-active-bonus" data-bonus-kind="spor">
                <p class="profile-active-bonus-loading">Yükleniyor…</p>
            </div>
        </div>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
