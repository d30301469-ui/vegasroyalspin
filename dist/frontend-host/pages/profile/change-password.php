<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
include __DIR__ . '/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? null;

$userRow   = ProfileApiHelper::profileByUsername($username);
$firstName = $userRow['first_name'] ?? '';
$surname   = $userRow['surname'] ?? '';
$initial = strtoupper(substr($username, 0, 2));
$user_info = [
    'id' => $user_id,
    'username' => $username,
    'first_name' => $firstName,
    'surname' => $surname,
];
$profileActiveTab = 'change-password';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

$profile_include_toastr = true;
include __DIR__ . '/../../views/partials/profile-page-frame-open.php';
?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        $profile_content_title = 'ŞİFRE DEĞİŞTİR';
        $profile_content_page_class = 'personal-details-page--password';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>
            <div class="profile-security-single profile-security-single--password" id="sifre">
                <form id="changePasswordForm" class="password-change-form">
                    <div class="password-change-field">
                        <input type="password" class="password-change-input" id="oldPwd" required placeholder="Geçerli Şifre *" autocomplete="current-password">
                    </div>
                    <div class="password-change-field">
                        <input type="password" class="password-change-input" id="newPwd" required placeholder="Yeni Şifre *" autocomplete="new-password">
                    </div>
                    <div class="password-change-field">
                        <input type="password" class="password-change-input" id="confirmPass" required placeholder="Yeni şifreyi onayla *" autocomplete="new-password">
                    </div>
                    <div class="password-change-footer">
                        <button type="button" id="changePwdBtn" class="password-change-btn">ŞİFRE DEĞİŞTİR</button>
                    </div>
                </form>
            </div>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php include __DIR__ . '/../../views/partials/profile-page-frame-close.php'; ?>
