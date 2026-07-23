<?php
/**
 * Oyun oynatma: ince üst çubuk + iframe; POST /api/v2/game-launch ile game_url.
 *
 * Örnek: /play?game_id=23002&mode=real&wallet=main
 *        /play?game_id=23002&mode=fun
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once __DIR__ . '/../core/bootstrap.php';

$playGameId = trim(
    (string) ($_GET['game_id'] ?? $_GET['gameId'] ?? $_GET['gameid'] ?? '')
);
if ($playGameId === '') {
    header('Location: /slot');
    exit;
}

$playWallet = trim((string) ($_GET['wallet'] ?? 'main'));
$playMode   = strtolower(trim((string) ($_GET['mode'] ?? 'real')));
if ($playMode === '') {
    $playMode = 'real';
}

$playLang     = trim((string) ($_GET['lang'] ?? ''));
$playCurrency = trim((string) ($_GET['currency'] ?? ''));
$playRequestedOpenMode = strtolower(trim((string) ($_GET['open_mode'] ?? '')));
if (!in_array($playRequestedOpenMode, ['iframe', 'redirect'], true)) {
  $playRequestedOpenMode = '';
}

$demoFlag = isset($_GET['demo']) && ($_GET['demo'] === '1' || $_GET['demo'] === 'true');
if ($demoFlag || in_array($playMode, ['fun', 'demo'], true)) {
    $playMode = 'fun';
    $demoFlag = true;
}

$loggedIn     = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
$hasMemberJwt = !empty($_SESSION['member_jwt']);
$playIsAuthenticated = $loggedIn || $hasMemberJwt;

$playPayload = [
    'game_id' => $playGameId,
    'mode'    => $playMode,
];
$playPayload['open_mode'] = $playRequestedOpenMode !== ''
  ? $playRequestedOpenMode
  : ((function_exists('isMobile') && isMobile()) ? 'redirect' : 'iframe');
if ($playMode === 'real' && $playWallet !== '') {
    $playPayload['wallet'] = $playWallet;
}
if ($playLang !== '') {
    $playPayload['lang'] = $playLang;
}
if ($playCurrency !== '') {
    $playPayload['currency'] = $playCurrency;
}
if ($demoFlag) {
    $playPayload['demo'] = true;
}

$playJsPath = BASE_PATH . '/assets/js/play-page.js';
$playJsVer  = is_readable($playJsPath) ? (string) filemtime($playJsPath) : '1';
$playAuthSharedPath = BASE_PATH . '/assets/js/auth-shared.js';
$playAuthSharedVer = (string) ((is_file($playAuthSharedPath) ? filemtime($playAuthSharedPath) : '1') . '-' . (is_file($playAuthSharedPath) ? filesize($playAuthSharedPath) : '0'));
$playMobileUa = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
$playUaIsMobile = $playMobileUa !== '' && preg_match('/android|iphone|ipad|ipod|mobile|windows phone|opera mini|iemobile/', $playMobileUa) === 1;
$playBypassShell = $playRequestedOpenMode === 'redirect' || (function_exists('isMobile') && isMobile()) || $playUaIsMobile;

$playTitle = htmlspecialchars((string) ($ayar['site_adi'] ?? 'Oyun'), ENT_QUOTES, 'UTF-8');

if ($playBypassShell) {
    if (!function_exists('metropol_member_api_layout_vars')) {
        require_once __DIR__ . '/../config/member_api_public.php';
    }
    $memberApiLayout = metropol_member_api_layout_vars();
    ?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <title><?= $playTitle ?></title>
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicons/favicon.svg">
  <style>
    html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: #0f0522; color: #fff; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; }
    .play-redirecting { min-height: 100%; display: grid; place-items: center; }
    .play-redirecting img { width: 56px; height: 56px; display: block; }
  </style>
</head>
<body>
  <div class="play-redirecting"><img src="/assets/images/favicons/favicon.svg" alt=""></div>
  <script>
  window.__PLAY_LAUNCH_PAYLOAD__ = <?= json_encode($playPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  window.__USER_LOGGED_IN__ = <?= $playIsAuthenticated ? 'true' : 'false' ?>;
  window.__HAS_MEMBER_JWT__ = <?= $hasMemberJwt ? 'true' : 'false' ?>;
  window.__CSRF_TOKEN__ = <?= json_encode((string) ($_SESSION['csrf_token'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  window.__MEMBER_API_BASE__ = <?= json_encode((string) ($memberApiLayout['__MEMBER_API_BASE__'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  window.__FRONTEND_DIRECT_MEMBER_API__ = <?= !empty($memberApiLayout['__FRONTEND_DIRECT_MEMBER_API__']) ? 'true' : 'false' ?>;
  </script>
  <script src="<?= htmlspecialchars(asset_url('assets/js/auth-shared.js') . '?v=' . rawurlencode($playAuthSharedVer), ENT_QUOTES, 'UTF-8') ?>"></script>
  <script src="/assets/js/play-page.js?v=<?= htmlspecialchars($playJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
  <title><?= $playTitle ?> — Oyun</title>
  <link rel="icon" type="image/svg+xml" href="/assets/images/favicons/favicon.svg">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous">
<style>
/* global.css ile aynı tema anahtarları — play sayfası bağımsız yüklendiği için burada tekrarlanıyor */
:root {
  --play-primary: #5121DF;
  --play-primary-soft: rgba(81, 33, 223, 0.22);
  --play-secondary: #FCAC00;
  --play-header-bg: #0e0124;
  --play-header-border: rgba(104, 9, 76, 0.55);
  --play-body-bg: #0f0522;
  --play-balance-box-height: 36px;
  --play-font-ui: "Segoe UI", system-ui, -apple-system, BlinkMacSystemFont, "Roboto", "Helvetica Neue", Arial, sans-serif;
}
/* Sitenin profesyonel cüzdan ikonu (BetConstruct-Icons) — play sayfası bağımsız yüklendiği için burada tanımlanıyor */
@font-face {
  font-family: BetConstruct-Icons;
  src: url("/assets/fonts/BetConstruct-Icons.woff") format("woff"),
       url("/assets/fonts/BetConstruct-Icons.ttf") format("truetype");
  font-weight: 400;
  font-style: normal;
  font-display: block;
}
.bc-i-wallet {
  font-family: BetConstruct-Icons !important;
  font-style: normal;
  font-weight: 400;
  font-variant: normal;
  text-transform: none;
  line-height: 1;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
.bc-i-wallet::before { content: "\e918"; }
.play-shell-body { margin: 0; min-height: 100vh; display: flex; flex-direction: column; background: var(--play-body-bg); color: #e8eaed; }
.play-topbar {
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0; min-height: 48px; padding: 0 12px 0 14px;
  background: var(--play-header-bg);
  border-bottom: 1px solid var(--play-header-border);
  box-shadow: 0 6px 24px rgba(0, 0, 0, 0.35);
  gap: 10px;
}
.play-topbar-logo { display: flex; align-items: center; text-decoration: none; }
.play-topbar-logo img { display: block; height: 42px; width: auto; }
.play-topbar-actions { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
.play-topbar-balance {
  display: none;
  align-items: center;
  margin-right: 2px;
  min-width: 0;
  max-width: min(52vw, 260px);
  padding: 0 10px 0 4px;
  gap: 14px;
  font-family: var(--play-font-ui);
}
.play-topbar-balance.is-visible { display: flex; flex-direction: row; }
.play-topbar-balance-icon {
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: var(--play-balance-box-height);
  height: var(--play-balance-box-height);
  border-radius: 10px;
  border: 1px solid rgba(51, 193, 107, 0.45);
  background: rgba(51, 193, 107, 0.14);
  box-sizing: border-box;
  color: #33c16b;
  font-size: 17px;
}
.play-topbar-balance-text {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  justify-content: center;
  min-width: 0;
  line-height: 1.28;
  gap: 4px;
}
@media (min-width: 992px) {
  .play-topbar-balance {
    max-width: min(62vw, 420px);
  }
  .play-topbar-balance-text {
    flex-direction: row;
    align-items: center;
    gap: 10px;
  }
  .play-topbar-balance .play-bal-bonus {
    font-size: 12px;
  }
}
.play-topbar-balance .play-bal-main {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: var(--play-balance-box-height);
  font-weight: 700;
  font-size: 15px;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.02em;
  color: #fff;
  white-space: nowrap;
  padding: 0 10px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.18);
  background: rgba(255, 255, 255, 0.1);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
  box-sizing: border-box;
}
.play-topbar-balance .play-bal-bonus {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: var(--play-balance-box-height);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.03em;
  opacity: 0.9;
  color: rgba(255, 255, 255, 0.76);
  white-space: nowrap;
  padding: 0 10px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.14);
  background: rgba(255, 255, 255, 0.08);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
  box-sizing: border-box;
}
.play-topbar-balance .play-bal-bonus strong { color: var(--play-secondary); font-weight: 600; }
.play-icon-btn {
  border: none;
  background: rgba(255, 255, 255, 0.06);
  color: #e8eaed;
  width: 42px;
  height: 42px;
  border-radius: 10px;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 17px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;
}
.play-icon-btn:hover {
  background: rgba(81, 33, 223, 0.35);
  color: #fff;
  border-color: rgba(81, 33, 223, 0.55);
}
.play-icon-btn:active { transform: scale(0.96); }
.play-icon-btn:focus-visible {
  outline: 2px solid var(--play-secondary);
  outline-offset: 2px;
}
.play-icon-btn--fullscreen:hover {
  color: var(--play-secondary);
  border-color: rgba(252, 172, 0, 0.45);
  background: rgba(252, 172, 0, 0.12);
}
.play-icon-btn--close {
  color: #f0f0f0;
  background: rgba(220, 53, 69, 0.12);
  border-color: rgba(220, 53, 69, 0.35);
}
.play-icon-btn--close:hover {
  background: rgba(220, 53, 69, 0.45);
  color: #fff;
  border-color: rgba(255, 99, 115, 0.65);
}
.play-icon-btn i { pointer-events: none; }
.play-stage { flex: 1; display: flex; flex-direction: column; min-height: 0; position: relative; }
#playFrame { flex: 1; width: 100%; border: none; display: block; background: #000; min-height: 240px; }
.play-loader {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  background: #0d0f14; z-index: 2; flex-direction: column; gap: 12px;
}
.play-loader[hidden] { display: none !important; }
.play-loader-spinner {
  width: 36px; height: 36px; border: 3px solid rgba(255,255,255,.12);
  border-top-color: var(--play-secondary); border-radius: 50%;
  animation: play-spin .75s linear infinite;
}
@keyframes play-spin { to { transform: rotate(360deg); } }
.play-error-overlay {
  position: absolute; inset: 0; background: rgba(13,15,20,.92); z-index: 3;
  display: flex; align-items: center; justify-content: center; padding: 24px; text-align: center;
}
.play-error-overlay[hidden] { display: none !important; }
.play-error-box { max-width: 360px; font-size: 15px; line-height: 1.45; }
</style>
</head>
<body class="play-shell-body">

<?php
// Anında (gecikmesiz) ilk boya için sunucu tarafi render; site-settings-hydrate.js
// sayfa yuklendiginde /api/v2/site-settings'ten taze veriyi cekip gerekirse anlik
// duzeltir (data-site-logo-link isaretlisi sayesinde), boylece hem hizli hem guncel.
$playLogoUrl = (string) ($siteBranding['logo_url'] ?? $ayar['logo_url'] ?? '');
if ($playLogoUrl !== '' && class_exists('ApiMediaUrl', false)) {
    $playLogoUrl = ApiMediaUrl::resolve($playLogoUrl);
}
?>
<header class="play-topbar" aria-label="Oyun çubuğu">
  <a class="play-topbar-logo" href="/" title="Ana sayfa" data-site-logo-link>
    <img src="<?= htmlspecialchars($playLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars((string) ($ayar['site_adi'] ?? 'Site'), ENT_QUOTES, 'UTF-8') ?>" width="180" height="60" loading="lazy">
  </a>
  <div class="play-topbar-actions">
    <div class="play-topbar-balance<?= $loggedIn ? ' is-visible' : '' ?>" id="playBalanceWrap" aria-live="polite">
      <span class="play-topbar-balance-icon" aria-hidden="true"><i class="bc-i-wallet"></i></span>
      <div class="play-topbar-balance-text">
        <span class="play-bal-main"><span id="playBalanceMain">0,00</span> ₺</span>
        <span class="play-bal-bonus">Bonus <strong><span id="playBalanceBonus">0,00</span> ₺</strong></span>
      </div>
    </div>
    <button type="button" class="play-icon-btn play-icon-btn--fullscreen" id="playFullscreenBtn" title="Tam ekran (oyun alanı)" aria-label="Tam ekran">
      <i class="fa-solid fa-expand" id="playFullscreenIcon" aria-hidden="true"></i>
    </button>
    <button type="button" class="play-icon-btn play-icon-btn--close" id="playCloseBtn" title="Kapat ve geri dön" aria-label="Kapat">
      <i class="fa-solid fa-xmark" aria-hidden="true"></i>
    </button>
  </div>
</header>

<div class="play-stage">
  <div class="play-loader" id="playLoader">
    <div class="play-loader-spinner" aria-hidden="true"></div>
    <span>Oyun yükleniyor…</span>
  </div>
  <div class="play-error-overlay" id="playErrorOverlay" hidden>
    <div class="play-error-box" id="playErrorText" role="alert"></div>
  </div>
  <iframe id="playFrame" title="Oyun" allow="fullscreen; payment; autoplay"></iframe>
</div>

<script>
<?php
if (!function_exists('metropol_member_api_layout_vars')) {
    require_once __DIR__ . '/../config/member_api_public.php';
}
$memberApiLayout = metropol_member_api_layout_vars();
?>
window.__PLAY_LAUNCH_PAYLOAD__ = <?= json_encode($playPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.__USER_LOGGED_IN__ = <?= $playIsAuthenticated ? 'true' : 'false' ?>;
window.__HAS_MEMBER_JWT__ = <?= $hasMemberJwt ? 'true' : 'false' ?>;
window.__CSRF_TOKEN__ = <?= json_encode((string) ($_SESSION['csrf_token'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.__MEMBER_API_BASE__ = <?= json_encode((string) ($memberApiLayout['__MEMBER_API_BASE__'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
window.__FRONTEND_DIRECT_MEMBER_API__ = <?= !empty($memberApiLayout['__FRONTEND_DIRECT_MEMBER_API__']) ? 'true' : 'false' ?>;
</script>
<script src="<?= htmlspecialchars(asset_url('assets/js/auth-shared.js') . '?v=' . rawurlencode($playAuthSharedVer), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(asset_url('assets/js/site-settings-hydrate.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/play-page.js?v=<?= htmlspecialchars($playJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
