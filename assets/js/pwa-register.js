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

    installButton = document.createElement('button');
    installButton.type = 'button';
    installButton.id = 'pwaInstallButton';
    installButton.textContent = 'Uygulamayi Yukle';
    installButton.setAttribute('aria-label', 'Uygulamayi yukle');
    installButton.style.position = 'fixed';
    installButton.style.right = '14px';
    installButton.style.bottom = '84px';
    installButton.style.zIndex = '2147483000';
    installButton.style.background = '#18a957';
    installButton.style.color = '#fff';
    installButton.style.border = '0';
    installButton.style.borderRadius = '999px';
    installButton.style.padding = '12px 16px';
    installButton.style.fontSize = '14px';
    installButton.style.fontWeight = '700';
    installButton.style.boxShadow = '0 8px 20px rgba(0,0,0,.26)';
    installButton.style.cursor = 'pointer';
    installButton.style.display = 'none';

    installButton.addEventListener('click', async function () {
      if (!deferredInstallPrompt) {
        alert('Chrome menu > Add to Home screen adimlarini kullanarak yukleyebilirsiniz.');
        return;
      }

      deferredInstallPrompt.prompt();
      try {
        await deferredInstallPrompt.userChoice;
      } catch (e) {
        // no-op
      }
      deferredInstallPrompt = null;
      installButton.style.display = 'none';
    });

    document.body.appendChild(installButton);
    return installButton;
  }

  function updateInstallButtonVisibility() {
    var btn = ensureInstallButton();
    if (!btn) {
      return;
    }

    btn.style.display = deferredInstallPrompt ? 'inline-flex' : 'none';
    btn.style.alignItems = 'center';
    btn.style.justifyContent = 'center';
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
    registerServiceWorker();
    ensureInstallButton();
  });
})();
