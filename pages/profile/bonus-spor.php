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

$user_info = ['username' => $username, 'id' => $user['id'] ?? null, 'first_name' => $user['first_name'] ?? '', 'surname' => $user['surname'] ?? ''];
$initial = strtoupper(substr($username, 0, 2));
$profileActiveTab = 'bonus-spor';
$bonusSubTab = 'spor';
$unread_count = 0;
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content bonus-spor-main">
        <div class="bonus-spor-card">
            <div class="bonus-spor-header">
                <h1 class="bonus-spor-title">SPOR BONUSU</h1>
                <a href="/" class="bonus-spor-close" aria-label="Kapat"><i class="fa-solid fa-times"></i></a>
            </div>
            <div class="bonus-spor-content js-profile-active-bonus" data-bonus-kind="spor">
                <p class="profile-active-bonus-loading">Yükleniyor…</p>
            </div>
        </div>
    </main>
<?php if (!$profile_modal): ?>
</div>

<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
