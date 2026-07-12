(function () {
  'use strict';

  var TAB_SEL = '.jp-tab';
  var PANEL_SEL = '.jp-panel';
  var AMOUNT_SEL = '.jp-amount';
  var SECTION_SEL = '.jp-section[data-jackpot-epoch]';

  function init() {
  var container = document.querySelector('.jp-section');
  if (container) {
    container.addEventListener('click', function (e) {
      var tab = e.target.closest(TAB_SEL);
      if (!tab) return;
      var target = tab.getAttribute('data-jp-provider');
      if (!target) return;

      var tabs = container.querySelectorAll(TAB_SEL);
      var panels = container.querySelectorAll(PANEL_SEL);
      tabs.forEach(function (t) { t.classList.remove('jp-tab--active'); });
      panels.forEach(function (p) { p.classList.remove('jp-panel--active'); });

      tab.classList.add('jp-tab--active');
      var panel = container.querySelector('.jp-panel[data-jp-panel="' + target + '"]');
      if (panel) panel.classList.add('jp-panel--active');
    });
  }

  /* ---- Jackpot counter: single epoch, one Date.now() per tick ---- */
  try {
    var section = document.querySelector(SECTION_SEL);
    if (!section) return;

    var epochStr = (section.getAttribute('data-jackpot-epoch') || '').trim();
    var epochMs = epochStr ? (Date.parse(epochStr.replace(' ', 'T') + '+03:00') || Date.now()) : Date.now();

    function formatTRY(value) {
      var n = typeof value === 'number' && !isNaN(value) ? value : 0;
      try {
        return n.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20BA';
      } catch (e) {
        var s = n.toFixed(2);
        var i = s.indexOf('.');
        var intPart = i >= 0 ? s.slice(0, i) : s;
        var decPart = i >= 0 ? s.slice(i + 1) : '00';
        return intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + decPart + ' \u20BA';
      }
    }

    var amountEls = section.querySelectorAll(AMOUNT_SEL);
    var items = [];
    for (var i = 0; i < amountEls.length; i++) {
      var el = amountEls[i];
      items.push({
        el: el,
        base: parseFloat(el.getAttribute('data-jackpot-amount') || '0') || 0,
        inc: parseFloat(el.getAttribute('data-jackpot-increment') || '0') || 0
      });
    }

    function tick() {
      var elapsed = Math.max(0, (Date.now() - epochMs) / 1000);
      for (var j = 0; j < items.length; j++) {
        var item = items[j];
        item.el.textContent = formatTRY(item.base + elapsed * item.inc);
      }
    }

    tick();
    // increment > 0 olmasa bile interval başlat (sayaç her zaman çalışır)
    setInterval(tick, 1000);
  } catch (err) {
    if (console && console.warn) console.warn('Jackpot counter error', err);
  }
  }

  // DOMContentLoaded zaten geçtiyse hemen çalıştır
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
