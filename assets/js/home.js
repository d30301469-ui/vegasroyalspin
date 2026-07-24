document.addEventListener('DOMContentLoaded', function () {
  /* Ana sayfa (mobil): JACKPOT | KAZANANLAR — slot sayfası ile aynı sekme davranışı */
  (function initHomeJackpotHeroTabs() {
    var root = document.querySelector('[data-slot-hero-tabs]');
    if (!root) return;
    var tabs = root.querySelectorAll('.slot-hero-tab[data-slot-hero-tab]');
    var panels = root.querySelectorAll('.slot-hero-tabpanel[data-slot-hero-panel]');
    if (!tabs.length || !panels.length) return;

    function activate(key) {
      tabs.forEach(function (t) {
        var on = t.getAttribute('data-slot-hero-tab') === key;
        t.classList.toggle('slot-hero-tab--active', on);
        t.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach(function (p) {
        var on = p.getAttribute('data-slot-hero-panel') === key;
        p.classList.toggle('slot-hero-tabpanel--active', on);
        if (on) {
          p.removeAttribute('hidden');
        } else {
          p.setAttribute('hidden', '');
        }
      });
    }

    root.addEventListener('click', function (e) {
      var tab = e.target.closest('.slot-hero-tab[data-slot-hero-tab]');
      if (!tab || !root.contains(tab)) return;
      var key = tab.getAttribute('data-slot-hero-tab');
      if (!key || tab.classList.contains('slot-hero-tab--active')) return;
      e.preventDefault();
      activate(key);
    });
  })();

  try {
    var params = new URLSearchParams(window.location.search);
    if (params.get('error') === 'not_logged_in') {
      if (window.MaltabetToast) {
        MaltabetToast.warning('Lütfen hesabınıza giriş yapınız!', 'Uyarı');
        window.setTimeout(function () {
          window.location.href = window.location.pathname;
        }, 3000);
      }
    }
  } catch (e) {
    console && console.warn && console.warn('Login toast error', e);
  }

  (function () {
    try {
      var Shared = window.BetcoAuthShared || {};
      var visitUrl = Shared.apiUrl ? Shared.apiUrl('/api/v2/track-visit') : '/api/v2/track-visit';
      fetch(visitUrl, {
        method: 'GET',
        credentials: 'include',
        headers: {
          'Accept': 'application/json'
        }
      })
        .then(function (resp) {
          if (!resp.ok) {
            return resp.json().catch(function () {
              throw new Error('HTTP ' + resp.status);
            }).then(function (body) {
              throw new Error((body && body.message) ? body.message : ('HTTP ' + resp.status));
            });
          }
          return resp.json();
        })
        .then(function (data) {
          if (console && console.log && data && (data.success === true || data.ok === true)) {
            console.log('Ziyaretçi kaydedildi', data);
          }
        })
        .catch(function (err) {
          if (console && console.warn) {
            console.warn('Ziyaretçi kaydı hatası', err);
          }
        });
    } catch (err) {
      if (console && console.warn) {
        console.warn('Ziyaretçi kaydı beklenmeyen hata', err);
      }
    }
  })();

  // Banner sıralı parlama (glow) – sadece aktif öğe değişir, tüm liste taranmaz
  (function initBannerGlow() {
    try {
      const bannerRow = document.querySelector('.hm-row-bc');
      const banners = bannerRow ? bannerRow.querySelectorAll('.product-banner-info-bc') : [];
      if (!banners.length) return;

      const ACTIVE_CLASS = 'banner-glow-bc';
      let currentIndex = 0;
      const total = banners.length;

      function step() {
        banners[currentIndex].classList.remove(ACTIVE_CLASS);
        currentIndex = (currentIndex + 1) % total;
        banners[currentIndex].classList.add(ACTIVE_CLASS);
      }

      banners[0].classList.add(ACTIVE_CLASS);
      setInterval(step, 1900);
    } catch (e) {
      if (typeof console !== 'undefined' && console.warn) console.warn('Ana sayfa banner parlama hatası', e);
    }
  })();
});

function showLoginWarning() {
  if (window.MaltabetToast) {
    MaltabetToast.warning('Lütfen hesabınıza giriş yapınız!', 'Uyarı');
  }
}

function openPlayUrl(url) {
  var isMobileSite = !!(document.body && document.body.classList.contains('mobile-site'));
  var targetUrl = String(url || '');
  if (!isMobileSite) {
    var hasTouch = (navigator.maxTouchPoints || 0) > 0;
    var narrowViewport = !!(window.matchMedia && window.matchMedia('(max-width: 1024px)').matches);
    isMobileSite = hasTouch && narrowViewport;
  }
  if (isMobileSite) {
    try {
      var parsed = new URL(targetUrl, window.location.origin);
      parsed.searchParams.set('open_mode', 'redirect');
      targetUrl = parsed.pathname + parsed.search + parsed.hash;
    } catch (e) {
      targetUrl += (targetUrl.indexOf('?') === -1 ? '?' : '&') + 'open_mode=redirect';
    }
  }
  window.location.href = targetUrl;
}

function handlePlay(gameId) {
  if (!gameId) {
    return;
  }

  function memberLoggedInRuntime() {
    var Shared = window.BetcoAuthShared || {};
    if (Shared && typeof Shared.runtimeSessionLoggedIn === 'function' && Shared.runtimeSessionLoggedIn()) {
      return true;
    }
    if (Shared && typeof Shared.getMemberJwt === 'function' && Shared.getMemberJwt() !== '') {
      return true;
    }
    return window.__USER_LOGGED_IN__ === true || window.__HAS_MEMBER_JWT__ === true;
  }

  function openLoginGate() {
    if (typeof window.__openLoginModal === 'function') {
      window.__openLoginModal();
      return;
    }
    if (window.MaltabetAuth && typeof window.MaltabetAuth.showLoginModal === 'function') {
      window.MaltabetAuth.showLoginModal();
      return;
    }
    var loginBtn = document.getElementById('Giris');
    if (loginBtn) {
      loginBtn.click();
    }
  }

  var url = '/play?game_id=' + encodeURIComponent(gameId) + '&mode=real&wallet=main';

  function launch() {
    if (window.MaltabetWalletPicker && typeof window.MaltabetWalletPicker.launch === 'function') {
      window.MaltabetWalletPicker.launch(url, openPlayUrl);
      return;
    }
    openPlayUrl(url);
  }

  if (!memberLoggedInRuntime()) {
    var Shared = window.BetcoAuthShared || {};
    if (Shared && typeof Shared.hydrateMemberJwt === 'function') {
      Shared.hydrateMemberJwt().then(function () {
        if (memberLoggedInRuntime()) {
          launch();
          return;
        }
        openLoginGate();
      }).catch(function () {
        openLoginGate();
      });
      return;
    }
    openLoginGate();
    return;
  }

  launch();
}

function handleDemo(gameId) {
  if (!gameId) {
    return;
  }
  var url = '/play?game_id=' + encodeURIComponent(gameId) + '&mode=fun&demo=1';
  window.location.href = url;
}

