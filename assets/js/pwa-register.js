(function () {
  var deferredInstallPrompt = null;
  var installButton = null;

  function isStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function isAndroid() {
    return /android/i.test(navigator.userAgent || '');
  }

  function isIos() {
    var ua = navigator.userAgent || '';
    var iOSDevice = /iphone|ipad|ipod/i.test(ua);
    var iPadOS = navigator.platform === 'MacIntel' && (navigator.maxTouchPoints || 0) > 1;
    return iOSDevice || iPadOS;
  }

  function isIosSafari() {
    if (!isIos()) {
      return false;
    }
    var ua = navigator.userAgent || '';
    // Add to Home Screen is only available in Safari (exclude Chrome/Firefox/other iOS browsers).
    return !/crios|fxios|edgios|opios|mercury/i.test(ua);
  }

  function canUseInstallUx() {
    if (isStandaloneMode()) {
      return false;
    }
    return isAndroid() || isIosSafari();
  }

  function showIosInstallGuide() {
    if (document.getElementById('pwaIosGuide')) {
      return;
    }

    var overlay = document.createElement('div');
    overlay.id = 'pwaIosGuide';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'Ana ekrana ekleme rehberi');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483001;background:rgba(4,0,12,.6);display:flex;align-items:flex-end;justify-content:center;';

    var sheet = document.createElement('div');
    sheet.style.cssText = 'width:100%;max-width:420px;background:#1b0733;color:#fff;border-radius:18px 18px 0 0;padding:20px 20px calc(20px + env(safe-area-inset-bottom));box-shadow:0 -8px 30px rgba(0,0,0,.5);font-family:inherit;';

    sheet.innerHTML =
      '<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">' +
        '<img src="/assets/images/favicons/apple-touch-icon.png" alt="" width="44" height="44" style="border-radius:12px;">' +
        '<div><div style="font-weight:800;font-size:16px;">VegasRoyal uygulamasini yukle</div>' +
        '<div style="font-size:12px;opacity:.7;">Safari ile birkac saniyede ana ekrana ekle</div></div>' +
      '</div>' +
      '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;">' +
        '<span style="flex:0 0 30px;height:30px;border-radius:50%;background:#8a2be2;display:inline-flex;align-items:center;justify-content:center;font-weight:800;">1</span>' +
        '<div style="font-size:14px;">Alttaki <strong>Paylas</strong> simgesine dokun ' +
          '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" style="vertical-align:middle;"><path d="M12 3v12M12 3l-4 4M12 3l4 4" stroke="#c9a3ff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 12v6a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-6" stroke="#c9a3ff" stroke-width="2" stroke-linecap="round"/></svg>' +
        '</div>' +
      '</div>' +
      '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;">' +
        '<span style="flex:0 0 30px;height:30px;border-radius:50%;background:#8a2be2;display:inline-flex;align-items:center;justify-content:center;font-weight:800;">2</span>' +
        '<div style="font-size:14px;"><strong>Ana Ekrana Ekle</strong> secenegine dokun</div>' +
      '</div>' +
      '<div style="display:flex;align-items:center;gap:12px;padding:10px 0 4px;">' +
        '<span style="flex:0 0 30px;height:30px;border-radius:50%;background:#8a2be2;display:inline-flex;align-items:center;justify-content:center;font-weight:800;">3</span>' +
        '<div style="font-size:14px;">Sag ustten <strong>Ekle</strong> butonuna dokun</div>' +
      '</div>' +
      '<button type="button" id="pwaIosGuideClose" style="margin-top:16px;width:100%;padding:13px;border:0;border-radius:12px;background:linear-gradient(135deg,#8a2be2 0%,#5b1aa8 100%);color:#fff;font-weight:800;font-size:15px;cursor:pointer;">Anladim</button>';

    overlay.appendChild(sheet);

    function close() {
      overlay.remove();
    }
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        close();
      }
    });
    document.body.appendChild(overlay);
    var closeBtn = document.getElementById('pwaIosGuideClose');
    if (closeBtn) {
      closeBtn.addEventListener('click', close);
    }
  }

  function ensureInstallButton() {
    if (!canUseInstallUx()) {
      return null;
    }

    if (installButton) {
      return installButton;
    }

    if (!document.getElementById('pwaInstallButtonStyles')) {
      var style = document.createElement('style');
      style.id = 'pwaInstallButtonStyles';
      style.textContent =
        '#pwaInstallButton{position:fixed;right:16px;bottom:88px;z-index:2147483000;width:56px;height:56px;padding:0;border:0;border-radius:50%;' +
        'background:linear-gradient(135deg,#8a2be2 0%,#5b1aa8 100%);color:#fff;cursor:pointer;display:none;align-items:center;justify-content:center;' +
        'box-shadow:0 10px 24px rgba(90,26,168,.45),0 2px 6px rgba(0,0,0,.3);transition:transform .18s ease,box-shadow .18s ease;-webkit-tap-highlight-color:transparent;}' +
        '#pwaInstallButton:active{transform:scale(.92);}' +
        '#pwaInstallButton svg{width:26px;height:26px;display:block;}' +
        '#pwaInstallButton::after{content:"";position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 0 rgba(138,43,226,.55);animation:pwaPulse 2.2s infinite;}' +
        '@keyframes pwaPulse{0%{box-shadow:0 0 0 0 rgba(138,43,226,.5);}70%{box-shadow:0 0 0 14px rgba(138,43,226,0);}100%{box-shadow:0 0 0 0 rgba(138,43,226,0);}}';
      document.head.appendChild(style);
    }

    installButton = document.createElement('button');
    installButton.type = 'button';
    installButton.id = 'pwaInstallButton';
    installButton.setAttribute('aria-label', 'Uygulamayi yukle');
    installButton.setAttribute('title', 'Uygulamayi yukle');
    installButton.innerHTML =
      '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
      '<path d="M12 3v10m0 0l-4-4m4 4l4-4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' +
      '<path d="M4 15v2a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-2" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';

    installButton.addEventListener('click', function () {
      if (isIosSafari()) {
        showIosInstallGuide();
        return;
      }

      if (!deferredInstallPrompt) {
        alert('Chrome menu > Add to Home screen adimlari ile yukleme yapabilirsiniz.');
        return;
      }

      deferredInstallPrompt.prompt();
      deferredInstallPrompt.userChoice
        .catch(function () {
          return null;
        })
        .then(function () {
          deferredInstallPrompt = null;
          updateInstallButtonVisibility();
        });
    });

    document.body.appendChild(installButton);
    return installButton;
  }

  function updateInstallButtonVisibility() {
    var btn = ensureInstallButton();
    if (!btn) {
      return;
    }

    btn.style.display = 'inline-flex';
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
      return;
    }

    var isSecure = window.isSecureContext || location.hostname === 'localhost' || location.hostname === '127.0.0.1';
    if (!isSecure) {
      console.warn('PWA install requires HTTPS context.');
      return;
    }

    navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(function (error) {
      console.error('Service worker registration failed:', error);
    });
  }

  window.addEventListener('beforeinstallprompt', function (event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    updateInstallButtonVisibility();
  });

  window.addEventListener('appinstalled', function () {
    deferredInstallPrompt = null;
    if (installButton) {
      installButton.style.display = 'none';
    }
  });

  window.addEventListener('load', function () {
    if (location.protocol === 'http:' && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
      location.replace('https://' + location.host + location.pathname + location.search + location.hash);
      return;
    }

    registerServiceWorker();
    updateInstallButtonVisibility();
  });
})();
