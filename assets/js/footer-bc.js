(function () {
  function initFooterClock() {
    var el = document.getElementById('footerClockWidget');
    if (!el) return;

    function tick() {
      el.textContent = new Date().toLocaleTimeString('tr-TR', {
        timeZone: 'Europe/Istanbul',
        hour12: false
      });
    }

    tick();
    setInterval(tick, 1000);
  }

  function initFooterSliders() {
    document.querySelectorAll('[data-footer-slider]').forEach(function (wrapper) {
      var row = wrapper.querySelector('.horizontalSliderRow');
      var viewport = wrapper.querySelector('.horizontalSliderViewport');
      var prev = wrapper.querySelector('[data-slider-prev]');
      var next = wrapper.querySelector('[data-slider-next]');
      if (!row || !viewport || !prev || !next) return;

      function stepSize() {
        return Math.max(220, Math.floor(viewport.clientWidth * 0.55));
      }

      prev.addEventListener('click', function () {
        viewport.scrollBy({ left: -stepSize(), behavior: 'smooth' });
      });

      next.addEventListener('click', function () {
        viewport.scrollBy({ left: stepSize(), behavior: 'smooth' });
      });

      viewport.addEventListener('wheel', function (event) {
        if (Math.abs(event.deltaY) <= Math.abs(event.deltaX)) return;
        event.preventDefault();
        viewport.scrollLeft += event.deltaY;
      }, { passive: false });

      var dragging = false;
      var startX = 0;
      var startScrollLeft = 0;

      viewport.addEventListener('pointerdown', function (event) {
        dragging = true;
        startX = event.clientX;
        startScrollLeft = viewport.scrollLeft;
        viewport.classList.add('is-dragging');
        viewport.setPointerCapture(event.pointerId);
      });

      viewport.addEventListener('pointermove', function (event) {
        if (!dragging) return;
        viewport.scrollLeft = startScrollLeft - (event.clientX - startX);
      });

      function stopDragging(event) {
        if (!dragging) return;
        dragging = false;
        viewport.classList.remove('is-dragging');
        if (event && typeof viewport.releasePointerCapture === 'function') {
          try {
            viewport.releasePointerCapture(event.pointerId);
          } catch (e) {}
        }
      }

      viewport.addEventListener('pointerup', stopDragging);
      viewport.addEventListener('pointercancel', stopDragging);
      viewport.addEventListener('pointerleave', stopDragging);

      window.addEventListener('resize', function () {
        viewport.scrollLeft = Math.min(viewport.scrollLeft, Math.max(0, row.scrollWidth - viewport.clientWidth));
      });
    });
  }

  function init() {
    initFooterClock();
    initFooterSliders();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
