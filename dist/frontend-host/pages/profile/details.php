<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
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

$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'] ?? null;

/**
 * HTML date input için YYYY-MM-DD normalize eder.
 */
$normalizeDateInput = static function (string $value): string {
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
        return $v;
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $v, $m) === 1) {
        return (string) ($m[0] ?? '');
    }
    $ts = strtotime($v);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d', $ts);
};

$normalizeGenderLabel = static function (string $value): string {
    $g = strtolower(trim($value));
    return match ($g) {
        'male', 'm', 'erkek' => 'Erkek',
        'female', 'f', 'kadın', 'kadin' => 'Kadın',
        'other', 'o', 'diğer', 'diger' => 'Diğer',
        default => trim($value),
    };
};

$normalizeCountryLabel = static function (string $value): string {
    $c = strtoupper(trim($value));
    if ($c === 'TR' || $c === 'TUR' || $c === 'TURKEY') {
        return 'Türkiye';
    }
    return trim($value);
};

$user = [];
$profileV2 = [];
if (!class_exists('MemberViewDataService', false)) {
    require_once BASE_PATH . '/services/MemberViewDataService.php';
}
$profileV2 = MemberViewDataService::profileForSession();

$firstName = trim((string) ($profileV2['name'] ?? $profileV2['first_name'] ?? ''));
$surname   = trim((string) ($profileV2['surname'] ?? $profileV2['last_name'] ?? ''));
$dob       = $normalizeDateInput((string) ($profileV2['dob'] ?? $profileV2['birth_date'] ?? ''));
$gender    = $normalizeGenderLabel((string) ($profileV2['gender'] ?? ''));
$phone     = trim((string) ($profileV2['phone'] ?? ''));
$country   = $normalizeCountryLabel((string) ($profileV2['country'] ?? ''));
$country   = $country !== '' ? $country : 'Türkiye';
$city      = trim((string) ($profileV2['city'] ?? ''));
$address   = trim((string) ($profileV2['address'] ?? ''));
$email     = trim((string) ($profileV2['email'] ?? ''));
$tcDisplay = trim((string) ($profileV2['identity_number'] ?? $profileV2['tc'] ?? ''));
$statusCode = strtolower(trim((string) ($profileV2['status'] ?? '')));
$displayUsername = trim((string) ($profileV2['username'] ?? $username));
if ($displayUsername === '') {
    $displayUsername = $username;
}

$statusLabel = match ($statusCode) {
    'active' => 'Aktif',
    'pending' => 'Onay bekliyor',
    'banned' => 'Yasaklı',
    default => '',
};
$statusClass = in_array($statusCode, ['active', 'pending', 'banned'], true) ? $statusCode : 'unknown';

$initial = strtoupper(substr($displayUsername, 0, 2));
$user_info = [
    'id' => $user_id,
    'username' => $displayUsername,
    'first_name' => $firstName,
    'surname' => $surname,
    'dob' => $dob,
    'gender' => $gender,
    'phone' => $phone,
];
$profileActiveTab = 'details';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

// AJAX ile güncelleme → POST /api/v2/profile/update, Bearer JWT, JSON zarfı
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax'])) {
    require_once SERVICE_PATH . '/PublicApiV2Dispatcher.php';
    PublicApiV2Dispatcher::dispatch('profile/update');
}

$profile_include_toastr = true;
include __DIR__ . '/../../views/partials/profile-page-frame-open.php';
?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        $profile_content_title = 'KİŞİSEL DETAYLAR';
        $profile_close_href_full = '/';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>
                <form id="personalDetailsForm" class="personal-details-form" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="personal-details-grid">
                        <div class="field-row full">
                            <label class="field-label" for="username">Kullanıcı adı <span class="required">*</span></label>
                            <input type="text" id="username" name="username" class="field-input" value="<?php echo htmlspecialchars($displayUsername); ?>" required readonly>
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="profile_email">E-posta</label>
                            <input type="email" id="profile_email" name="profile_email" class="field-input" value="<?php echo htmlspecialchars($email); ?>" readonly autocomplete="email">
                        </div>
                        <?php if ($statusLabel !== ''): ?>
                        <div class="field-row half">
                            <span class="field-label">Hesap durumu</span>
                            <span class="personal-details-status-badge personal-details-status-badge--<?php echo htmlspecialchars($statusClass); ?>" role="status"><?php echo htmlspecialchars($statusLabel); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="field-row half">
                            <label class="field-label" for="profile_phone">Telefon</label>
                            <input type="text" id="profile_phone" name="profile_phone" class="field-input" value="<?php echo htmlspecialchars($phone); ?>" readonly inputmode="tel" autocomplete="tel">
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="profile_tc">T.C. kimlik no</label>
                            <input type="text" id="profile_tc" name="profile_tc" class="field-input" value="<?php echo htmlspecialchars($tcDisplay); ?>" readonly autocomplete="off">
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="first_name">Adı <span class="required">*</span></label>
                            <input type="text" id="first_name" name="first_name" class="field-input" value="<?php echo htmlspecialchars($firstName); ?>" required>
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="surname">Soyadı <span class="required">*</span></label>
                            <input type="text" id="surname" name="surname" class="field-input" value="<?php echo htmlspecialchars($surname); ?>" required>
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="dob">Doğum tarihi <span class="required">*</span></label>
                            <div class="field-input-wrap">
                                <input type="date" id="dob" name="dob" class="field-input" value="<?php echo htmlspecialchars($dob); ?>" required>
                                <i class="fa-regular fa-calendar field-icon" aria-hidden="true"></i>
                            </div>
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="gender">Cinsiyet <span class="required">*</span></label>
                            <select id="gender" name="gender" class="field-input field-select" required>
                                <option value="">Seçin</option>
                                <option value="Erkek" <?php echo $gender === 'Erkek' ? 'selected' : ''; ?>>Erkek</option>
                                <option value="Kadın" <?php echo $gender === 'Kadın' ? 'selected' : ''; ?>>Kadın</option>
                                <option value="Diğer" <?php echo $gender === 'Diğer' ? 'selected' : ''; ?>>Diğer</option>
                            </select>
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="country">Ülke</label>
                            <div class="field-input-wrap field-input-wrap--flag">
                                <span class="flag-icon flag-icon-tr" aria-hidden="true"></span>
                                <input type="text" id="country" name="country" class="field-input" value="<?php echo htmlspecialchars($country); ?>" placeholder="Ülke">
                            </div>
                        </div>
                        <div class="field-row half">
                            <label class="field-label" for="city">Şehir</label>
                            <input type="text" id="city" name="city" class="field-input" value="<?php echo htmlspecialchars($city); ?>" placeholder="Şehir">
                        </div>
                        <div class="field-row full">
                            <label class="field-label" for="address">Adres</label>
                            <input type="text" id="address" name="address" class="field-input" value="<?php echo htmlspecialchars($address); ?>" placeholder="Adres">
                        </div>
                    </div>

                    <div class="personal-details-divider"></div>
                    <p class="personal-details-hint">Değişiklikleri kaydetmek için şifrenizi girin.</p>
                    <div class="field-row full">
                        <label class="field-label" for="current_password">Geçerli Şifre <span class="required">*</span></label>
                        <div class="field-input-wrap">
                            <input type="password" id="current_password" name="current_password" class="field-input" placeholder="••••••••" required>
                            <button type="button" class="field-toggle-pwd" aria-label="Şifreyi göster/gizle"><i class="fa-regular fa-eye-slash" aria-hidden="true"></i></button>
                        </div>
                    </div>

                    <div class="personal-details-footer">
                        <button type="submit" class="personal-details-submit" id="saveDetailsBtn">DEĞİŞİKLİKLERİ KAYDET</button>
                    </div>
                </form>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php include __DIR__ . '/../../views/partials/profile-page-frame-close.php'; ?>
