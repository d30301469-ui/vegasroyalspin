<?php
/**
 * Sportsbook (BetBy) — orijinal site header + gövdede tam boy iframe.
 * Launch URL, POST /api/v2/sportsbook-launch üzerinden alınır.
 * Route: /sportbook  (legacy_dispatch pages/ fallback)
 */
if (!defined('SPORTSBOOK_LIGHTWEIGHT_LAYOUT')) {
    define('SPORTSBOOK_LIGHTWEIGHT_LAYOUT', true);
}
if (!defined('FRONTEND_SKIP_REMOTE_CMS')) {
    define('FRONTEND_SKIP_REMOTE_CMS', true);
}

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    frontend_session_start();
}

require_once __DIR__ . '/../core/bootstrap.php';

$pageTitle = 'Spor Bahisleri';
$sbLang    = trim((string) ($_GET['lang'] ?? ''));
if ($sbLang === '' && function_exists('current_locale')) {
    $sbLang = current_locale();
}
if ($sbLang === '' || (class_exists('SiteI18n', false) && SiteI18n::normalize($sbLang) === '')) {
    $sbLang = 'tr';
}
$sbLoginUrl    = '/login';
$sbRegisterUrl = '/kayit';
$sbDepositUrl  = '/profile/deposit?openDepositPanel=1';
$__shellFragment = function_exists('shell_nav_fragment_mode') && shell_nav_fragment_mode();
?>
<?php if (!$__shellFragment): ?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>
<?php endif; ?>

<div id="shellPageHost" data-shell-path="/sportbook">

<link rel="dns-prefetch" href="//operator-sportsbook.site">
<link rel="preconnect" href="https://operator-sportsbook.site" crossorigin>

<style data-shell-style="sportbook-stage">
  .mainContentWrap { overflow-x: hidden; }
  .sportbook-stage { position: relative; width: 100%; height: calc(100vh - var(--header-sticky-top, 140px) - 18px); min-height: 520px; margin: 0 0 18px; background: #0f0522; border-radius: 12px; overflow: hidden; }
  @media (max-width: 900px) { .sportbook-stage { height: calc(100vh - 132px - 72px - env(safe-area-inset-bottom)); min-height: 420px; border-radius: 10px; margin-bottom: 14px; } }
  .sportbook-stage iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }
  .sportbook-overlay { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; text-align: center; padding: 24px; background: #0f0522; }
  .sportbook-overlay[hidden] { display: none; }
  .sportbook-spinner { width: 44px; height: 44px; border-radius: 50%; border: 4px solid rgba(255,255,255,.18); border-top-color: #FCAC00; animation: sbspin 1s linear infinite; }
  @keyframes sbspin { to { transform: rotate(360deg); } }
  .sportbook-error-box { max-width: 460px; background: #1a0a2e; border: 1px solid rgba(104,9,76,.55); border-radius: 14px; padding: 22px 24px; color: #e8eaed; font-size: 15px; line-height: 1.5; }
  .sportbook-error-box a { color: #FCAC00; }
</style>

<main class="sportbook-stage" id="sbStage">
  <iframe id="sbFrame" title="Spor Bahisleri" allow="fullscreen; payment; autoplay" referrerpolicy="no-referrer"></iframe>
  <div class="sportbook-overlay" id="sbLoader"><div class="sportbook-spinner"></div></div>
  <div class="sportbook-overlay" id="sbError" hidden><div class="sportbook-error-box" id="sbErrorText"></div></div>
</main>

<script>
(function () {
  var SB_LANG = <?= json_encode($sbLang, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SB_LOGIN = <?= json_encode($sbLoginUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SB_REGISTER = <?= json_encode($sbRegisterUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SB_DEPOSIT = <?= json_encode($sbDepositUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

  function el(id) { return document.getElementById(id); }
  function showError(html) {
    var l = el('sbLoader'), e = el('sbError'), t = el('sbErrorText');
    if (l) l.hidden = true;
    if (t) t.innerHTML = html;
    if (e) e.hidden = false;
  }

  function hideLoader() {
    var loader = el('sbLoader');
    if (loader) loader.hidden = true;
  }

  function bindFrameLoad(frame) {
    if (!frame) return;
    frame.addEventListener('load', hideLoader, { once: true });
    setTimeout(hideLoader, 5000);
  }

  function openUrl(url) {
    if (url) window.location.href = url;
  }

  function pickSrc(data) {
    var mob = /Mobi|Android|iPhone|iPad/i.test(navigator.userAgent);
    if (mob && data.mobile_iframe_url) return data.mobile_iframe_url;
    return data.iframe_url || data.game_url || data.launch_url || '';
  }

  function paintFrame(src) {
    var frame = el('sbFrame');
    if (frame && src) frame.src = src;
    bindFrameLoad(frame);
  }

  function mountDistro(data) {
    var src = pickSrc(data);
    paintFrame(src);
    var frame = el('sbFrame');
    function go() {
      if (!window.BcEmbed || !frame) return;
      window.BcEmbed.mount({
        iframe: frame,
        src: data.iframe_url || data.game_url || '',
        mobileSrc: data.mobile_iframe_url || data.game_url || '',
        liveUrl: data.live_url || '',
        mobileLiveUrl: data.mobile_live_url || '',
        prematchUrl: data.prematch_url || '',
        mobilePrematchUrl: data.mobile_prematch_url || '',
        preferences: data.preferences || null,
        authToken: data.auth_token || '',
        userId: data.user_id || 0,
        loginUrl: SB_LOGIN,
        registerUrl: SB_REGISTER,
        depositUrl: SB_DEPOSIT,
        onLogin: function () { openUrl(SB_LOGIN); },
        onRegister: function () { openUrl(SB_REGISTER); },
        onDeposit: function () { openUrl(SB_DEPOSIT); }
      });
    }
    if (window.BcEmbed) { go(); return; }
    var js = data.embed_js || '';
    if (!js) { go(); return; }
    var s = document.createElement('script');
    s.src = js;
    s.async = true;
    s.onload = go;
    s.onerror = go;
    document.head.appendChild(s);
  }

  function fallbackShared() {
    return {
      apiUrl: function (p) { return p.charAt(0) === '/' ? p : '/' + p; },
      memberRequestInit: function (_url, extra) {
        var h = Object.assign({ Accept: 'application/json', 'Content-Type': 'application/json' }, extra || {});
        try {
          var jwt = localStorage.getItem('app_member_jwt') || '';
          if (jwt) h.Authorization = 'Bearer ' + jwt;
        } catch (e) {}
        return { credentials: 'same-origin', headers: h };
      }
    };
  }

  function doLaunch(Shared) {
    var LAUNCH_URL = Shared.apiUrl ? Shared.apiUrl('/api/v2/sportsbook-launch') : '/api/v2/sportsbook-launch';
    var init = Shared.memberRequestInit
      ? Shared.memberRequestInit(LAUNCH_URL, { Accept: 'application/json', 'Content-Type': 'application/json' })
      : { credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' } };

    fetch(LAUNCH_URL, {
      method: 'POST',
      credentials: init.credentials,
      headers: init.headers,
      body: JSON.stringify({ lang: SB_LANG || 'tr', channel: /Mobi|Android|iPhone/i.test(navigator.userAgent) ? 'mobile' : 'desktop' })
    }).then(function (res) {
      return res.text().then(function (txt) {
        var j = null; try { j = txt ? JSON.parse(txt) : null; } catch (e) {}
        return { ok: res.ok, status: res.status, j: j };
      });
    }).then(function (x) {
      var data = (x.j && (x.j.data || x.j)) || {};
      var url = data.game_url || data.launch_url || data.launchUrl || '';
      if (x.ok && (data.provider === 'distro' || data.embed_js) && (url || data.iframe_url || data.mobile_iframe_url)) {
        mountDistro(data);
        return;
      }
      if (x.ok && /^https?:\/\//i.test(url)) {
        paintFrame(url);
        return;
      }
      var msg = (x.j && (x.j.message || x.j.msg)) || 'Spor bahisleri şu anda açılamıyor.';
      showError('<strong>' + msg + '</strong><br><br>Lütfen daha sonra tekrar deneyin.<br><a href="/">Ana sayfaya dön</a>');
    }).catch(function () {
      showError('Bağlantı hatası oluştu.<br><a href="/">Ana sayfaya dön</a>');
    });
  }

  function boot() {
    var Shared = window.BetcoAuthShared || window.MetropolShared || {};
    if (Shared && Shared.apiUrl) {
      doLaunch(Shared);
      return;
    }
    doLaunch(fallbackShared());
  }
  boot();
})();
</script>
</div><!-- #shellPageHost -->
<?php if (!empty($__shellFragment)): exit; endif; ?>
<?php include VIEW_PATH . '/partials/footer.php'; ?>
