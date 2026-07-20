(function () {
  'use strict';

  function getPanel() { return document.getElementById('mprofilePanel'); }
  function getOverlay() { return document.getElementById('mprofileOverlay'); }
  var Shared = window.BetcoAuthShared || {};

  var isOpen = false;
  var balanceMethodsLoaded = false;
  var withdrawMethodsLoaded = false;
  var balanceInfoLoaded = false;
  var transactionHistoryLoaded = false;
  var withdrawStatusLoaded = false;
  var betHistoryLoaded = false;
  var betHistoryRows = [];
  var activeBetHistoryPage = 'bets';
  var betHistoryFormFilterActive = false;
  var casinoHistoryLoaded = false;
  var casinoHistoryRows = [];
  var activeCasinoHistoryPage = 'bets';
  var casinoHistoryFormFilterActive = false;
  var transactionHistoryRows = [];
  var balanceMethodStore = { deposit: [], withdraw: [] };
  var activePaymentModal = null;
  var paymentModalSubmitting = false;

  function apiUrl(path) {
    return Shared.apiUrl ? Shared.apiUrl(path) : path;
  }

  function memberAuthHeaders(extra) {
    if (Shared.memberAuthHeaders) return Shared.memberAuthHeaders(extra);
    var headers = extra || {};
    var csrf = (window.__CSRF_TOKEN__ || '').trim();
    if (csrf) headers['X-CSRF-Token'] = csrf;
    return headers;
  }

  function requestedProfileSection() {
    var params;
    try {
      params = new URLSearchParams(window.location.search || '');
    } catch (e) {
      return '';
    }
    if (params.get('profile') !== 'open' || params.get('account') !== 'profile') return '';
    var page = params.get('page') || '';
    return ['details', 'change-password', 'two-factor-authentication', 'timeout-limits'].indexOf(page) !== -1 ? page : '';
  }

  function requestedBalanceSection() {
    var params;
    try {
      params = new URLSearchParams(window.location.search || '');
    } catch (e) {
      return '';
    }
    if (params.get('profile') !== 'open' || params.get('account') !== 'balance') return '';
    var page = params.get('page') || 'deposit';
    return ['deposit', 'withdraw', 'history', 'info', 'withdraws'].indexOf(page) !== -1 ? page : 'deposit';
  }

  function requestedBetHistorySection() {
    var pathname = window.location.pathname || '';
    if (pathname.replace(/\/+$/, '') === '/profile/bet-history') return 'bets';
    var params;
    try {
      params = new URLSearchParams(window.location.search || '');
    } catch (e) {
      return '';
    }
    if (params.get('profile') !== 'open') return '';
    if (params.get('account') === 'bet' && params.get('page') === 'history') return 'bets';
    if (params.get('account') !== 'history') return '';
    var page = params.get('page') || 'bets';
    return ['bets', 'open-bets', 'cashed-out', 'won', 'lost', 'returned', 'won-return', 'lost-return'].indexOf(page) !== -1 ? page : 'bets';
  }

  function requestedCasinoHistorySection() {
    var pathname = window.location.pathname || '';
    if (pathname.replace(/\/+$/, '') === '/profile/casino-history') return 'bets';
    var params;
    try {
      params = new URLSearchParams(window.location.search || '');
    } catch (e) {
      return '';
    }
    if (params.get('profile') !== 'open') return '';
    if (params.get('account') === 'bet' && params.get('page') === 'casino-history') return params.get('filter') || 'bets';
    if (params.get('account') === 'casino-history') return params.get('page') || 'bets';
    return '';
  }

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
    var casinoHistorySection = requestedCasinoHistorySection();
    if (casinoHistorySection) {
      showCasinoHistoryPage(panel, casinoHistorySection);
    } else {
      var betHistorySection = requestedBetHistorySection();
      if (betHistorySection) {
        showBetHistoryPage(panel, betHistorySection);
      } else {
        var balanceSection = requestedBalanceSection();
        if (balanceSection) {
          showBalancePage(panel, balanceSection);
        } else {
          var section = requestedProfileSection();
          if (section) showProfileDetails(panel, section);
        }
      }
    }
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
    showProfileMenu(panel);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function appendQuery(path, query) {
    return path + (path.indexOf('?') === -1 ? '?' : '&') + query;
  }

  function moneyText(value) {
    var num = Number(value);
    if (!isFinite(num)) return '—';
    try {
      return new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num) + ' ₺';
    } catch (e) {
      return num.toFixed(2) + ' ₺';
    }
  }

  function formatDateTime(value) {
    var raw = String(value || '').trim();
    if (!raw) return '—';
    var date = new Date(raw.replace(' ', 'T'));
    if (!isNaN(date.getTime())) {
      try {
        return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
      } catch (e) {}
    }
    return raw;
  }

  function sportsbookTypeText(type) {
    type = String(type || '').toLowerCase();
    if (type === 'win') return 'KAZANÇ';
    if (type === 'cancel' || type === 'refund') return 'İADE';
    if (type === 'adjustment') return 'DÜZELTME';
    return 'BAHİS';
  }

  function sportsbookStatusText(row) {
    var type = String(row && row.txn_type || '').toLowerCase();
    if (type === 'cancel' || type === 'refund') return 'İADE EDİLDİ';
    return row && row.is_finished ? 'TAMAMLANDI' : 'AÇIK';
  }

  function casinoStatusText(status) {
    status = String(status || '').toLowerCase();
    if (status === 'pending') return 'AÇIK';
    if (status === 'cancel' || status === 'cancelled' || status === 'refund') return 'İADE EDİLDİ';
    if (status === 'failed' || status === 'error') return 'BAŞARISIZ';
    return 'TAMAMLANDI';
  }

  function normalizeHistoryRow(row, source) {
    row = row && typeof row === 'object' ? row : {};
    source = source || 'sports';
    var type = String(row.txn_type || row.txnType || row.type || 'bet').toLowerCase();
    var amount = Math.abs(Number(row.amount != null ? row.amount : 0));
    if (source === 'casino') {
      var betAmount = Math.abs(Number(row.bet_amount != null ? row.bet_amount : row.betAmount || 0));
      var winAmount = Math.abs(Number(row.win_amount != null ? row.win_amount : row.winAmount || 0));
      amount = type === 'win' ? winAmount : (type === 'cancel' || type === 'refund' ? (winAmount || betAmount) : betAmount);
    }
    return Object.assign({}, row, {
      __history_source: source,
      __type: type,
      __amount: amount,
      __id: row.wager_id || row.txn_code || row.transaction_id || row.transactionId || row.provider_txn_id || row.providerTxnId || row.history_id || row.id || '',
      __round: row.round_id || row.roundId || '',
      __title: source === 'casino'
        ? (row.game_name || row.gameName || row.game_id || row.gameId || 'Casino Oyunu')
        : (row.sport_name || row.game_name || row.game_code || 'Spor Kuponu'),
      __provider: source === 'casino'
        ? (row.provider_name || row.providerName || row.provider_code || row.providerCode || 'casino')
        : (row.vendor_code || row.provider_name || 'sports-betby'),
      __created_at: row.created_at || row.createdAt || '',
      __is_finished: source === 'casino' ? String(row.status || '').toLowerCase() !== 'pending' : !!row.is_finished
    });
  }

  function limitText(value) {
    var num = Number(value);
    if (!isFinite(num)) return '—';
    try {
      return new Intl.NumberFormat('tr-TR', { maximumFractionDigits: 0 }).format(num) + ' ₺';
    } catch (e) {
      return String(Math.round(num)) + ' ₺';
    }
  }

  function dateText(value) {
    if (!value) return '—';
    var date = new Date(String(value).replace(' ', 'T'));
    if (isNaN(date.getTime())) return String(value);
    try {
      return new Intl.DateTimeFormat('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(date);
    } catch (e) {
      return String(value);
    }
  }

  function statusText(status) {
    var key = String(status || '').toLowerCase();
    var map = { pending: 'Beklemede', processing: 'İşleniyor', approved: 'Onaylandı', confirmed: 'Onaylandı', completed: 'Tamamlandı', rejected: 'Reddedildi', failed: 'Başarısız', cancelled: 'İptal' };
    return map[key] || status || '—';
  }

  /** Header'daki ana bakiyeyi panele yansıt. */
  function syncBalance() {
    var target = document.querySelector('[data-balance-target="mprofileMain"]');
    var source = document.getElementById('headerBalanceMain')
      || document.querySelector('[data-balance-target="headerBalanceMain"]');
    if (target && source) {
      target.textContent = source.textContent.trim() || '0';
    }
    if (typeof window.__syncProfileSidebarBalancesFromHeaderDom === 'function') {
      window.__syncProfileSidebarBalancesFromHeaderDom();
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

  function showProfileMenu(panel) {
    panel = panel || getPanel();
    if (!panel) return;
    panel.classList.remove('mprofile-detail-active');
    panel.classList.remove('mprofile-balance-active');
    panel.classList.remove('mprofile-bet-history-active');
    panel.classList.remove('mprofile-casino-history-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'true');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'true');
    var betHistory = panel.querySelector('[data-mprofile-view="bet-history"]');
    if (betHistory) betHistory.setAttribute('aria-hidden', 'true');
    var casinoHistory = panel.querySelector('[data-mprofile-view="casino-history"]');
    if (casinoHistory) casinoHistory.setAttribute('aria-hidden', 'true');
  }

  function showProfileDetails(panel, sectionName) {
    panel = panel || getPanel();
    if (!panel) return;
    sectionName = sectionName || 'details';
    panel.classList.add('mprofile-detail-active');
    panel.classList.remove('mprofile-balance-active');
    panel.classList.remove('mprofile-bet-history-active');
    panel.classList.remove('mprofile-casino-history-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'false');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'true');
    var betHistory = panel.querySelector('[data-mprofile-view="bet-history"]');
    if (betHistory) betHistory.setAttribute('aria-hidden', 'true');
    var casinoHistory = panel.querySelector('[data-mprofile-view="casino-history"]');
    if (casinoHistory) casinoHistory.setAttribute('aria-hidden', 'true');
    panel.querySelectorAll('[data-mprofile-section]').forEach(function (section) {
      var isActive = section.getAttribute('data-mprofile-section') === sectionName;
      section.hidden = !isActive;
    });
    panel.querySelectorAll('[data-mprofile-tab]').forEach(function (tab) {
      tab.classList.toggle('active', tab.getAttribute('data-mprofile-tab') === sectionName);
    });
  }

  function balanceCategory(method) {
    var methodId = String((method && method.method_id) || '').toLowerCase();
    var type = String((method && method.type) || '').toLowerCase();
    var name = String((method && method.name) || '').toLowerCase();
    if (methodId.indexOf('qr') !== -1 || name.indexOf('qr') !== -1) return 'qr';
    if (type === 'crypto' || methodId.indexOf('crypto') !== -1 || name.indexOf('kripto') !== -1 || name.indexOf('bitcoin') !== -1 || name.indexOf('tether') !== -1 || name.indexOf('tron') !== -1) return 'crypto';
    if (type === 'card' || methodId.indexOf('card') !== -1 || name.indexOf('kredi') !== -1) return 'card';
    return 'bank';
  }

  function renderBalanceMethods(gridId, methods, kind) {
    var grid = document.getElementById(gridId);
    if (!grid) return;
    balanceMethodStore[kind] = Array.isArray(methods) ? methods.slice() : [];
    if (!methods || !methods.length) {
      grid.innerHTML = '<p class="dw-methods-empty" role="status">Şu an ' + (kind === 'withdraw' ? 'çekim' : 'para yatırma') + ' için listelenen yöntem bulunmuyor.</p>';
      return;
    }
    grid.innerHTML = methods.map(function (method, index) {
      var logo = method.logo_url && String(method.logo_url).trim() ? String(method.logo_url).trim() : '';
      var methodId = String(method.method_id || method.id || '').trim();
      var category = balanceCategory(method);
      var cls = (kind === 'withdraw' ? 'withdraw_' : 'deposit_') + (methodId || 'method').toLowerCase().replace(/[^a-z0-9_-]+/g, '');
      var attr = kind === 'withdraw' ? 'data-mbalance-withdraw-method' : 'data-mbalance-method';
      return '<button type="button" class="m-nav-items-list-item-bc ' + escapeHtml(cls) + '" ' + attr + ' data-category="' + escapeHtml(category) + '" data-mbalance-payment-kind="' + escapeHtml(kind) + '" data-mbalance-payment-index="' + escapeHtml(index) + '"><div class="nav-ico-w-row-bc">' +
        (logo ? '<img alt="" loading="lazy" decoding="async" src="' + escapeHtml(logo) + '" class="payment-logo">' : '<span class="payment-logo payment-logo--text">' + escapeHtml(method.name || methodId || 'Ödeme') + '</span>') +
        '</div></button>';
    }).join('');
  }

  function renderDepositMethods(methods) {
    renderBalanceMethods('mprofileDepositMethods', methods, 'deposit');
  }

  function renderWithdrawMethods(methods) {
    renderBalanceMethods('mprofileWithdrawMethods', methods, 'withdraw');
  }

  function extractWithdrawPaymentMethods(data) {
    if (!data || typeof data !== 'object') return [];
    if (Array.isArray(data.methods)) return data.methods;
    if (data.megapayz_withdraw_form && Array.isArray(data.megapayz_withdraw_form.methods)) return data.megapayz_withdraw_form.methods;
    if (data.create_withdraw && Array.isArray(data.create_withdraw.methods)) return data.create_withdraw.methods;
    return [];
  }

  function extractHistoryRows(env, kind) {
    var data = env && env.data != null ? env.data : env;
    if (Array.isArray(data)) return data;
    if (!data || typeof data !== 'object') return [];
    var keys = kind === 'withdraw'
      ? ['withdrawals', 'withdraws', 'items', 'rows', 'history', 'transactions', 'data']
      : ['deposits', 'items', 'rows', 'history', 'transactions', 'data'];
    for (var i = 0; i < keys.length; i++) {
      if (Array.isArray(data[keys[i]])) return data[keys[i]];
    }
    return [];
  }

  function normalizeTransactionHistoryRow(row, kind) {
    row = row && typeof row === 'object' ? row : {};
    return {
      kind: kind,
      id: row.id || row.transaction_id || row.transactionId || row.trx || row.referenceCode || '',
      method: row.method || row.method_name || row.payment_method || row.system || '—',
      provider: row.provider || row.provider_name || '',
      reference: row.referenceCode || row.reference_code || row.trx || row.transaction_id || '—',
      amount: row.amount,
      fee: row.fee,
      status: row.status || '',
      createdAt: row.createdAt || row.created_at || row.date || row.created || ''
    };
  }

  function buildHistoryCard(row, showKind) {
    var kindLabel = row.kind === 'withdraw' ? 'ÇEKİM' : 'YATIRIM';
    var status = String(row.status || '').toLowerCase();
    return '<article class="mprofile-history-card" data-history-kind="' + escapeHtml(row.kind) + '">' +
      '<div class="mprofile-history-card-head"><strong>' + escapeHtml(showKind ? kindLabel : (row.method || kindLabel)) + '</strong><span class="mprofile-status-badge mprofile-status-' + escapeHtml(status) + '">' + escapeHtml(statusText(status)) + '</span></div>' +
      '<dl><div><dt>Tarih Ve İD</dt><dd>' + escapeHtml(dateText(row.createdAt)) + (row.id ? ' #' + escapeHtml(row.id) : '') + '</dd></div>' +
      '<div><dt>Sistem</dt><dd>' + escapeHtml(row.provider || row.method || '—') + '</dd></div>' +
      '<div><dt>Referans</dt><dd>' + escapeHtml(row.reference || '—') + '</dd></div>' +
      '<div><dt>Tutar</dt><dd>' + escapeHtml(moneyText(row.amount)) + '</dd></div>' +
      (row.fee != null ? '<div><dt>Ücret</dt><dd>' + escapeHtml(moneyText(row.fee)) + '</dd></div>' : '') + '</dl></article>';
  }

  function renderTransactionHistory(filter) {
    var list = document.getElementById('mprofileTransactionHistory');
    if (!list) return;
    filter = filter || 'all';
    var rows = transactionHistoryRows.filter(function (row) { return filter === 'all' || row.kind === filter; });
    if (!rows.length) {
      list.innerHTML = '<p class="dw-methods-empty" role="status">Kayıt bulunamadı.</p>';
      return;
    }
    list.innerHTML = rows.map(function (row) { return buildHistoryCard(row, true); }).join('');
  }

  function loadTransactionHistory() {
    if (transactionHistoryLoaded) return;
    var list = document.getElementById('mprofileTransactionHistory');
    if (!list) return;
    transactionHistoryLoaded = true;
    var query = 'page=1&per_page=20';
    Promise.all([
      fetch(appendQuery(apiUrl('/api/v2/deposit-history'), query), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) }).then(function (res) { return res.json(); }),
      fetch(appendQuery(apiUrl('/api/v2/withdraw-history'), query), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) }).then(function (res) { return res.json(); })
    ]).then(function (packs) {
      var deposits = extractHistoryRows(packs[0], 'deposit').map(function (row) { return normalizeTransactionHistoryRow(row, 'deposit'); });
      var withdraws = extractHistoryRows(packs[1], 'withdraw').map(function (row) { return normalizeTransactionHistoryRow(row, 'withdraw'); });
      transactionHistoryRows = deposits.concat(withdraws).sort(function (a, b) { return new Date(String(b.createdAt).replace(' ', 'T')).getTime() - new Date(String(a.createdAt).replace(' ', 'T')).getTime(); });
      renderTransactionHistory('all');
    }).catch(function () {
      transactionHistoryLoaded = false;
      list.innerHTML = '<p class="dw-methods-empty" role="status">İşlem geçmişi yüklenemedi.</p>';
    });
  }

  function renderInfoRows(gridId, methods, emptyText) {
    var grid = document.getElementById(gridId);
    if (!grid) return;
    if (!methods || !methods.length) {
      grid.innerHTML = '<p class="dw-methods-empty" role="status">' + escapeHtml(emptyText) + '</p>';
      return;
    }
    grid.innerHTML = methods.map(function (method) {
      var methodId = String(method.method_id || method.id || method.name || 'method').replace(/[^A-Za-z0-9_-]+/g, '');
      var logo = method.logo_url && String(method.logo_url).trim() ? String(method.logo_url).trim() : '';
      var processing = method.processing_time != null && String(method.processing_time).trim() ? String(method.processing_time).trim() : 'Anlık';
      var min = method.min_amount != null ? limitText(method.min_amount) : '—';
      var max = method.max_amount != null ? limitText(method.max_amount) : '—';
      return '<div class="description-c-row-bc ' + escapeHtml(methodId || 'method') + '"><div class="description-c-row-column-bc pay-logo">' +
        (logo ? '<img alt="" loading="lazy" decoding="async" src="' + escapeHtml(logo) + '">' : '<span class="payment-logo payment-logo--text">' + escapeHtml(method.name || method.method_id || 'Ödeme') + '</span>') +
        '</div><div class="description-c-row-column-bc texts"><div class="description-c-row-c-title-bc description_payment-title"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis">Ücret: Ücretsiz</span></div><div class="description-c-r-c-t-column-bc"><span class="description-instant ellipsis">' + escapeHtml(processing) + '</span></div></div><div class="description-card-info"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Min.">Min.</span><span class="description-value ellipsis" title="' + escapeHtml(min) + '">' + escapeHtml(min) + '</span></div><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Maks.">Maks.</span><span class="description-value ellipsis" title="' + escapeHtml(max) + '">' + escapeHtml(max) + '</span></div></div></div></div>';
    }).join('');
  }

  function showBalanceInfoTab(panel, tabName) {
    panel = panel || getPanel();
    tabName = tabName === 'withdraw' ? 'withdraw' : 'deposit';
    if (!panel) return;
    panel.querySelectorAll('[data-mbalance-info-tab]').forEach(function (item) {
      item.classList.toggle('active', item.getAttribute('data-mbalance-info-tab') === tabName);
    });
    panel.querySelectorAll('[data-mbalance-info-list]').forEach(function (item) {
      item.hidden = item.getAttribute('data-mbalance-info-list') !== tabName;
    });
  }

  function loadBalanceInfo() {
    if (balanceInfoLoaded) return;
    balanceInfoLoaded = true;
    fetch(apiUrl('/api/v2/payment-methods'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        var methods = env && env.success && env.data && Array.isArray(env.data.payment_methods) ? env.data.payment_methods : [];
        renderInfoRows('mprofileDepositInfo', methods.filter(function (method) { return method && method.deposit_enabled; }), 'Listelenecek yatırım bilgisi bulunmuyor.');
        var withdrawMethods = methods.filter(function (method) { return method && method.withdrawal_enabled; });
        if (withdrawMethods.length) {
          renderInfoRows('mprofileWithdrawInfo', withdrawMethods, 'Listelenecek çekim bilgisi bulunmuyor.');
          return null;
        }
        return fetch(apiUrl('/api/v2/withdraw-payment'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
          .then(function (withdrawRes) { return withdrawRes.json(); })
          .then(function (withdrawEnv) { renderInfoRows('mprofileWithdrawInfo', withdrawEnv && withdrawEnv.success && withdrawEnv.data ? extractWithdrawPaymentMethods(withdrawEnv.data) : [], 'Listelenecek çekim bilgisi bulunmuyor.'); });
      })
      .catch(function () {
        balanceInfoLoaded = false;
        renderInfoRows('mprofileDepositInfo', [], 'Yatırım bilgileri yüklenemedi.');
        renderInfoRows('mprofileWithdrawInfo', [], 'Çekim bilgileri yüklenemedi.');
      });
  }

  function loadWithdrawStatus() {
    if (withdrawStatusLoaded) return;
    var list = document.getElementById('mprofileWithdrawStatus');
    if (!list) return;
    withdrawStatusLoaded = true;
    fetch(appendQuery(apiUrl('/api/v2/withdraw-history'), 'page=1&per_page=20'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        var rows = extractHistoryRows(env, 'withdraw').map(function (row) { return normalizeTransactionHistoryRow(row, 'withdraw'); });
        if (!rows.length) {
          list.innerHTML = '<p class="dw-methods-empty" role="status">Para Çekme Bilgisi Yok</p>';
          return;
        }
        list.innerHTML = rows.map(function (row) { return buildHistoryCard(row, false); }).join('');
      })
      .catch(function () {
        withdrawStatusLoaded = false;
        list.innerHTML = '<p class="dw-methods-empty" role="status">Para çekme durumu yüklenemedi.</p>';
      });
  }

  function loadBalanceMethods() {
    if (balanceMethodsLoaded) return;
    var grid = document.getElementById('mprofileDepositMethods');
    if (!grid) return;
    balanceMethodsLoaded = true;
    fetch(apiUrl('/api/v2/payment-methods'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        var methods = env && env.success && env.data && Array.isArray(env.data.payment_methods) ? env.data.payment_methods : [];
        renderDepositMethods(methods.filter(function (method) { return method && method.deposit_enabled; }));
      })
      .catch(function () {
        balanceMethodsLoaded = false;
        grid.innerHTML = '<p class="dw-methods-empty" role="status">Ödeme yöntemleri yüklenemedi.</p>';
      });
  }

  function loadWithdrawMethods() {
    if (withdrawMethodsLoaded) return;
    var grid = document.getElementById('mprofileWithdrawMethods');
    if (!grid) return;
    withdrawMethodsLoaded = true;
    fetch(apiUrl('/api/v2/payment-methods'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        var methods = env && env.success && env.data && Array.isArray(env.data.payment_methods) ? env.data.payment_methods : [];
        var withdrawMethods = methods.filter(function (method) { return method && method.withdrawal_enabled; });
        if (withdrawMethods.length) {
          renderWithdrawMethods(withdrawMethods);
          return null;
        }
        return fetch(apiUrl('/api/v2/withdraw-payment'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
          .then(function (withdrawRes) { return withdrawRes.json(); })
          .then(function (withdrawEnv) {
            var fallback = withdrawEnv && withdrawEnv.success && withdrawEnv.data ? extractWithdrawPaymentMethods(withdrawEnv.data) : [];
            renderWithdrawMethods(fallback);
          });
      })
      .catch(function () {
        withdrawMethodsLoaded = false;
        grid.innerHTML = '<p class="dw-methods-empty" role="status">Çekim yöntemleri yüklenemedi.</p>';
      });
  }

  function methodProvider(method) {
    if (method && method.provider && method.provider.code) return String(method.provider.code);
    return 'megapayz';
  }

  function methodId(method) {
    return String((method && (method.method_id || method.id)) || '').trim();
  }

  function methodName(method) {
    return String((method && (method.name || method.method_id || method.id)) || 'Ödeme').trim();
  }

  function isCryptoMethod(method) {
    var text = [
      balanceCategory(method),
      method && method.type,
      methodId(method),
      methodName(method)
    ].join(' ').toLowerCase();
    return text.indexOf('crypto') !== -1 || text.indexOf('kripto') !== -1 || text.indexOf('bitcoin') !== -1 || text.indexOf('tether') !== -1 || text.indexOf('tron') !== -1 || text.indexOf('usdt') !== -1 || text.indexOf('btc') !== -1;
  }

  function paymentDisplayName(method) {
    return methodName(method);
  }

  function methodLimit(method, key, fallback) {
    var value = method && method[key] != null ? Number(method[key]) : NaN;
    return isFinite(value) ? value : fallback;
  }

  function paymentSiteName() {
    var panel = getPanel();
    return String(window.__mprofileSiteName || (panel && panel.getAttribute('data-site-name')) || document.documentElement.getAttribute('data-site-name') || 'VegasRoyalSpin').trim() || 'VegasRoyalSpin';
  }

  function setPaymentModalMessage(type, text) {
    var message = document.querySelector('#mprofilePaymentModal [data-mprofile-payment-message]');
    if (!message) return;
    message.textContent = text || '';
    message.classList.toggle('is-error', type === 'error');
    message.classList.toggle('is-success', type === 'success');
  }

  function syncPaymentSubmitState() {
    var amount = document.getElementById('mprofilePaymentAmount');
    var submit = document.querySelector('#mprofilePaymentModal .mprofile-payment-submit');
    if (submit && !paymentModalSubmitting) submit.disabled = !(amount && String(amount.value || '').trim());
  }

  function closePaymentModal() {
    var modal = document.getElementById('mprofilePaymentModal');
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    modal.style.transform = 'translateY(100%)';
    modal.style.opacity = '0';
    activePaymentModal = null;
    paymentModalSubmitting = false;
  }

  function paymentMethodClass(method) {
    return paymentDisplayName(method).replace(/[^A-Za-z0-9_-]+/g, '') || methodId(method) || 'payment';
  }

  function paymentMethodLogo(method) {
    return String((method && (method.logo_url || method.logo)) || '');
  }

  function withdrawNetworkField(method) {
    var fields = method && Array.isArray(method.input_fields) ? method.input_fields : [];
    return fields.find(function (field) { return field && field.field === 'select' && (field.name === 'bank_id' || field.name === 'crypto_network'); }) || null;
  }

  function withdrawNetworkOptions(method) {
    var field = withdrawNetworkField(method);
    var options = field && Array.isArray(field.options) ? field.options : [];
    options = options.map(function (option) { return [String(option.value || ''), String(option.label || option.value || '')]; }).filter(function (option) { return option[0] !== '' && option[1] !== ''; });
    return options.length ? options : [
      ['65bd7bba964700005d002ae1', 'Bitcoin'],
      ['65bd7bc1964700005d002ae2', 'Litecoin'],
      ['65bd7bd5964700005d002ae4', 'USDT TRC20']
    ];
  }

  function withdrawNetworkLabel(method) {
    var field = withdrawNetworkField(method);
    return String((field && field.label) || 'Banka');
  }

  function withdrawExtraFieldsHtml(method) {
    var id = methodId(method).toLowerCase();
    if (id.indexOf('bank') !== -1) {
      return '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" id="mprofilePaymentAccount" name="account_number" step="0" value="" maxlength="26" autocomplete="off"><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">IBAN</span></label></div></div>';
    }
    if (isCryptoMethod(method)) {
      var networkOptions = withdrawNetworkOptions(method);
      var firstNetwork = networkOptions[0];
      return '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc select has-icon valid filled mprofile-crypto-select" data-mprofile-crypto-open><label class="form-control-label-bc inputs"><input type="hidden" name="bank_id" id="mprofilePaymentNetwork" value="' + escapeHtml(firstNetwork[0]) + '"><button type="button" class="form-control-select-bc active mprofile-crypto-select-button"><span id="mprofilePaymentCryptoLabel">' + escapeHtml(firstNetwork[1]) + '</span></button><i class="form-control-icon-bc bc-i-small-arrow-down"></i><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">' + escapeHtml(withdrawNetworkLabel(method)) + '</span></label></div></div><div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" id="mprofilePaymentAccount" name="account_number" step="0" value="" autocomplete="off"><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">address</span></label></div></div>';
    }
    return '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default filled"><label class="form-control-label-bc inputs"><input type="text" class="form-control-input-bc" id="mprofilePaymentAccount" name="account_number" step="0" value="" autocomplete="off"><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">address</span></label></div></div>';
  }

  function cryptoPopupHtml(options) {
    options = Array.isArray(options) && options.length ? options : withdrawNetworkOptions(null);
    return '<div id="mprofileCryptoPopup" class="popup-inner-bc mprofile-crypto-popup" aria-hidden="true" hidden><div class="status-popup-content-w-bc"><div><div class="multi-select-bc multi-select-popup"><div class="form-control-bc"><input class="form-control-input-bc" type="text" placeholder="Arama Kripto" value="" id="mprofileCryptoSearch"><i class="ss-icon-bc bc-i-search"></i><div class="multi-select-label-bc" data-scroll-lock-scrollable>' +
      options.map(function (option, index) { return '<label class="checkbox-control-content-bc ' + (index === 0 ? 'active ' : '') + '" data-option-value="' + escapeHtml(option[0]) + '" data-option-label="' + escapeHtml(option[1]) + '"><p class="checkbox-control-text-bc ellipsis" style="pointer-events: none;">' + escapeHtml(option[1]) + '</p></label>'; }).join('') +
      '</div></div></div></div></div></div>';
  }

  function depositExtraFieldsHtml(method) {
    return '';
  }

  function paymentModalHtml(kind, method) {
    var min = methodLimit(method, 'min_amount', 0);
    var max = methodLimit(method, 'max_amount', 999999);
    var name = paymentDisplayName(method);
    var logo = paymentMethodLogo(method);
    var methodClass = paymentMethodClass(method);
    var submitText = kind === 'withdraw' ? 'ÇEKİM YAP' : 'PARA YATIR';
    var extraFields = kind === 'withdraw' ? withdrawExtraFieldsHtml(method) : depositExtraFieldsHtml(method);
    return '<div class="payment-info-bc" tabindex="-1"><div class="payment-info-content">' +
      '<div class="description-c-row-bc ' + escapeHtml(methodClass) + '"><div class="description-c-row-column-bc pay-logo">' + (logo ? '<img alt="" loading="lazy" decoding="async" src="' + escapeHtml(logo) + '">' : '<span class="payment-logo payment-logo--text">' + escapeHtml(name) + '</span>') + '</div><div class="description-c-row-column-bc texts"><div class="description-c-row-c-title-bc description_payment-title"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis">Ücret: Ücretsiz</span></div><div class="description-c-r-c-t-column-bc"><span class="description-instant ellipsis">' + escapeHtml(method.processing_time || 'Anlık') + '</span></div></div><div class="description-card-info"><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Min.">Min.</span><span class="description-value ellipsis" title="' + escapeHtml(limitText(min)) + '">' + escapeHtml(limitText(min)) + '</span></div><div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Maks.">Maks.</span><span class="description-value ellipsis" title="' + escapeHtml(limitText(max)) + '">' + escapeHtml(limitText(max)) + '</span></div></div></div></div>' +
      '<div class="expandableContentWrapper"><div class="expandableContentData ' + escapeHtml(methodClass) + ' payment-content not-expandable" data-scroll-lock-scrollable><div class="container"><p>' + escapeHtml(paymentSiteName()) + ' Ailesine hoş geldiniz. İyi eğlenceler, bol şanslar dileriz. ' + (kind === 'withdraw' ? 'Para çekmek' : 'Para yatırmak') + ' için lütfen aşağıdaki tüm gerekli alanları doldurun. Minimum tutar altı yatırımlar &quot;İADE EDİLMEZ&quot; lütfen kurallara uygun yatırım yapınız.</p></div></div></div>' +
        '<div class="withdraw-form-l-bc"><form id="mprofilePaymentForm"><div id="screenArea">' + extraFields + '<div class="u-i-p-control-item-holder-bc"><div class="form-control-bc default"><label class="form-control-label-bc inputs"><input type="text" inputmode="decimal" class="form-control-input-bc" id="mprofilePaymentAmount" name="amount" step="0" value="" autocomplete="off"><i class="form-control-input-stroke-bc"></i><span class="form-control-title-bc ellipsis">Tutar</span></label></div></div><div class="mprofile-form-message" data-mprofile-payment-message role="status" aria-live="polite"></div><div class="u-i-p-c-footer-bc"><button class="btn a-color ' + (kind === 'withdraw' ? 'withdraw' : 'deposit') + ' mprofile-payment-submit" type="submit" title="' + escapeHtml(submitText) + '" disabled><span>' + escapeHtml(submitText) + '</span></button></div></div></form></div>' + (kind === 'withdraw' && isCryptoMethod(method) ? cryptoPopupHtml(withdrawNetworkOptions(method)) : '') +
      '</div></div>';
  }

  function openPaymentModal(kind, method) {
    var modal = document.getElementById('mprofilePaymentModal');
    var title = document.getElementById('mprofilePaymentModalTitle');
    var content = document.getElementById('mprofilePaymentModalContent');
    if (!modal || !content || !method) return;
    kind = kind === 'withdraw' ? 'withdraw' : 'deposit';
    activePaymentModal = { kind: kind, method: method };
    if (title) title.textContent = paymentDisplayName(method);
    content.innerHTML = paymentModalHtml(kind, method);
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    modal.style.transform = 'translateY(0px)';
    modal.style.opacity = '1';
    var amount = document.getElementById('mprofilePaymentAmount');
    if (amount) amount.focus({ preventScroll: true });
  }

  function openCryptoPopup() {
    var popup = document.getElementById('mprofileCryptoPopup');
    if (!popup) return;
    popup.hidden = false;
    popup.setAttribute('aria-hidden', 'false');
    var search = document.getElementById('mprofileCryptoSearch');
    if (search) search.focus({ preventScroll: true });
  }

  function closeCryptoPopup() {
    var popup = document.getElementById('mprofileCryptoPopup');
    if (!popup) return;
    popup.hidden = true;
    popup.setAttribute('aria-hidden', 'true');
  }

  function selectCryptoOption(option) {
    if (!option) return;
    var value = option.getAttribute('data-option-value') || '';
    var label = option.getAttribute('data-option-label') || option.textContent.trim();
    var input = document.getElementById('mprofilePaymentCryptoType');
    var networkInput = document.getElementById('mprofilePaymentNetwork');
    var labelEl = document.getElementById('mprofilePaymentCryptoLabel');
    if (input) input.value = value;
    if (networkInput) networkInput.value = value;
    if (labelEl) labelEl.textContent = label.toLowerCase() === 'tron' ? 'tron' : label;
    document.querySelectorAll('#mprofileCryptoPopup .checkbox-control-content-bc').forEach(function (item) {
      item.classList.toggle('active', item === option);
    });
    closeCryptoPopup();
  }

  function filterCryptoOptions(value) {
    var needle = String(value || '').trim().toLowerCase();
    document.querySelectorAll('#mprofileCryptoPopup .checkbox-control-content-bc').forEach(function (item) {
      var text = String(item.getAttribute('data-option-label') || item.textContent || '').toLowerCase();
      item.hidden = needle !== '' && text.indexOf(needle) === -1;
    });
  }

  function submitPaymentModal() {
    if (paymentModalSubmitting || !activePaymentModal) return;
    var method = activePaymentModal.method;
    var kind = activePaymentModal.kind;
    var amountInput = document.getElementById('mprofilePaymentAmount');
    var amount = amountInput ? Number(amountInput.value) : NaN;
    var min = methodLimit(method, 'min_amount', 0);
    var max = methodLimit(method, 'max_amount', 999999);
    if (!isFinite(amount) || amount <= 0) {
      setPaymentModalMessage('error', 'Lütfen geçerli bir tutar girin.');
      return;
    }
    if (amount < min) {
      setPaymentModalMessage('error', 'Minimum tutar ' + limitText(min) + '.');
      return;
    }
    if (amount > max) {
      setPaymentModalMessage('error', 'Maksimum tutar ' + limitText(max) + '.');
      return;
    }
    var payload = { amount: amount };
    var paymentMethodId = method.id != null ? String(method.id) : '';
    if (paymentMethodId) {
      payload.payment_method_id = paymentMethodId;
    } else {
      payload.method = methodId(method);
      payload.provider = methodProvider(method);
    }
    if (kind === 'withdraw') {
      var account = document.getElementById('mprofilePaymentAccount');
      var accountNumber = account ? String(account.value || '').trim() : '';
      if (!accountNumber) {
        setPaymentModalMessage('error', 'Lütfen hesap bilgisini girin.');
        return;
      }
      payload.payment_method_id = paymentMethodId || methodId(method);
      payload.account_number = accountNumber.replace(/\s/g, '');
      payload.lang = 'tr';
      var network = document.getElementById('mprofilePaymentNetwork');
      if (network && network.value) payload.input_fields = { bank_id: network.value };
    }
    paymentModalSubmitting = true;
    var submit = document.querySelector('#mprofilePaymentModal .mprofile-payment-submit');
    if (submit) {
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      submit.textContent = 'İşleniyor...';
    }
    setPaymentModalMessage('', '');
    fetch(apiUrl(kind === 'withdraw' ? '/api/v2/withdraw-payment' : '/api/v2/deposit-payment'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: memberAuthHeaders({ 'Content-Type': 'application/json', Accept: 'application/json' }),
      body: JSON.stringify(payload)
    })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        if (env && env.success && env.data && env.data.payment_url) {
          window.location.href = String(env.data.payment_url);
          return;
        }
        if (env && env.success) {
          setPaymentModalMessage('success', env.message || (kind === 'withdraw' ? 'Çekim talebiniz alındı.' : 'İşlem başlatıldı.'));
          setTimeout(closePaymentModal, 900);
          return;
        }
        setPaymentModalMessage('error', (env && env.message) ? env.message : 'İşlem tamamlanamadı.');
      })
      .catch(function () {
        setPaymentModalMessage('error', 'Sunucu hatası. Lütfen tekrar deneyin.');
      })
      .then(function () {
        paymentModalSubmitting = false;
        if (submit) {
          submit.disabled = false;
          submit.removeAttribute('aria-busy');
          submit.textContent = kind === 'withdraw' ? 'ÇEKİM YAP' : 'PARA YATIR';
        }
      });
  }

  function filterBalanceMethods(panel, category) {
    panel.querySelectorAll('[data-mbalance-category]').forEach(function (item) {
      item.classList.toggle('active', item.getAttribute('data-mbalance-category') === category);
    });
    panel.querySelectorAll('[data-mbalance-method]').forEach(function (item) {
      var show = category === 'all' || item.getAttribute('data-category') === category;
      item.hidden = !show;
    });
  }

  function filterWithdrawMethods(panel, category) {
    panel.querySelectorAll('[data-mbalance-withdraw-category]').forEach(function (item) {
      item.classList.toggle('active', item.getAttribute('data-mbalance-withdraw-category') === category);
    });
    panel.querySelectorAll('[data-mbalance-withdraw-method]').forEach(function (item) {
      var show = category === 'all' || item.getAttribute('data-category') === category;
      item.hidden = !show;
    });
  }

  function showBalancePage(panel, sectionName) {
    panel = panel || getPanel();
    if (!panel) return;
    sectionName = sectionName || 'deposit';
    panel.classList.remove('mprofile-detail-active');
    panel.classList.remove('mprofile-bet-history-active');
    panel.classList.remove('mprofile-casino-history-active');
    panel.classList.add('mprofile-balance-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'true');
    var betHistory = panel.querySelector('[data-mprofile-view="bet-history"]');
    if (betHistory) betHistory.setAttribute('aria-hidden', 'true');
    var casinoHistory = panel.querySelector('[data-mprofile-view="casino-history"]');
    if (casinoHistory) casinoHistory.setAttribute('aria-hidden', 'true');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'false');
    panel.querySelectorAll('[data-mbalance-section]').forEach(function (section) {
      section.hidden = section.getAttribute('data-mbalance-section') !== sectionName;
    });
    panel.querySelectorAll('[data-mbalance-tab]').forEach(function (tab) {
      tab.classList.toggle('active', tab.getAttribute('data-mbalance-tab') === sectionName);
    });
    if (sectionName === 'deposit') loadBalanceMethods();
    if (sectionName === 'withdraw') loadWithdrawMethods();
    if (sectionName === 'history') loadTransactionHistory();
    if (sectionName === 'info') loadBalanceInfo();
    if (sectionName === 'withdraws') loadWithdrawStatus();
  }

  function showBetHistoryPage(panel, pageName) {
    panel = panel || getPanel();
    if (!panel) return;
    pageName = pageName || 'bets';
    panel.classList.remove('mprofile-detail-active');
    panel.classList.remove('mprofile-balance-active');
    panel.classList.remove('mprofile-casino-history-active');
    panel.classList.add('mprofile-bet-history-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'true');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'true');
    var betHistory = panel.querySelector('[data-mprofile-view="bet-history"]');
    if (betHistory) betHistory.setAttribute('aria-hidden', 'false');
    var casinoHistory = panel.querySelector('[data-mprofile-view="casino-history"]');
    if (casinoHistory) casinoHistory.setAttribute('aria-hidden', 'true');
    panel.querySelectorAll('[data-mbet-history-tab]').forEach(function (tab) {
      tab.classList.toggle('active', tab.getAttribute('data-mbet-history-tab') === pageName);
    });
    activeBetHistoryPage = pageName;
    if (betHistoryLoaded) renderBetHistory(panel);
    else loadBetHistory(panel);
  }

  function showCasinoHistoryPage(panel, pageName) {
    panel = panel || getPanel();
    if (!panel) return;
    pageName = ['bets', 'won', 'lost', 'returned'].indexOf(pageName) !== -1 ? pageName : 'bets';
    panel.classList.remove('mprofile-detail-active');
    panel.classList.remove('mprofile-balance-active');
    panel.classList.remove('mprofile-bet-history-active');
    panel.classList.add('mprofile-casino-history-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'true');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'true');
    var betHistory = panel.querySelector('[data-mprofile-view="bet-history"]');
    if (betHistory) betHistory.setAttribute('aria-hidden', 'true');
    var casinoHistory = panel.querySelector('[data-mprofile-view="casino-history"]');
    if (casinoHistory) casinoHistory.setAttribute('aria-hidden', 'false');
    panel.querySelectorAll('[data-mcasino-history-tab]').forEach(function (tab) {
      tab.classList.toggle('active', tab.getAttribute('data-mcasino-history-tab') === pageName);
    });
    activeCasinoHistoryPage = pageName;
    if (casinoHistoryLoaded) renderCasinoHistory(panel);
    else loadCasinoHistory(panel);
  }

  function rowMatchesBetHistoryPage(row, pageName) {
    var type = String(row && row.__type || row && row.txn_type || '').toLowerCase();
    var amount = Math.abs(Number(row && row.__amount || row && row.amount || 0));
    var isFinished = !!(row && row.__is_finished);
    if (pageName === 'open-bets') return !isFinished && type === 'bet';
    if (pageName === 'cashed-out') return isFinished && (type === 'win' || type === 'cancel' || type === 'refund') && amount > 0;
    if (pageName === 'won') return type === 'win' && amount > 0;
    if (pageName === 'lost') return isFinished && type === 'bet';
    if (pageName === 'returned' || pageName === 'won-return' || pageName === 'lost-return') return type === 'cancel' || type === 'refund';
    return true;
  }

  function rowMatchesBetHistoryForm(panel, row) {
    if (!betHistoryFormFilterActive) return true;
    var form = panel && panel.querySelector('[data-mbet-filter-wrapper] .filter-form-w-bc');
    if (!form) return true;
    var betId = String((form.elements.bet_id && form.elements.bet_id.value) || '').trim().toLowerCase();
    var sportName = String((form.elements.name && form.elements.name.value) || '').trim().toLowerCase();
    var betType = String((form.elements.bet_type && form.elements.bet_type.value) || '').trim();
    var period = String((form.elements.period && form.elements.period.value) || '24').trim();
    if (betId) {
      var idText = [row.__id, row.id, row.history_id, row.txn_code, row.wager_id, row.round_id, row.transaction_id, row.provider_txn_id].map(function (value) { return String(value || '').toLowerCase(); }).join(' ');
      if (idText.indexOf(betId) === -1) return false;
    }
    if (sportName) {
      var nameText = [row.__title, row.sport_name, row.game_name, row.gameName, row.game_code, row.game_id, row.vendor_code, row.provider_name, row.providerName].map(function (value) { return String(value || '').toLowerCase(); }).join(' ');
      if (nameText.indexOf(sportName) === -1) return false;
    }
    if (betType && betType !== 'ALL') {
      var explicitBetType = String(row.bet_type_id || row.betTypeId || row.bet_type || row.betType || row.selection_type || row.selectionType || '').toLowerCase();
      if (explicitBetType && explicitBetType !== betType.toLowerCase()) return false;
    }
    if (period) {
      var hours = { '24': 24, '72': 72, '168': 168, '720': 720 }[period];
      if (hours) {
        var ts = new Date(String(row.__created_at || row.created_at || row.createdAt || '').replace(' ', 'T')).getTime();
        if (!isNaN(ts) && ts < Date.now() - hours * 3600000) return false;
      }
    }
    return true;
  }

  function rowMatchesCasinoHistoryForm(panel, row) {
    if (!casinoHistoryFormFilterActive) return true;
    var form = panel && panel.querySelector('[data-mcasino-filter-wrapper] .filter-form-w-bc');
    if (!form) return true;
    var betId = String((form.elements.bet_id && form.elements.bet_id.value) || '').trim().toLowerCase();
    var name = String((form.elements.name && form.elements.name.value) || '').trim().toLowerCase();
    var type = String((form.elements.bet_type && form.elements.bet_type.value) || '').trim().toLowerCase();
    var period = String((form.elements.period && form.elements.period.value) || '24').trim();
    if (betId) {
      var idText = [row.__id, row.id, row.history_id, row.transaction_id, row.transactionId, row.provider_txn_id, row.providerTxnId, row.round_id, row.roundId].map(function (value) { return String(value || '').toLowerCase(); }).join(' ');
      if (idText.indexOf(betId) === -1) return false;
    }
    if (name) {
      var nameText = [row.__title, row.game_name, row.gameName, row.game_code, row.game_id, row.provider_name, row.providerName].map(function (value) { return String(value || '').toLowerCase(); }).join(' ');
      if (nameText.indexOf(name) === -1) return false;
    }
    if (type && row.__type !== type) return false;
    if (period) {
      var hours = { '24': 24, '72': 72, '168': 168, '720': 720 }[period];
      if (hours) {
        var ts = new Date(String(row.__created_at || '').replace(' ', 'T')).getTime();
        if (!isNaN(ts) && ts < Date.now() - hours * 3600000) return false;
      }
    }
    return true;
  }

  function renderBetHistory(panel) {
    panel = panel || getPanel();
    if (!panel) return;
    var rows = betHistoryRows.filter(function (row) {
      return rowMatchesBetHistoryPage(row, activeBetHistoryPage) && rowMatchesBetHistoryForm(panel, row);
    }).slice(0, 50);
    renderHistoryRows(panel, '[data-mbet-history-list]', rows, 'GÖSTERİLECEK BAHİS YOK');
  }

  function renderCasinoHistory(panel) {
    panel = panel || getPanel();
    if (!panel) return;
    var rows = casinoHistoryRows.filter(function (row) {
      return rowMatchesBetHistoryPage(row, activeCasinoHistoryPage) && rowMatchesCasinoHistoryForm(panel, row);
    }).slice(0, 50);
    renderHistoryRows(panel, '[data-mcasino-history-list]', rows, 'GÖSTERİLECEK CASINO GEÇMİŞİ YOK');
  }

  function sportsbookGroupKey(row, index) {
    var key = String(row.wager_id || row.__id || row.round_id || row.__round || '').trim();
    return key !== '' ? key : 'sports-row-' + index;
  }

  function reconcileSportsbookRows(rows) {
    var groups = [];
    var byKey = Object.create(null);
    rows.forEach(function (row, index) {
      var key = sportsbookGroupKey(row, index);
      if (!byKey[key]) {
        byKey[key] = [];
        groups.push(byKey[key]);
      }
      byKey[key].push(row);
    });
    return groups.map(function (group) {
      var betRows = group.filter(function (row) { return row.__type === 'bet'; });
      var settlementRows = group.filter(function (row) { return row.__type === 'win' || row.__type === 'cancel' || row.__type === 'refund'; });
      if (!settlementRows.length) {
        return betRows[0] || group[0];
      }
      var betRow = betRows[0] || group[group.length - 1] || settlementRows[0];
      var settlementRow = settlementRows[0];
      var cancelRows = settlementRows.filter(function (row) { return row.__type === 'cancel' || row.__type === 'refund'; });
      var winRows = settlementRows.filter(function (row) { return row.__type === 'win'; });
      var winAmount = winRows.reduce(function (sum, row) { return sum + Math.abs(Number(row.__amount || 0)); }, 0);
      var cancelAmount = cancelRows.reduce(function (sum, row) { return sum + Math.abs(Number(row.__amount || 0)); }, 0);
      var displayType = cancelRows.length ? 'cancel' : (winAmount > 0 ? 'win' : 'bet');
      var displayAmount = displayType === 'win'
        ? winAmount
        : (displayType === 'cancel' ? (cancelAmount || betRow.__amount || settlementRow.__amount) : (betRow.__amount || 0));
      return Object.assign({}, betRow, settlementRow, {
        txn_type: displayType,
        type: displayType,
        amount: displayAmount,
        is_finished: 1,
        __history_source: 'sports',
        __type: displayType,
        __amount: displayAmount,
        __id: betRow.__id || settlementRow.__id || '',
        __round: betRow.__round || settlementRow.__round || '',
        __title: betRow.__title || settlementRow.__title || 'Spor Kuponu',
        __provider: betRow.__provider || settlementRow.__provider || 'sports-betby',
        __created_at: settlementRow.__created_at || betRow.__created_at || '',
        __is_finished: true
      });
    });
  }

  function historyItemsFromEnvelope(envelope) {
    var data = envelope && envelope.data ? envelope.data : envelope;
    if (Array.isArray(data && data.items)) return data.items;
    if (Array.isArray(data && data.transactions)) return data.transactions;
    if (Array.isArray(envelope && envelope.items)) return envelope.items;
    if (Array.isArray(envelope && envelope.transactions)) return envelope.transactions;
    return [];
  }

  function renderHistoryRows(panel, listSelector, rows, emptyText) {
    var list = panel && panel.querySelector(listSelector);
    if (!list) return;
    if (!rows.length) {
      list.innerHTML = '<p class="empty-b-text-v-bc" role="status">' + escapeHtml(emptyText) + '</p>';
      return;
    }
    list.innerHTML = rows.map(function (row) {
      var source = row.__history_source === 'casino' ? 'casino' : 'sports';
      var type = String(row.__type || row.txn_type || 'bet').toLowerCase();
      var amount = Math.abs(Number(row.__amount || row.amount || 0));
      var amountClass = type === 'bet' ? 'is-bet' : 'is-win';
      var title = row.__title || 'Bahis';
      var provider = row.__provider || '—';
      var itemLabel = source === 'casino' ? 'Oyun Adı' : 'Spor Adı';
      return '<article class="mprofile-bet-history-card">'
        + '<div class="mprofile-bet-history-card-head"><strong>' + escapeHtml((source === 'casino' ? 'CASINO ' : '') + sportsbookTypeText(type)) + '</strong><span class="mprofile-status-badge ' + (row.__is_finished ? 'mprofile-status-approved' : 'mprofile-status-pending') + '">' + escapeHtml(source === 'casino' ? casinoStatusText(row.status) : sportsbookStatusText(row)) + '</span></div>'
        + '<dl>'
        + '<div><dt>Bahis Kimliği</dt><dd>' + escapeHtml(row.__id || '—') + '</dd></div>'
        + '<div><dt>' + itemLabel + '</dt><dd>' + escapeHtml(title) + '</dd></div>'
        + '<div><dt>Sağlayıcı</dt><dd>' + escapeHtml(provider) + '</dd></div>'
        + '<div><dt>Tutar</dt><dd class="' + amountClass + '">' + escapeHtml(moneyText(amount)) + '</dd></div>'
        + '<div><dt>Tarih</dt><dd>' + escapeHtml(formatDateTime(row.__created_at)) + '</dd></div>'
        + '</dl></article>';
    }).join('');
  }

  function loadBetHistory(panel, force) {
    panel = panel || getPanel();
    if (!panel) return;
    var list = panel.querySelector('[data-mbet-history-list]');
    if (list) list.innerHTML = '<p class="empty-b-text-v-bc" role="status">BAHİS GEÇMİŞİ YÜKLENİYOR...</p>';
    if (betHistoryLoaded && !force) {
      renderBetHistory(panel);
      return;
    }
    fetch(apiUrl('/api/v2/sportsbook/history?limit=200&offset=0'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
      .then(function (response) { return response.json(); })
      .then(function (envelope) {
        betHistoryRows = reconcileSportsbookRows(historyItemsFromEnvelope(envelope).map(function (row) { return normalizeHistoryRow(row, 'sports'); }));
        betHistoryLoaded = true;
        renderBetHistory(panel);
      })
      .catch(function () {
        betHistoryRows = [];
        betHistoryLoaded = true;
        if (list) list.innerHTML = '<p class="empty-b-text-v-bc" role="status">BAHİS GEÇMİŞİ YÜKLENEMEDİ</p>';
      });
  }

  function loadCasinoHistory(panel, force) {
    panel = panel || getPanel();
    if (!panel) return;
    var list = panel.querySelector('[data-mcasino-history-list]');
    if (list) list.innerHTML = '<p class="empty-b-text-v-bc" role="status">CASINO GEÇMİŞİ YÜKLENİYOR...</p>';
    if (casinoHistoryLoaded && !force) {
      renderCasinoHistory(panel);
      return;
    }
    fetch(apiUrl('/api/v2/profile/casino-game-history?limit=200&offset=0'), { credentials: 'same-origin', headers: memberAuthHeaders({ Accept: 'application/json' }) })
      .then(function (response) { return response.json(); })
      .then(function (envelope) {
        casinoHistoryRows = historyItemsFromEnvelope(envelope).map(function (row) { return normalizeHistoryRow(row, 'casino'); });
        casinoHistoryLoaded = true;
        renderCasinoHistory(panel);
      })
      .catch(function () {
        casinoHistoryRows = [];
        casinoHistoryLoaded = true;
        if (list) list.innerHTML = '<p class="empty-b-text-v-bc" role="status">CASINO GEÇMİŞİ YÜKLENEMEDİ</p>';
      });
  }

  function setPasswordMessage(panel, type, text) {
    var message = panel && panel.querySelector('[data-mprofile-password-message]');
    if (!message) return;
    message.textContent = text || '';
    message.classList.toggle('is-error', type === 'error');
    message.classList.toggle('is-success', type === 'success');
  }

  function setFreezeMessage(panel, type, text) {
    var message = panel && panel.querySelector('[data-mprofile-freeze-message]');
    if (!message) return;
    message.textContent = text || '';
    message.classList.toggle('is-error', type === 'error');
    message.classList.toggle('is-success', type === 'success');
  }

  function setTwofaMessage(panel, type, text) {
    var statusEl = panel && panel.querySelector('#mprofile-twofa-status');
    if (!statusEl || !text) return;
    statusEl.textContent = text;
    statusEl.classList.toggle('is-error', type === 'error');
    statusEl.classList.toggle('is-success', type === 'success');
  }

  function submitTwofaToggle(panel, toggle) {
    panel = panel || getPanel();
    if (!panel || !toggle) return;
    var statusEl = panel.querySelector('#mprofile-twofa-status');
    var wantOn = toggle.checked;
    var previous = !wantOn;
    var formData = new FormData();
    formData.append('ajax', '1');
    formData.append('action', 'twofa_toggle');
    formData.append('enabled', wantOn ? '1' : '0');
    formData.append('csrf_token', toggle.getAttribute('data-csrf-token') || '');
    toggle.disabled = true;
    setTwofaMessage(panel, '', '');

    fetch(apiUrl('/api/v2/two-factor'), {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: memberAuthHeaders({ Accept: 'application/json' })
    })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        if (env && env.success) {
          var enabled = typeof env.enabled !== 'undefined' ? !!env.enabled : !!(env.data && env.data.enabled);
          toggle.checked = enabled;
          setTwofaMessage(panel, 'success', enabled ? 'İki faktörlü kimlik doğrulama etkin.' : 'İki faktörlü kimlik doğrulama kapatıldı');
          return;
        }
        toggle.checked = previous;
        setTwofaMessage(panel, 'error', (env && env.message) ? env.message : 'İki aşamalı doğrulama güncellenemedi.');
      })
      .catch(function () {
        toggle.checked = previous;
        setTwofaMessage(panel, 'error', 'Sunucu hatası. Lütfen tekrar deneyin.');
      })
      .then(function () {
        toggle.disabled = false;
      });
  }

  function submitPasswordForm(panel) {
    panel = panel || getPanel();
    if (!panel) return;
    var form = panel.querySelector('#mprofileChangePasswordForm');
    if (!form) return;
    var oldPwd = (form.querySelector('[name="current_password"]') || {}).value || '';
    var newPwd = (form.querySelector('[name="password"]') || {}).value || '';
    var confirmPass = (form.querySelector('[name="password_confirmation"]') || {}).value || '';
    oldPwd = oldPwd.trim();
    newPwd = newPwd.trim();
    confirmPass = confirmPass.trim();

    if (!oldPwd || !newPwd || !confirmPass) {
      setPasswordMessage(panel, 'error', 'Lütfen tüm alanları doldurun.');
      return;
    }
    if (newPwd !== confirmPass) {
      setPasswordMessage(panel, 'error', 'Yeni şifreler uyuşmuyor.');
      return;
    }

    var button = panel.querySelector('#mprofileChangePwdBtn');
    if (button) button.disabled = true;
    setPasswordMessage(panel, '', '');

    fetch(apiUrl('/api/v2/password-update'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: memberAuthHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' }),
      body: JSON.stringify({
        current_password: oldPwd,
        password: newPwd,
        password_confirmation: confirmPass
      })
    })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        if (env && env.success) {
          setPasswordMessage(panel, 'success', (env.message && String(env.message).trim()) || 'Şifreniz güncellendi.');
          form.reset();
          return;
        }
        setPasswordMessage(panel, 'error', (env && env.message) ? env.message : 'Şifre güncellenemedi.');
      })
      .catch(function () {
        setPasswordMessage(panel, 'error', 'Sunucu hatası. Lütfen tekrar deneyin.');
      })
      .then(function () {
        if (button) button.disabled = false;
      });
  }

  function submitFreezeForm(panel) {
    panel = panel || getPanel();
    if (!panel) return;
    var form = panel.querySelector('#mprofileFreezeForm');
    if (!form) return;
    var input = form.querySelector('[name="password"]');
    var password = input && input.value ? String(input.value) : '';
    if (!password.trim()) {
      setFreezeMessage(panel, 'error', 'Şifrenizi girin.');
      return;
    }

    var button = panel.querySelector('#mprofileFreezeSaveBtn');
    if (button) button.disabled = true;
    setFreezeMessage(panel, '', '');

    fetch(apiUrl('/api/v2/account-freeze'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: memberAuthHeaders({ 'Content-Type': 'application/json', 'Accept': 'application/json' }),
      body: JSON.stringify({ password: password })
    })
      .then(function (res) { return res.json(); })
      .then(function (env) {
        if (env && env.success) {
          var data = env.data || {};
          var redirect = typeof data.redirect === 'string' && data.redirect.indexOf('/') === 0 ? data.redirect : '/login?account_frozen=1';
          window.location.href = redirect;
          return;
        }
        var message = (env && env.message) ? env.message : 'İşlem yapılamadı.';
        var errors = env && env.data && env.data.errors;
        if (errors && typeof errors === 'object') {
          Object.keys(errors).some(function (key) {
            var value = errors[key];
            if (Array.isArray(value) && value.length) {
              message = String(value[0]);
              return true;
            }
            if (typeof value === 'string' && value) {
              message = value;
              return true;
            }
            return false;
          });
        }
        setFreezeMessage(panel, 'error', message);
      })
      .catch(function () {
        setFreezeMessage(panel, 'error', 'Sunucu hatası. Lütfen tekrar deneyin.');
      })
      .then(function () {
        if (button) button.disabled = false;
      });
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
      var initialSection = requestedProfileSection();
      var initialBalanceSection = requestedBalanceSection();
      var initialBetHistorySection = requestedBetHistorySection();
      var initialCasinoHistorySection = requestedCasinoHistorySection();
      if (initialCasinoHistorySection) {
        openPanel();
        showCasinoHistoryPage(panel, initialCasinoHistorySection);
      } else if (initialBetHistorySection) {
        openPanel();
        showBetHistoryPage(panel, initialBetHistorySection);
      } else if (initialBalanceSection) {
        openPanel();
        showBalancePage(panel, initialBalanceSection);
      } else if (initialSection) {
        openPanel();
        showProfileDetails(panel, initialSection);
      }
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
          if (menuItem.getAttribute('data-href') === '/profile/details') {
            showProfileDetails(panel);
            return;
          }
          if (menuItem.getAttribute('data-href') === '/profile/deposit-withdraw') {
            showBalancePage(panel, 'deposit');
            return;
          }
          if (menuItem.getAttribute('data-href') === '/profile/bet-history') {
            showBetHistoryPage(panel, 'bets');
            return;
          }
          if (menuItem.getAttribute('data-href') === '/profile/casino-history') {
            showCasinoHistoryPage(panel, 'bets');
            return;
          }
          window.location.href = menuItem.getAttribute('data-href');
          return;
        }

        var casinoHistoryLink = target.closest('a[href*="page=casino-history"], [data-mcasino-history-tab]');
        if (casinoHistoryLink) {
          e.preventDefault();
          var casinoPage = casinoHistoryLink.getAttribute('data-mcasino-history-tab') || '';
          if (!casinoPage) {
            var filterMatch = (casinoHistoryLink.getAttribute('href') || '').match(/[?&]filter=([^&]+)/);
            casinoPage = filterMatch ? decodeURIComponent(filterMatch[1]) : 'bets';
          }
          showCasinoHistoryPage(panel, casinoPage);
          return;
        }

        var betHistoryLink = target.closest('a[href*="account=history"][href*="page="], [data-mbet-history-tab]');
        if (betHistoryLink) {
          e.preventDefault();
          var page = betHistoryLink.getAttribute('data-mbet-history-tab') || '';
          if (!page) {
            var pageMatch = (betHistoryLink.getAttribute('href') || '').match(/[?&]page=([^&]+)/);
            page = pageMatch ? decodeURIComponent(pageMatch[1]) : 'bets';
          }
          showBetHistoryPage(panel, page);
          return;
        }

        var betFilterToggle = target.closest('[data-mbet-filter-toggle]');
        if (betFilterToggle) {
          e.preventDefault();
          var wrapper = betFilterToggle.closest('[data-mbet-filter-wrapper]');
          var body = wrapper && wrapper.querySelector('.componentFilterBody-bc');
          if (wrapper && body) {
            var isOpenFilter = body.hasAttribute('hidden');
            body.hidden = !isOpenFilter;
            betFilterToggle.classList.toggle('active', isOpenFilter);
          }
          return;
        }

        var casinoFilterToggle = target.closest('[data-mcasino-filter-toggle]');
        if (casinoFilterToggle) {
          e.preventDefault();
          var casinoWrapper = casinoFilterToggle.closest('[data-mcasino-filter-wrapper]');
          var casinoBody = casinoWrapper && casinoWrapper.querySelector('.componentFilterBody-bc');
          if (casinoWrapper && casinoBody) {
            var casinoOpen = casinoBody.hasAttribute('hidden');
            casinoBody.hidden = !casinoOpen;
            casinoFilterToggle.classList.toggle('active', casinoOpen);
          }
          return;
        }

        var balanceLink = target.closest('a[href*="account=balance"][href*="page="]');
        if (balanceLink) {
          e.preventDefault();
          var match = (balanceLink.getAttribute('href') || '').match(/[?&]page=([^&]+)/);
          showBalancePage(panel, match ? decodeURIComponent(match[1]) : 'deposit');
          return;
        }

        var balanceCategoryItem = target.closest('[data-mbalance-category]');
        if (balanceCategoryItem) {
          e.preventDefault();
          filterBalanceMethods(panel, balanceCategoryItem.getAttribute('data-mbalance-category') || 'all');
          return;
        }

        var withdrawCategoryItem = target.closest('[data-mbalance-withdraw-category]');
        if (withdrawCategoryItem) {
          e.preventDefault();
          filterWithdrawMethods(panel, withdrawCategoryItem.getAttribute('data-mbalance-withdraw-category') || 'all');
          return;
        }

        var paymentMethodItem = target.closest('[data-mbalance-payment-kind][data-mbalance-payment-index]');
        if (paymentMethodItem) {
          e.preventDefault();
          var kind = paymentMethodItem.getAttribute('data-mbalance-payment-kind') === 'withdraw' ? 'withdraw' : 'deposit';
          var index = parseInt(paymentMethodItem.getAttribute('data-mbalance-payment-index') || '-1', 10);
          var method = balanceMethodStore[kind] && balanceMethodStore[kind][index];
          openPaymentModal(kind, method);
          return;
        }

        var cryptoOpen = target.closest('[data-mprofile-crypto-open]');
        if (cryptoOpen) {
          e.preventDefault();
          openCryptoPopup();
          return;
        }

        var cryptoClose = target.closest('[data-mprofile-crypto-close]');
        if (cryptoClose) {
          e.preventDefault();
          closeCryptoPopup();
          return;
        }

        var cryptoOption = target.closest('#mprofileCryptoPopup .checkbox-control-content-bc');
        if (cryptoOption) {
          e.preventDefault();
          selectCryptoOption(cryptoOption);
          return;
        }

        var paymentClose = target.closest('[data-mprofile-payment-close]');
        if (paymentClose) {
          e.preventDefault();
          closePaymentModal();
          return;
        }

        var paymentSubmit = target.closest('#mprofilePaymentModal .mprofile-payment-submit');
        if (paymentSubmit) {
          e.preventDefault();
          submitPaymentModal();
          return;
        }

        var historyFilter = target.closest('[data-mbalance-history-filter]');
        if (historyFilter) {
          e.preventDefault();
          panel.querySelectorAll('[data-mbalance-history-filter]').forEach(function (item) {
            item.classList.toggle('active', item === historyFilter);
          });
          renderTransactionHistory(historyFilter.getAttribute('data-mbalance-history-filter') || 'all');
          return;
        }

        var infoTab = target.closest('[data-mbalance-info-tab]');
        if (infoTab) {
          e.preventDefault();
          showBalanceInfoTab(panel, infoTab.getAttribute('data-mbalance-info-tab') || 'deposit');
          return;
        }

        var detailsLink = target.closest('a[href*="account=profile"][href*="page=details"]');
        if (detailsLink) {
          e.preventDefault();
          showProfileDetails(panel, 'details');
          return;
        }

        var changePasswordLink = target.closest('a[href*="account=profile"][href*="page=change-password"]');
        if (changePasswordLink) {
          e.preventDefault();
          showProfileDetails(panel, 'change-password');
          return;
        }

        var twoFactorLink = target.closest('a[href*="account=profile"][href*="page=two-factor-authentication"]');
        if (twoFactorLink) {
          e.preventDefault();
          showProfileDetails(panel, 'two-factor-authentication');
          return;
        }

        var freezeLink = target.closest('a[href*="account=profile"][href*="page=timeout-limits"]');
        if (freezeLink) {
          e.preventDefault();
          showProfileDetails(panel, 'timeout-limits');
          return;
        }

        var back = target.closest('.back-nav-bc');
        if (back) {
          e.preventDefault();
          showProfileMenu(panel);
          return;
        }

        var logoutButton = target.closest('.userLogoutBtn');
        if (logoutButton) {
          e.preventDefault();
          window.location.href = '/logout';
          return;
        }

        var passwordSubmit = target.closest('#mprofileChangePwdBtn');
        if (passwordSubmit) {
          e.preventDefault();
          submitPasswordForm(panel);
          return;
        }

        var freezeSubmit = target.closest('#mprofileFreezeSaveBtn');
        if (freezeSubmit) {
          e.preventDefault();
          submitFreezeForm(panel);
        }
      });

      panel.addEventListener('submit', function (e) {
        if (e.target && e.target.closest && e.target.closest('#mprofileChangePasswordForm')) {
          e.preventDefault();
          submitPasswordForm(panel);
          return;
        }
        if (e.target && e.target.closest && e.target.closest('#mprofileFreezeForm')) {
          e.preventDefault();
          submitFreezeForm(panel);
          return;
        }
        if (e.target && e.target.closest && e.target.closest('#mprofilePaymentForm')) {
          e.preventDefault();
          submitPaymentModal();
          return;
        }
        if (e.target && e.target.closest && e.target.closest('[data-mbet-filter-wrapper] .filter-form-w-bc')) {
          e.preventDefault();
          betHistoryFormFilterActive = true;
          renderBetHistory(panel);
          return;
        }
        if (e.target && e.target.closest && e.target.closest('[data-mcasino-filter-wrapper] .filter-form-w-bc')) {
          e.preventDefault();
          casinoHistoryFormFilterActive = true;
          renderCasinoHistory(panel);
        }
      });

      panel.addEventListener('change', function (e) {
        var target = e.target && e.target.closest ? e.target : null;
        var twofaToggle = target && target.closest('#mprofileTwofaToggle');
        if (twofaToggle) submitTwofaToggle(panel, twofaToggle);
      });

      panel.addEventListener('input', function (e) {
        if (e.target && e.target.id === 'mprofilePaymentAmount') syncPaymentSubmitState();
        if (e.target && e.target.id === 'mprofileCryptoSearch') filterCryptoOptions(e.target.value);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
