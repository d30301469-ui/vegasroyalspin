<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
require_once (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2)) . '/services/ProfileApiHelper.php';

$isLoggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$username   = $isLoggedIn ? $_SESSION['username'] : '';

if (!$isLoggedIn) {
    header('Location: /');
    exit();
}

$prof      = ProfileApiHelper::profileByUsername($username);
$email     = $prof['email'] ?? '';
$firstName = $prof['first_name'] ?? '';
$surname   = $prof['surname'] ?? '';

$initial = strtoupper(substr($username, 0, 1));

$user_info = [
    'username'   => $username,
    'id'         => $prof['id'] ?? ($_SESSION['user_id'] ?? null),
    'first_name' => $firstName,
    'surname'    => $surname,
];

$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<?php endif; ?>

<div class="centerWrap porfileWrap">
    <?php $profileActiveTab = null; include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content account-statement-main">
    <app-all-player-account-details _ngcontent-ouf-c46="" _nghost-ouf-c47="" class="ng-star-inserted">
        <div _ngcontent-ouf-c47="" class="container-fluid">
            <div _ngcontent-ouf-c47="" class="row"><app-g1-downline-navigation _ngcontent-ouf-c47="" _nghost-ouf-c48="">
                    <div _ngcontent-ouf-c48="" class="col-12 mt-theme"><button _ngcontent-ouf-c48="" class="btn backBtn"><i _ngcontent-ouf-c48="" class="fa-solid fa-angle-left"></i></button>
                        <ul _ngcontent-ouf-c48="" class="breadcrumb">
<li _ngcontent-ouf-c48="" class="breadcrumb-item">
    <a _ngcontent-ouf-c48="" href="/">Ana Sayfa</a>
</li>
                            <li _ngcontent-ouf-c48="" aria-current="page" class="breadcrumb-item">Profilim</li>
                        </ul>
                    </div>
                </app-g1-downline-navigation>
            </div>
        </div>
    </app-all-player-account-details>
    </main>
</div>

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
