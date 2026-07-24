<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
require_once (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/services/ProfileApiHelper.php';

$isLoggedIn = function_exists('metropol_frontend_member_logged_in')
    ? metropol_frontend_member_logged_in()
    : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
$username   = $isLoggedIn ? $_SESSION['username'] : '';

if (!$isLoggedIn) {
    header('Location: /');
    exit();
}

$prof      = ProfileApiHelper::profileByUsernameCached($username);
$userId    = (int) ($prof['id'] ?? 0);
$email     = $prof['email'] ?? '';
$firstName = $prof['first_name'] ?? '';
$surname   = $prof['surname'] ?? '';

$initial = strtoupper(substr($username, 0, 1));

$user_info = ['username' => $username, 'id' => $userId, 'first_name' => $firstName, 'surname' => $surname];

$tdata        = ProfileApiHelper::profileSection('/profile/para-transactions', ['user_id' => $userId]);
$transactions = is_array($tdata['transactions'] ?? null) ? $tdata['transactions'] : [];

$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<?php endif; ?>

<div class="centerWrap porfileWrap">
    <?php $profileActiveTab = 'references'; include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>
    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content references-main">
    <div _ngcontent-mhw-c57="" class="container-fluid">
        <div _ngcontent-mhw-c57="" class="row">
          
       <div class="referrals-container">
    <h2 class="referrals-title">Referanslarım</h2>

    <div class="referral-card-custom">
        <h4>Referans Kodunuz</h4>
        <p class="muted-text">Bu kodu arkadaşlarınızla paylaşarak onları davet edebilirsiniz!</p>
        <div class="referral-code-display-custom">
            <span id="userReferralCode" class="referral-code-value">Yükleniyor...</span>
            <button class="copy-button-custom" id="copyReferralCode">
                <i class="fas fa-copy"></i> Kopyala </button>
        </div>
        <small class="share-link-text">
            Paylaşım linkiniz: <span id="shareLink" class="share-link-value"></span>
        </small>
    </div>

    <div class="table-card-custom">
        <div class="table-card-header-custom">
            Yönlendirdiğiniz Kullanıcılar
        </div>
        <div style="padding: 20px;">
            <table class="referral-table-custom">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Ad Soyad</th>
                        <th>Kullanıcı Adı</th>
                        <th>E-posta</th>
                        <th>Kayıt Tarihi</th>
                    </tr>
                </thead>
                <tbody id="referredUsersTableBody">
                    <tr><td colspan="5" class="text-center-custom">Yönlendirilmiş kullanıcılar yükleniyor...</td></tr>
                </tbody>
            </table>
            <p id="noReferralsMessage" class="message-center-custom" style="display: none;">Henüz yönlendirilmiş kullanıcınız bulunmamaktadır.</p>
        </div>
    </div>
</div>
        </div>
        </div>
    </div>
    </main>
</div>

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
