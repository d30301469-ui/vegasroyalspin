(function () {
  var deferredInstallPrompt = null;
  var installButton = null;

  function isStandaloneMode() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function isAndroid() {
    return /android/i.test(navigator.userAgent || '');
  }

  function canUseInstallUx() {
    return isAndroid() && !isStandaloneMode();
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
