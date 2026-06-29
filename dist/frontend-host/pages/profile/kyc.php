<?php
$appDebug = filter_var((string) getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $appDebug ? '1' : '0');
ini_set('display_startup_errors', $appDebug ? '1' : '0');
ini_set('log_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}
include __DIR__ . '/database.php';
require_once dirname(__DIR__, 2) . '/services/ProfileApiHelper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

$csrfKey = 'vegasroyalspin_csrf_token';
if (empty($_SESSION[$csrfKey]) || !is_string($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32));
}
$_SESSION['csrf_token'] = $_SESSION[$csrfKey];

$user_id = (int) $_SESSION['user_id'];
$msg     = '';
$success = '';

$user_info = ProfileApiHelper::profileByUsername((string) ($_SESSION['username'] ?? ''));
if ($user_info === []) {
    header('Location: /login');
    exit;
}

$kycRow = ProfileApiHelper::profileSection('/profile/kyc-status', ['user_id' => $user_id]);
$kyc    = $kycRow['kyc'] ?? $kycRow['request'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$kyc) {
    $csrf = (string) ($_POST['csrf_token'] ?? '');
    if ($csrf === '' || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        $msg = 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.';
    } elseif (!isset($_FILES['identity_file']) || $_FILES['identity_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'Kimlik/Pasaport dosyası zorunludur!';
    } else {
        $file    = $_FILES['identity_file'];
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed) || $file['size'] > 5 * 1024 * 1024) {
            $msg = 'Kimlik/Pasaport dosyası uygun değil! (jpg,jpeg,png,pdf max 5MB)';
        }
    }

    $addressPayload = null;
    if ($msg === '' && isset($_FILES['address_file']) && $_FILES['address_file']['error'] === UPLOAD_ERR_OK) {
        $file2 = $_FILES['address_file'];
        $ext2  = strtolower(pathinfo($file2['name'], PATHINFO_EXTENSION));
        if (in_array($ext2, $allowed) && $file2['size'] <= 5 * 1024 * 1024) {
            $addressPayload = [
                'base64'   => base64_encode((string) file_get_contents($file2['tmp_name'])),
                'filename' => $file2['name'],
                'ext'      => $ext2,
            ];
        }
    }

    if ($msg === '') {
        $identityPayload = [
            'base64'   => base64_encode((string) file_get_contents($_FILES['identity_file']['tmp_name'])),
            'filename' => $_FILES['identity_file']['name'],
            'ext'      => $ext,
        ];

        $apiRes = ProfileApiHelper::postProfile('/profile/kyc/submit', [
            'user_id'        => $user_id,
            'identity'       => $identityPayload,
            'address'        => $addressPayload,
        ]);

        if (is_array($apiRes) && !empty($apiRes['success'])) {
            $success = 'KYC başvurunuz gönderildi.';
            header('Refresh:0');
            exit;
        }
        $msg = $apiRes['message'] ?? 'KYC gönderilemedi veya API yanıt vermedi.';
    }
}

$initial = strtoupper(substr($user_info['username'] ?? 'U', 0, 1));
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<?php endif; ?>

<div class="centerWrap porfileWrap">
    <?php $profileActiveTab = 'kyc'; include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content kyc-main">
    <div class="container mt-4 kyc-profile-page">
        <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Ana Sayfa</a></li>
            <li class="breadcrumb-item active">Hesap Doğrulama</li>
        </ul>

        <div class="profileRow">
            <div class="row">
                <div class="col-12">
                    <h4 class="text-white mb-3"><i class="fa-solid fa-user-shield"></i> Hesap Doğrulama</h4>

                    <?php if ($msg): ?>
                        <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg) ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
                    <?php endif; ?>

                    <?php if (!$kyc): ?>
                        <form method="post" enctype="multipart/form-data" class="card p-4 shadow-lg bg-dark text-light border border-secondary">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                            <div class="mb-3">
                                <label class="form-label"><i class="fa-solid fa-id-card"></i> Kimlik / Pasaport <span class="text-danger">*</span></label>
                                <input type="file" name="identity_file" class="form-control bg-secondary text-light" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><i class="fa-solid fa-map-location-dot"></i> Adres Belgesi (opsiyonel)</label>
                                <input type="file" name="address_file" class="form-control bg-secondary text-light">
                            </div>
                            <button type="submit" class="btn btn-warning w-100"><i class="fa-solid fa-paper-plane"></i> Başvuru Gönder</button>
                        </form>
                    <?php else: ?>
                        <div class="card p-4 shadow-lg bg-dark text-light border border-secondary">
                            <h5><i class="fa-solid fa-clock-rotate-left"></i> Son Başvuru Detayları</h5>

                            <div class="stack-list mt-3">
                                <div class="stack-item">
                                    <span class="stack-title"><i class="fa-solid fa-user"></i> İsim</span>
                                    <span class="stack-value"><?= htmlspecialchars($user_info['first_name'] ?? '') ?></span>
                                </div>
                                <div class="stack-item">
                                    <span class="stack-title"><i class="fa-solid fa-user"></i> Soyisim</span>
                                    <span class="stack-value"><?= htmlspecialchars($user_info['surname'] ?? '') ?></span>
                                </div>
                                <div class="stack-item">
                                    <span class="stack-title"><i class="fa-solid fa-calendar-days"></i> Başvuru Tarihi</span>
                                    <span class="stack-value"><?= htmlspecialchars($kyc['submitted_at'] ?? '') ?></span>
                                </div>
                                <div class="stack-item">
                                    <span class="stack-title"><i class="fa-solid fa-user-check"></i> Onay Tarihi</span>
                                    <span class="stack-value">
                                        <?= !empty($kyc['approved_at'])
                                            ? htmlspecialchars($kyc['approved_at'])
                                            : '<span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half"></i> Admin onayı bekliyor</span>' ?>
                                    </span>
                                </div>
                                <div class="stack-item">
                                    <span class="stack-title"><i class="fa-solid fa-info-circle"></i> Durum</span>
                                    <span class="stack-value">
                                        <?php if (($kyc['status'] ?? '') === 'approved'): ?>
                                            <span class="badge bg-success"><i class="fa-solid fa-circle-check"></i> Onaylandı</span>
                                        <?php elseif (($kyc['status'] ?? '') === 'rejected'): ?>
                                            <span class="badge bg-danger"><i class="fa-solid fa-circle-xmark"></i> Reddedildi</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-hourglass-half"></i> Beklemede</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if (!empty($kyc['comment'])): ?>
                                <div class="stack-item">
                                    <span class="stack-title"><i class="fa-solid fa-comment"></i> Admin Yorumu</span>
                                    <span class="stack-value"><?= htmlspecialchars($kyc['comment']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </main>
</div>

<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
