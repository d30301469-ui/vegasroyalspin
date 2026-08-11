<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
include __DIR__ . '/database.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? null;

$userRow   = ProfileApiHelper::profileByUsernameCached($username);
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
                <form id="changePasswordForm" class="user-profile password-change-form">
                    <?php
                    $fc_id = 'oldPwd';
                    $fc_name = 'old_password';
                    $fc_title = 'Geçerli Şifre *';
                    $fc_type = 'password';
                    $fc_autocomplete = 'current-password';
                    $fc_required = true;
                    $fc_attrs = 'placeholder=""';
                    include __DIR__ . '/../../views/partials/cm622-form-control-text.php';

                    $fc_id = 'newPwd';
                    $fc_name = 'new_password';
                    $fc_title = 'Yeni Şifre *';
                    $fc_type = 'password';
                    $fc_autocomplete = 'new-password';
                    $fc_required = true;
                    $fc_attrs = 'placeholder=""';
                    include __DIR__ . '/../../views/partials/cm622-form-control-text.php';

                    $fc_id = 'confirmPass';
                    $fc_name = 'confirm_password';
                    $fc_title = 'Yeni şifreyi onayla *';
                    $fc_type = 'password';
                    $fc_autocomplete = 'new-password';
                    $fc_required = true;
                    $fc_attrs = 'placeholder=""';
                    include __DIR__ . '/../../views/partials/cm622-form-control-text.php';
                    ?>
                    <div class="u-i-p-c-footer-bc password-change-footer">
                        <button type="button" id="changePwdBtn" class="btn a-color" title="ŞİFRE DEĞİŞTİR"><span>ŞİFRE DEĞİŞTİR</span></button>
                    </div>
                </form>
            </div>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php include __DIR__ . '/../../views/partials/profile-page-frame-close.php'; ?>
