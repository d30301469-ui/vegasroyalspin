<?php
/**
 * Sportsbook (BetBy) — orijinal site header + gövdede tam boy iframe.
 * Launch URL, POST /api/v2/sportsbook-launch üzerinden alınır.
 * Route: /sportbook  (legacy_dispatch pages/ fallback)
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once __DIR__ . '/../core/bootstrap.php';

$pageTitle = 'Spor Bahisleri';
$sbLang    = trim((string) ($_GET['lang'] ?? 'tr'));
?>
<?php include VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<style>
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
  <iframe id="sbFrame" title="Spor Bahisleri" allow="fullscreen; payment; autoplay"></iframe>
  <div class="sportbook-overlay" id="sbLoader"><div class="sportbook-spinner"></div></div>
  <div class="sportbook-overlay" id="sbError" hidden><div class="sportbook-error-box" id="sbErrorText"></div></div>
</main>

<?php include VIEW_PATH . '/partials/footer.php'; ?>

<script>
(function () {
  var SB_LANG = <?= json_encode($sbLang, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

  function el(id) { return document.getElementById(id); }
  function showError(html) {
    var l = el('sbLoader'), e = el('sbError'), t = el('sbErrorText');
    if (l) l.hidden = true;
    if (t) t.innerHTML = html;
    if (e) e.hidden = false;
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
      if (x.ok && /^https?:\/\//i.test(url)) {
        var frame = el('sbFrame'), loader = el('sbLoader');
        frame.src = url;
        frame.addEventListener('load', function () { if (loader) loader.hidden = true; }, { once: true });
        setTimeout(function () { if (loader) loader.hidden = true; }, 5000);
        return;
      }
      var msg = (x.j && (x.j.message || x.j.msg)) || 'Spor bahisleri şu anda açılamıyor.';
      showError('<strong>' + msg + '</strong><br><br>Lütfen daha sonra tekrar deneyin.<br><a href="/">Ana sayfaya dön</a>');
    }).catch(function () {
      showError('Bağlantı hatası oluştu.<br><a href="/">Ana sayfaya dön</a>');
    });
  }

  function boot(tries) {
    var Shared = window.BetcoAuthShared || window.MetropolShared || {};
    if (Shared && Shared.apiUrl) {
      if (Shared.onReady) { Shared.onReady(function () { doLaunch(Shared); }); }
      else { doLaunch(Shared); }
      return;
    }
    if (tries > 40) { doLaunch({}); return; }
    setTimeout(function () { boot(tries + 1); }, 100);
  }
  boot(0);
})();
</script>
