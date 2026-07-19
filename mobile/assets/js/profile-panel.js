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
    document.body.classList.add('overlay-sliding-is-visible', 'overlaySlidingIsVisible');
    isOpen = true;
    syncBalance();
    syncBalanceRail(panel);
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
    document.body.classList.remove('overlay-sliding-is-visible', 'overlaySlidingIsVisible');
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

  function bindBalanceRail(panel) {
    var rail = panel.querySelector('.swiper-wrapper');
    var slides = panel.querySelectorAll('.swiper-slide');
    var dots = panel.querySelectorAll('.swiper-pagination-bullet');
    if (!rail || !slides.length || dots.length < 2) return;

    function update() {
      var railCenter = rail.scrollLeft + (rail.clientWidth / 2);
      var activeIndex = 0;
      var activeDistance = Infinity;

      slides.forEach(function (slide, index) {
        var slideCenter = slide.offsetLeft + (slide.offsetWidth / 2);
        var distance = Math.abs(railCenter - slideCenter);
        if (distance < activeDistance) {
          activeDistance = distance;
          activeIndex = index;
        }
      });

      slides.forEach(function (slide, index) {
        slide.classList.toggle('swiper-slide-active', index === activeIndex);
        slide.classList.toggle('swiper-slide-prev', index === activeIndex - 1);
        slide.classList.toggle('swiper-slide-next', index === activeIndex + 1);
      });
      dots.forEach(function (dot, index) {
        dot.classList.toggle('swiper-pagination-bullet-active', index === activeIndex);
      });
    }

    rail.addEventListener('scroll', update, { passive: true });
    update();
  }

  function syncBalanceRail(panel) {
    if (!panel) return;
    var rail = panel.querySelector('.swiper-wrapper');
    if (rail) rail.dispatchEvent(new Event('scroll'));
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
      bindBalanceRail(panel);
      panel.addEventListener('click', function (e) {
        var target = e.target && e.target.closest ? e.target : null;
        if (!target) return;

        var close = target.closest('.hdr-user-close');
        if (close) {
          e.preventDefault();
          e.stopPropagation();
          closePanel();
          return;
        }

        var copy = target.closest('.u-i-p-p-u-i-d-user-id-copy-bc');
        if (copy) {
          e.preventDefault();
          e.stopPropagation();
          var uid = copy.getAttribute('data-user-id') || '';
          if (uid && navigator.clipboard) {
            navigator.clipboard.writeText(uid).catch(function () {});
          }
          return;
        }

        var menuItem = target.closest('.u-i-p-l-head-bc[data-href]');
        if (menuItem) {
          e.preventDefault();
          window.location.href = menuItem.getAttribute('data-href');
          return;
        }

        var logoutButton = target.closest('.userLogoutBtn');
        if (logoutButton) {
          e.preventDefault();
          window.location.href = '/logout';
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
