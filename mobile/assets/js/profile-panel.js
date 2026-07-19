(function () {
  'use strict';

  function getPanel() { return document.getElementById('mprofilePanel'); }
  function getOverlay() { return document.getElementById('mprofileOverlay'); }

  var isOpen = false;

  function openPanel() {
    var panel = getPanel();
    var overlay = getOverlay();
    if (!panel || !overlay) return false;

    // Diğer açık katmanları kapat
    if (typeof window.__closeSmartPanel === 'function') window.__closeSmartPanel();
    if (typeof window.__closeMobileNavMenu === 'function') window.__closeMobileNavMenu();

    overlay.classList.add('is-open');
    panel.classList.add('is-open');
    overlay.setAttribute('aria-hidden', 'false');
    panel.setAttribute('aria-hidden', 'false');
    document.body.classList.add('mprofile-open');
    isOpen = true;
    syncBalance();
    return true;
  }

  function closePanel() {
    var panel = getPanel();
    var overlay = getOverlay();
    if (!panel || !overlay) return;
    overlay.classList.remove('is-open');
    panel.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    panel.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('mprofile-open');
    isOpen = false;
  }

  /** Header'daki ana bakiyeyi panele yansıt. */
  function syncBalance() {
    var target = document.querySelector('[data-balance-target="mprofileMain"]');
    var source = document.getElementById('headerBalanceMain')
      || document.querySelector('[data-balance-target="headerBalanceMain"]');
    if (target && source) {
      target.textContent = source.textContent.trim() || '0';
    }
  }

  window.__openMobileProfilePanel = openPanel;
  window.__closeMobileProfilePanel = closePanel;

  function bind() {
    var avatar = document.getElementById('toggleButton');
    if (avatar) {
      avatar.addEventListener('click', function (e) {
        var panel = getPanel();
        if (!panel) return; // panel yoksa (misafir) varsayılan davranış
        e.preventDefault();
        e.stopImmediatePropagation();
        isOpen ? closePanel() : openPanel();
      }, true);
    }

    var overlay = getOverlay();
    if (overlay) overlay.addEventListener('click', closePanel);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen) closePanel();
    });

    // Kullanıcı ID kopyalama
    var panel = getPanel();
    if (panel) {
      panel.addEventListener('click', function (e) {
        var copy = e.target && e.target.closest ? e.target.closest('.mprofile-user__copy') : null;
        if (!copy) return;
        e.preventDefault();
        e.stopPropagation();
        var uid = copy.getAttribute('data-user-id') || '';
        if (uid && navigator.clipboard) {
          navigator.clipboard.writeText(uid).catch(function () {});
        }
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
