(function () {
  function getScrollY() {
    return window.pageYOffset
      || document.documentElement.scrollTop
      || document.body.scrollTop
      || 0;
  }

  function initBackToTop() {
    var wrap = document.getElementById('backToTopWrap');
    var btn = document.getElementById('scrollToTopBtn');
    if (!wrap || !btn || btn.dataset.backToTopReady === '1') return;
    btn.dataset.backToTopReady = '1';

    var scrollY = 0;
    var hideTimer = null;
    var throttleMs = 100;
    var lastRun = 0;

    function setVisible(show) {
      wrap.classList.toggle('nav-floating-btn-hide', !show);
    }

    function evaluateVisibility() {
      var show = scrollY > window.innerHeight;
      setVisible(show);

      if (hideTimer) {
        clearTimeout(hideTimer);
        hideTimer = null;
      }

      if (show) {
        hideTimer = setTimeout(function () {
          setVisible(false);
        }, 5000);
      }
    }

    function onScroll() {
      var now = Date.now();
      if (now - lastRun < throttleMs) return;
      lastRun = now;
      scrollY = getScrollY();
      evaluateVisibility();
    }

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });

    window.addEventListener('scroll', onScroll, { passive: true });
    scrollY = getScrollY();
    evaluateVisibility();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBackToTop);
  } else {
    initBackToTop();
  }
})();
