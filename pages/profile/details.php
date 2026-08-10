<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /login');
    exit;
}

$csrfKey = 'app_csrf_token';
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

    <main id="profilePlayerMain" name="profilePlayerMain" class="my-profile-info-block profile-main-content">
        <?php
        $profile_content_title = 'KİŞİSEL DETAYLAR';
        $profile_close_href_full = '/';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>
                <form id="personalDetailsForm" class="personal-details-form user-profile" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php
                    $fc = static function (string $value): string {
                        return 'form-control-bc default' . ($value !== '' ? ' valid filled' : '');
                    };
                    ?>
                    <div class="userProfile-content" data-scroll-lock-scrollable>
                        <div class="userProfileWrapper-bc userProfileSection-0">
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="<?= htmlspecialchars($fc($displayUsername), ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="form-control-label-bc inputs" for="username">
                                        <input type="text" id="username" name="username" class="form-control-input-bc" value="<?= htmlspecialchars($displayUsername, ENT_QUOTES, 'UTF-8') ?>" required readonly autocomplete="username">
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Kullanıcı adı *</span>
                                    </label>
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="<?= htmlspecialchars($fc($firstName), ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="form-control-label-bc inputs" for="first_name">
                                        <input type="text" id="first_name" name="first_name" class="form-control-input-bc" value="<?= htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="given-name">
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Adı *</span>
                                    </label>
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="<?= htmlspecialchars($fc($surname), ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="form-control-label-bc inputs" for="surname">
                                        <input type="text" id="surname" name="surname" class="form-control-input-bc" value="<?= htmlspecialchars($surname, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="family-name">
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Soyadı *</span>
                                    </label>
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc profile-dob-holder">
                                <?php
                                $dobDisplay = '';
                                if ($dob !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dob, $dm)) {
                                    $dobDisplay = $dm[3] . '.' . $dm[2] . '.' . $dm[1];
                                }
                                ?>
                                <div class="form-control-bc default has-icon<?= $dob !== '' ? ' valid filled' : '' ?>" id="profileDobControl">
                                    <label class="form-control-label-bc inputs" for="dob_display">
                                        <input type="text" id="dob_display" class="form-control-input-bc" value="<?= htmlspecialchars($dobDisplay, ENT_QUOTES, 'UTF-8') ?>" readonly autocomplete="off" inputmode="none">
                                        <input type="hidden" id="dob" name="dob" value="<?= htmlspecialchars($dob, ENT_QUOTES, 'UTF-8') ?>" required>
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Doğum tarihi *</span>
                                    </label>
                                </div>
                                <i class="dropdownIcon-bc bc-i-datepicker" id="profileDobIcon" role="button" tabindex="0" aria-label="Takvim" aria-expanded="false" aria-controls="profile_datepicker_panel"></i>
                                <div class="profile-datepicker-panel" id="profile_datepicker_panel" role="dialog" aria-label="Doğum tarihi seçin" hidden>
                                    <div class="profile-datepicker-nav">
                                        <button type="button" class="profile-datepicker-prev" aria-label="Önceki ay">&lt;</button>
                                        <div class="profile-dp-select" data-dp-select="month">
                                            <button type="button" class="profile-dp-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                                                <span class="profile-dp-select-value" data-dp-month-value>Ocak</span>
                                                <i class="bc-i-small-arrow-down" aria-hidden="true"></i>
                                            </button>
                                            <div class="profile-dp-select-menu" role="listbox" hidden>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="0">Ocak</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="1">Şubat</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="2">Mart</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="3">Nisan</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="4">Mayıs</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="5">Haziran</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="6">Temmuz</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="7">Ağustos</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="8">Eylül</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="9">Ekim</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="10">Kasım</button>
                                                <button type="button" class="profile-dp-select-option" role="option" data-value="11">Aralık</button>
                                            </div>
                                        </div>
                                        <div class="profile-dp-select" data-dp-select="year">
                                            <button type="button" class="profile-dp-select-trigger" aria-haspopup="listbox" aria-expanded="false">
                                                <span class="profile-dp-select-value" data-dp-year-value></span>
                                                <i class="bc-i-small-arrow-down" aria-hidden="true"></i>
                                            </button>
                                            <div class="profile-dp-select-menu profile-dp-select-menu--year" role="listbox" data-dp-year-menu hidden></div>
                                        </div>
                                    </div>
                                    <div class="profile-datepicker-weekdays">
                                        <span>Pzt</span><span>Sal</span><span>Çar</span><span>Per</span><span>Cum</span><span>Cmt</span><span>Paz</span>
                                    </div>
                                    <div class="profile-datepicker-days"></div>
                                    <div class="profile-datepicker-actions">
                                        <button type="button" class="profile-datepicker-cancel">İPTAL</button>
                                        <button type="button" class="profile-datepicker-apply">UYGULA</button>
                                    </div>
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <?php
                                $ms_id = 'genderMs';
                                $ms_input_id = 'gender';
                                $ms_name = 'gender';
                                $ms_title = 'Cinsiyet *';
                                $ms_selected = $gender;
                                $ms_required = true;
                                $ms_options = [
                                    'Erkek' => 'Erkek',
                                    'Kadın' => 'Kadın',
                                    'Diğer' => 'Diğer',
                                ];
                                include __DIR__ . '/../../views/partials/cm622-multi-select.php';
                                ?>
                            </div>
                            <div class="u-i-p-control-item-holder-bc dropdownArrowParent-bc">
                                <div class="multi-select-bc cm622-country-select" id="countryMs" data-cm622-ms="1" data-cm622-country="1" tabindex="0">
                                    <div class="form-control-bc select has-icon country-code<?= $country !== '' ? ' valid filled' : '' ?>">
                                        <div class="form-control-label-bc inputs" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false">
                                            <div class="form-control-select-bc country-select-value">
                                                <i class="ftr-lang-bar-flag-bc flag-bc" data-country-flag aria-hidden="true"></i>
                                                <span class="country-select-text ellipsis" data-country-display><?= htmlspecialchars($country !== '' ? $country : 'Seçin', ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <i class="form-control-icon-bc bc-i-small-arrow-down" aria-hidden="true"></i>
                                            <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                            <span class="form-control-title-bc ellipsis">Ülke</span>
                                        </div>
                                        <div class="multi-select-label-bc cm622-country-panel" role="listbox" hidden>
                                            <div class="cm622-country-search">
                                                <input type="text" class="cm622-country-search-input" placeholder="Ülke ara..." autocomplete="off">
                                            </div>
                                            <div class="cm622-country-options" data-country-options></div>
                                        </div>
                                    </div>
                                    <input type="hidden" id="country" name="country" value="<?= htmlspecialchars($country, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="<?= htmlspecialchars($fc($city), ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="form-control-label-bc inputs" for="city">
                                        <input type="text" id="city" name="city" class="form-control-input-bc" value="<?= htmlspecialchars($city, ENT_QUOTES, 'UTF-8') ?>" autocomplete="address-level2">
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Şehir</span>
                                    </label>
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="<?= htmlspecialchars($fc($address), ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="form-control-label-bc inputs" for="address">
                                        <input type="text" id="address" name="address" class="form-control-input-bc" value="<?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?>" autocomplete="street-address">
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Adres</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="userProfileWrapper-bc userProfileSection-1">
                            <div class="u-i-p-control-item-holder-bc">
                                <hr class="passwordAboveSeparator">
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="entrance-f-item-bc">
                                    <div class="entrance-f-error-message-bc">Değişiklikleri kaydetmek için şifrenizi girin.</div>
                                </div>
                            </div>
                            <div class="u-i-p-control-item-holder-bc">
                                <div class="form-control-bc default has-icon">
                                    <label class="form-control-label-bc inputs" for="current_password">
                                        <input type="password" id="current_password" name="current_password" class="form-control-input-bc" value="" required autocomplete="current-password">
                                        <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                        <span class="form-control-title-bc ellipsis">Geçerli Şifre *</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="u-i-p-c-footer-bc">
                        <button type="submit" class="btn a-color right-aligned" id="saveDetailsBtn" title="DEĞİŞİKLİKLERİ KAYDET">
                            <span>DEĞİŞİKLİKLERİ KAYDET</span>
                        </button>
                    </div>
                </form>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php include __DIR__ . '/../../views/partials/profile-page-frame-close.php'; ?>
