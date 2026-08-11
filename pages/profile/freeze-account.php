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
$profileActiveTab = 'freeze-account';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

include __DIR__ . '/../../views/partials/profile-page-frame-open.php';
?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        $profile_content_title = 'HESABI DONDUR';
        $profile_content_page_class = 'personal-details-page--freeze';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>
            <div class="profile-security-single profile-security-single--freeze" id="hesabi-dondur">
                <p class="u-i-common-content-textarea freeze-text">Hesabınızı dondurduğunuzda oturumunuz sonlanır ve mevcut giriş anahtarınız geçersiz olur. Tekrar siteyi kullanmak için hesap dondurmayı kaldırmanız (account unfreeze) gerekir. Onaylamak için hesap şifrenizi girin.</p>
                <form id="freezeForm" class="user-profile freeze-form" action="#" autocomplete="off">
                    <?php
                    $fc_id = 'freeze_password';
                    $fc_name = 'password';
                    $fc_title = 'Hesap şifresi *';
                    $fc_type = 'password';
                    $fc_autocomplete = 'current-password';
                    $fc_required = true;
                    $fc_attrs = 'placeholder=""';
                    include __DIR__ . '/../../views/partials/cm622-form-control-text.php';
                    ?>
                    <div class="u-i-p-c-footer-bc freeze-footer">
                        <button type="button" id="freezeSaveBtn" class="btn a-color" title="HESABI DONDUR"><span>HESABI DONDUR</span></button>
                    </div>
                </form>
            </div>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php include __DIR__ . '/../../views/partials/profile-page-frame-close.php'; ?>
