(function () {
  'use strict';

  var mainTabs = document.querySelectorAll('.winners-main-tab');
  var periodTabsWrap = document.querySelector('.winners-period-tabs');
  var periodTabs = document.querySelectorAll('.winners-period-tab');
  var listEl = document.querySelector('.winners-list');
  var slotWinnersWrap = document.querySelector('.slot-winners-wrap');
  var slotJackpotWrap = document.querySelector('.slot-jackpot-wrap');
  var slotHeroTabs = document.querySelector('[data-slot-hero-tabs]');

  var WINNERS_API = (typeof window.__WINNERS_API__ === 'string' && window.__WINNERS_API__.trim())
    ? window.__WINNERS_API__.trim()
    : '/api/v2/winners';
  var Shared = window.BetcoAuthShared || {};
  function apiUrl(path) {
    return Shared.apiUrl ? Shared.apiUrl(path) : path;
  }
  var LIMIT = typeof window.__WINNERS_LIMIT__ === 'number' && window.__WINNERS_LIMIT__ > 0
    ? Math.min(100, Math.floor(window.__WINNERS_LIMIT__))
    : 8;

  var cache = Object.create(null);

  function syncWinnersHeightToJackpot() {
    if (!slotWinnersWrap || !slotJackpotWrap) return;
    var h = slotJackpotWrap.offsetHeight;
    if (h > 0) slotWinnersWrap.style.height = h + 'px';
  }

  if (slotWinnersWrap && slotJackpotWrap && !slotHeroTabs) {
    var syncRaf = null;
    function runSync() {
      if (syncRaf) return;
      syncRaf = requestAnimationFrame(function () { syncRaf = null; syncWinnersHeightToJackpot(); });
    }
    var resizeTimer = null;
    window.addEventListener('resize', function () {
      if (resizeTimer) clearTimeout(resizeTimer);
      resizeTimer = setTimeout(runSync, 150);
    });
    runSync();
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(runSync).observe(slotJackpotWrap);
    }
  }

  if (!listEl) return;

  function getTab() {
    var el = document.querySelector('.winners-main-tab.active');
    return el ? el.getAttribute('data-winners-tab') : 'recent';
  }
  function getPeriod() {
    var el = document.querySelector('.winners-period-tab.active');
    return el ? el.getAttribute('data-period') : 'day';
  }

  var SKELETON_COUNT = 8;

  function skeletonHtml() {
    var h = '';
    for (var i = 0; i < SKELETON_COUNT; i++) {
      h += '<div class="winners-list-item-skeleton" role="presentation">' +
        '<div class="winners-skeleton-icon"></div>' +
        '<div class="winners-skeleton-info">' +
          '<div class="winners-skeleton-line"></div>' +
          '<div class="winners-skeleton-line"></div>' +
        '</div>' +
        '<div class="winners-skeleton-amount"></div></div>';
    }
    return h;
  }

  function renderSkeleton() {
    listEl.classList.add('is-loading');
    listEl.innerHTML = skeletonHtml();
  }

  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function fmt(num) {
    var n = Number(num);
    if (!isFinite(n)) n = 0;
    return n.toLocaleString('tr-TR', { maximumFractionDigits: 0 }) + ' ₺';
  }

  function cacheKey(tab, period) {
    return tab + '|' + period;
  }

  function envelopeOk(json) {
    if (!json || json.data == null) return false;
    if (!Object.prototype.hasOwnProperty.call(json, 'success')) return true;
    var s = json.success;
    return s === true || s === 1 || s === '1' || String(s).toLowerCase() === 'true';
  }

  function winnersFromData(data) {
    if (!data) return [];
    if (Array.isArray(data)) return data;
    if (Array.isArray(data.winners)) return data.winners;
    if (Array.isArray(data.items)) return data.items;
    if (Array.isArray(data.list)) return data.list;
    return [];
  }

  function normalizeRows(winners, tab) {
    if (!Array.isArray(winners)) return [];
    var placeholder = '/assets/games-img/game-img4.svg';
    if (tab === 'top') {
      return winners.map(function (w) {
        var last = w.lastWinAt != null ? w.lastWinAt : w.last_win_at;
        var total = w.totalWinAmount != null ? w.totalWinAmount : w.total_win_amount;
        var player = w.player != null ? w.player : w.user_mask;
        var img = w.gameImageUrl || w.game_image_url || w.cover || w.image || w.game_image || w.image_url || w.thumbnail_url || w.banner;
        var cover = (img && String(img).trim()) ? String(img).trim() : placeholder;
        var gname = w.gameName || w.game_name;
        var prov = w.providerName || w.provider_name || w.provider;
        var game = [gname, prov].filter(Boolean).join(' · ') || (last ? ('Son kazanç: ' + String(last)) : '—');
        return {
          user_mask: player != null ? String(player) : '',
          game_name: game,
          amount: total,
          cover: cover
        };
      });
    }
    return winners.map(function (w) {
      var img = w.gameImageUrl || w.game_image_url || w.cover || w.image || w.game_image || w.image_url || w.thumbnail_url || w.banner;
      var cover = (img && String(img).trim()) ? String(img).trim() : placeholder;
      var gname = w.gameName || w.game_name;
      var prov = w.providerName || w.provider_name || w.provider;
      var game = [gname, prov].filter(Boolean).join(' · ') || (gname || '');
      var amt = w.winAmount != null ? w.winAmount : w.win_amount;
      if (amt == null && w.amount != null) amt = w.amount;
      var player = w.player != null ? w.player : w.user_mask;
      return {
        user_mask: player != null ? String(player) : '',
        game_name: game,
        amount: amt,
        cover: cover
      };
    });
  }

  function render(rows, tab) {
    listEl.classList.remove('is-loading');
    if (!rows || !rows.length) {
      listEl.innerHTML = '<div class="winners-list-empty">Kayıt bulunamadı.</div>';
      return;
    }
    var placeholder = '/assets/games-img/game-img4.svg';
    listEl.innerHTML = rows.map(function (r) {
      var cover = (r.cover && r.cover.trim()) ? r.cover : placeholder;
      return '<div class="winners-list-item" role="listitem">' +
        '<div class="winners-item-icon"><img src="' + esc(cover) + '" alt="' + esc(r.game_name) + '" class="winners-item-cover" loading="lazy" onerror="this.onerror=null;this.src=\'' + esc(placeholder) + '\'"></div>' +
        '<div class="winners-item-info"><span class="winners-item-user">' + esc(r.user_mask) + '</span><span class="winners-item-game">' + esc(r.game_name) + '</span></div>' +
        '<div class="winners-item-amount">' + esc(fmt(r.amount)) + '</div></div>';
    }).join('');
  }

  function load(tab, period, done) {
    if (typeof done !== 'function') done = function () {};
    var key = cacheKey(tab, period);
    if (cache[key]) {
      render(cache[key], tab);
      done();
      return;
    }
    var u = new URL(apiUrl(WINNERS_API), window.location.origin);
    u.searchParams.set('winners_tab', tab);
    u.searchParams.set('winners_period', period);
    u.searchParams.set('limit', String(LIMIT));
    fetch(u.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (res) { return res.text(); })
      .then(function (text) {
        var json = null;
        try {
          json = JSON.parse(String(text || '').replace(/^\uFEFF/, '').trim());
        } catch (e) {
          json = null;
        }
        if (!envelopeOk(json)) {
          cache[key] = [];
          render([], tab);
          done();
          return;
        }
        var raw = winnersFromData(json.data);
        if (!raw.length && json.data && typeof json.data === 'object' && json.data.data != null) {
          raw = winnersFromData(json.data.data);
        }
        cache[key] = normalizeRows(raw, tab);
        render(cache[key], tab);
        done();
      })
      .catch(function () {
        cache[key] = [];
        render([], tab);
        done();
      });
  }

  function switchTo(tab, period) {
    mainTabs.forEach(function (t) {
      var on = t.getAttribute('data-winners-tab') === tab;
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (periodTabsWrap) {
      periodTabsWrap.classList.toggle('winners-period-tabs--hidden', tab === 'recent');
      periodTabsWrap.setAttribute('aria-hidden', tab === 'recent' ? 'true' : 'false');
    }
    periodTabs.forEach(function (t) {
      t.classList.toggle('active', t.getAttribute('data-period') === period);
    });
    var p = tab === 'recent' ? 'day' : period;
    if (!cache[cacheKey(tab, p)]) {
      renderSkeleton();
    }
    load(tab, p);
  }

  mainTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var t = this.getAttribute('data-winners-tab');
      if (this.classList.contains('active')) return;
      switchTo(t, t === 'top' ? getPeriod() : 'day');
    });
  });
  periodTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var p = this.getAttribute('data-period');
      if (this.classList.contains('active')) return;
      switchTo(getTab(), p);
    });
  });

  var urlParams = new URLSearchParams(window.location.search);
  var urlTab = urlParams.get('winners_tab');
  var urlPeriod = urlParams.get('winners_period');
  var periodsOk = ['day', 'week', 'month', 'all'];
  var initialTab = urlTab === 'top' ? 'top' : 'recent';
  var initialPeriod = periodsOk.indexOf(urlPeriod) >= 0 ? urlPeriod : 'day';

  if (urlParams.has('winners_tab') || urlParams.has('winners_period')) {
    var cleanUrl = new URL(window.location.href);
    cleanUrl.searchParams.delete('winners_tab');
    cleanUrl.searchParams.delete('winners_period');
    var qs = cleanUrl.searchParams.toString();
    var nextPath = cleanUrl.pathname + (qs ? '?' + qs : '') + cleanUrl.hash;
    if (nextPath !== window.location.pathname + window.location.search + window.location.hash) {
      window.history.replaceState({}, '', nextPath);
    }
  }

  if (initialTab === 'top') {
    mainTabs.forEach(function (t) {
      var on = t.getAttribute('data-winners-tab') === 'top';
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (periodTabsWrap) {
      periodTabsWrap.classList.remove('winners-period-tabs--hidden');
      periodTabsWrap.setAttribute('aria-hidden', 'false');
    }
    periodTabs.forEach(function (t) {
      t.classList.toggle('active', t.getAttribute('data-period') === initialPeriod);
    });
  }

  renderSkeleton();
  load(initialTab, initialTab === 'recent' ? 'day' : initialPeriod);
})();
