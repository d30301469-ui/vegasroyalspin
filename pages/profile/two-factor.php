<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';
include __DIR__ . '/database.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'], $_POST['action']) && $_POST['ajax'] === '1' && $_POST['action'] === 'twofa_toggle') {
    header('Content-Type: application/json; charset=utf-8');
    $csrf = (string) ($_POST['csrf_token'] ?? $_POST['_token'] ?? '');
    if ($csrf === '' || !hash_equals((string) $_SESSION['csrf_token'], $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Güvenlik doğrulaması başarısız. Lütfen sayfayı yenileyip tekrar deneyin.']);
        exit;
    }
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $_SESSION['twofa_enabled'] = $enabled;
    echo json_encode(['success' => true, 'enabled' => $enabled]);
    exit;
}

$twofaEnabled = !empty($_SESSION['twofa_enabled']);

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
$profileActiveTab = 'two-factor';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';

include __DIR__ . '/../../views/partials/profile-page-frame-open.php';
?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        $profile_content_title = 'İKİ AŞAMALI KORUMA (2FA)';
        $profile_content_page_class = 'personal-details-page--twofa';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>
            <div class="profile-security-single profile-security-single--twofa" id="2fa">
                <p class="twofa-status" id="twofa-status"><?php echo $twofaEnabled ? 'İki faktörlü kimlik doğrulama etkin.' : 'İki faktörlü kimlik doğrulama kapatıldı'; ?></p>
                <div class="twofa-activate-row">
                    <div class="twofa-left-col">
                        <div class="twofa-icon-wrap">
                            <span class="twofa-icon" aria-hidden="true" title="Google Authenticator">
                                <svg class="twofa-icon-ga" viewBox="0 0 48 48" width="28" height="28" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false" role="img">
                                    <title>Google Authenticator</title>
                                    <path fill="#4285F4" d="M24 24V4A20 20 0 0 1 44 24H24z"/>
                                    <path fill="#EA4335" d="M24 24h20a20 20 0 0 1-20 20V24z"/>
                                    <path fill="#FBBC04" d="M24 24v20A20 20 0 0 1 4 24h20z"/>
                                    <path fill="#34A853" d="M24 24H4A20 20 0 0 1 24 4v20z"/>
                                    <circle cx="24" cy="24" r="7" fill="#fff"/>
                                </svg>
                            </span>
                        </div>
                        <span class="twofa-activate-label">İKİ FAKTÖRLÜ DOĞRULAMAYI ETKİNLEŞTİR</span>
                    </div>
                    <label class="twofa-toggle">
                        <input type="checkbox" class="twofa-toggle-input" id="twofaToggle" data-csrf-token="<?php echo htmlspecialchars((string) $_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $twofaEnabled ? 'checked' : ''; ?> aria-describedby="twofa-status">
                        <span class="twofa-toggle-slider"></span>
                    </label>
                </div>
            </div>
        <?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php include __DIR__ . '/../../views/partials/profile-page-frame-close.php'; ?>
