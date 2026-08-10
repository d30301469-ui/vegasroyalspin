(function () {
  'use strict';

  var BRAND = 'Vegasroyalspin';
  var JWT_KEY = 'app_member_jwt';
  var tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;

  var PENDING_DEP_KEY = 'tg_pending_deposit_trx';

  var state = {
    token: '',
    user: null,
    panel: 'home',
    slots: { page: 1, hasNext: false, loaded: false, q: '' },
    live: { page: 1, hasNext: false, loaded: false, q: '' },
    searchTimer: null,
    wallet: {
      methods: [],
      loaded: false,
      depositMethod: null,
      withdrawMethod: null,
      depositBusy: false,
      withdrawBusy: false,
      pollTimer: null,
      overlayKind: '',
      overlayUrl: ''
    }
  };

  var els = {
    user: document.getElementById('tgUser'),
    balance: document.getElementById('tgBalance'),
    homeBalance: document.getElementById('tgHomeBalance'),
    welcomeName: document.getElementById('tgWelcomeName'),
    balanceBtn: document.getElementById('tgBalanceBtn'),
    searchWrap: document.getElementById('tgSearchWrap'),
    search: document.getElementById('tgSearch'),
    winners: document.getElementById('tgWinners'),
    homeFeatured: document.getElementById('tgHomeFeatured'),
    homeFeaturedStatus: document.getElementById('tgHomeFeaturedStatus'),
    slotsStatus: document.getElementById('tgSlotsStatus'),
    slotsGrid: document.getElementById('tgSlotsGrid'),
    slotsMore: document.getElementById('tgSlotsMore'),
    liveStatus: document.getElementById('tgLiveStatus'),
    liveGrid: document.getElementById('tgLiveGrid'),
    liveMore: document.getElementById('tgLiveMore'),
    sportLaunch: document.getElementById('tgSportLaunch'),
    sportHint: document.getElementById('tgSportHint'),
    accUser: document.getElementById('tgAccUser'),
    accBal: document.getElementById('tgAccBal'),
    accBonus: document.getElementById('tgAccBonus'),
    accHint: document.getElementById('tgAccHint'),
    depMethods: document.getElementById('tgDepMethods'),
    depForm: document.getElementById('tgDepForm'),
    depSelected: document.getElementById('tgDepSelected'),
    depAmount: document.getElementById('tgDepAmount'),
    depLimits: document.getElementById('tgDepLimits'),
    depSubmit: document.getElementById('tgDepSubmit'),
    depHint: document.getElementById('tgDepHint'),
    wdrMethods: document.getElementById('tgWdrMethods'),
    wdrForm: document.getElementById('tgWdrForm'),
    wdrSelected: document.getElementById('tgWdrSelected'),
    wdrFields: document.getElementById('tgWdrFields'),
    wdrAmount: document.getElementById('tgWdrAmount'),
    wdrLimits: document.getElementById('tgWdrLimits'),
    wdrSubmit: document.getElementById('tgWdrSubmit'),
    wdrHint: document.getElementById('tgWdrHint'),
    promoList: document.getElementById('tgPromoList'),
    profUser: document.getElementById('tgProfUser'),
    profId: document.getElementById('tgProfId'),
    profBal: document.getElementById('tgProfBal'),
    profBonus: document.getElementById('tgProfBonus'),
    overlay: document.getElementById('tgOverlay'),
    overlayTitle: document.getElementById('tgOverlayTitle'),
    overlayFrame: document.getElementById('tgOverlayFrame'),
    overlayLoader: document.getElementById('tgOverlayLoader'),
    overlayClose: document.getElementById('tgOverlayClose'),
    overlayRefresh: document.getElementById('tgOverlayRefresh'),
    toast: document.getElementById('tgToast'),
    nav: document.getElementById('tgNav')
  };

  function setBalanceText(value) {
    var txt = money(value);
    if (els.balance) els.balance.textContent = txt;
    if (els.homeBalance) els.homeBalance.textContent = txt;
    if (els.accBal) els.accBal.textContent = txt;
    if (els.profBal) els.profBal.textContent = txt;
  }

  function syncProfilePanel() {
    var u = state.user || {};
    if (els.profUser) els.profUser.textContent = u.username || '—';
    if (els.profId) els.profId.textContent = u.id != null ? String(u.id) : '—';
    if (els.profBonus) els.profBonus.textContent = money(u.bonus_balance || 0);
  }

  function apiBase() {
    var base = String(window.__MEMBER_API_BASE__ || '/api/v2').replace(/\/$/, '');
    return base || '/api/v2';
  }

  function money(n) {
    var v = Number(n || 0);
    try {
      return v.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    } catch (e) {
      return v.toFixed(2) + ' ₺';
    }
  }

  function toast(msg) {
    if (!els.toast) return;
    els.toast.hidden = false;
    els.toast.textContent = msg;
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { els.toast.hidden = true; }, 2600);
  }

  function persistJwt(token) {
    state.token = token;
    try { localStorage.setItem(JWT_KEY, token); } catch (e) {}
    try { sessionStorage.setItem(JWT_KEY, token); } catch (e2) {}
    try { sessionStorage.setItem('member_jwt', token); } catch (e3) {}
    window.__HAS_MEMBER_JWT__ = true;
    window.__MEMBER_JWT_BOOTSTRAP__ = token;
  }

  function request(path, options) {
    options = options || {};
    var headers = Object.assign({
      Accept: 'application/json',
      'Content-Type': 'application/json'
    }, options.headers || {});
    if (state.token) headers.Authorization = 'Bearer ' + state.token;
    return fetch(apiBase() + path, {
      method: options.method || 'GET',
      headers: headers,
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'include'
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        return { ok: res.ok, status: res.status, json: json };
      });
    });
  }

  function setPanel(name) {
    state.panel = name;
    Array.prototype.forEach.call(document.querySelectorAll('.tg-panel'), function (p) {
      p.classList.toggle('is-active', p.getAttribute('data-panel') === name);
    });
    Array.prototype.forEach.call(els.nav.querySelectorAll('button'), function (b) {
      var goto = b.getAttribute('data-goto');
      var active = goto === name
        || ((name === 'deposit' || name === 'withdraw' || name === 'profile' || name === 'promos') && goto === 'account');
      b.classList.toggle('is-active', active);
    });
    var showSearch = name === 'slots' || name === 'live';
    els.searchWrap.hidden = !showSearch;
    if (name === 'slots' && !state.slots.loaded) loadGames('slots', false);
    if (name === 'live' && !state.live.loaded) loadGames('live', false);
    if (name === 'account' || name === 'profile') {
      refreshBalance();
      syncProfilePanel();
    }
    if (name === 'deposit' || name === 'withdraw') {
      refreshBalance();
      ensureWalletMethods().then(function () {
        renderWalletMethods(name);
      });
    }
    if (name === 'promos') loadPromos();
    if (tg && tg.HapticFeedback) {
      try { tg.HapticFeedback.selectionChanged(); } catch (e) {}
    }
  }

  function setTelegramBack(enabled) {
    if (!tg || !tg.BackButton) return;
    try {
      if (tg.BackButton.offClick) tg.BackButton.offClick(closeOverlay);
      if (enabled) {
        if (tg.BackButton.onClick) tg.BackButton.onClick(closeOverlay);
        tg.BackButton.show();
      } else {
        tg.BackButton.hide();
      }
    } catch (e) {}
  }

  function openOverlay(url, title, opts) {
    opts = opts || {};
    if (!els.overlay || !els.overlayFrame || !url) return;
    state.wallet.overlayKind = opts.kind || 'frame';
    state.wallet.overlayUrl = url;
    els.overlay.hidden = false;
    document.body.classList.add('tg-overlay-open');
    if (els.overlayTitle) els.overlayTitle.textContent = title || BRAND;
    if (els.overlayLoader) els.overlayLoader.hidden = false;
    els.overlayFrame.onload = function () {
      if (els.overlayLoader) els.overlayLoader.hidden = true;
    };
    els.overlayFrame.src = url;
    setTelegramBack(true);
    if (tg && tg.HapticFeedback) {
      try { tg.HapticFeedback.impactOccurred('light'); } catch (e2) {}
    }
  }

  function closeOverlay() {
    if (!els.overlay) return;
    var kind = state.wallet.overlayKind;
    els.overlay.hidden = true;
    document.body.classList.remove('tg-overlay-open');
    if (els.overlayFrame) els.overlayFrame.src = 'about:blank';
    if (els.overlayLoader) els.overlayLoader.hidden = true;
    state.wallet.overlayKind = '';
    state.wallet.overlayUrl = '';
    setTelegramBack(false);
    if (kind === 'payment') resumePendingDeposit();
    else refreshBalance();
  }

  function refreshOverlay() {
    if (!els.overlayFrame || !state.wallet.overlayUrl) return;
    if (els.overlayLoader) els.overlayLoader.hidden = false;
    els.overlayFrame.src = state.wallet.overlayUrl;
  }

  function methodKey(m) {
    return String(m.id || m.payment_method_id || m.method_id || m.method || '').trim();
  }

  function methodLimitsText(m) {
    var min = Number(m.min_amount || 0);
    var max = Number(m.max_amount || 0);
    var parts = [];
    if (min > 0) parts.push('Min ' + min.toLocaleString('tr-TR') + ' ₺');
    if (max > 0) parts.push('Maks ' + max.toLocaleString('tr-TR') + ' ₺');
    return parts.join(' · ');
  }

  function ensureWalletMethods() {
    if (state.wallet.loaded && state.wallet.methods.length) {
      return Promise.resolve(state.wallet.methods);
    }
    return request('/payment-methods').then(function (res) {
      var data = (res.json && res.json.data) || {};
      var items = data.payment_methods || data.methods || [];
      if (!res.ok || !items.length) {
        return request('/deposit-payment').then(function (res2) {
          var data2 = (res2.json && res2.json.data) || {};
          items = data2.payment_methods || data2.methods || [];
          state.wallet.methods = items;
          state.wallet.loaded = true;
          return items;
        });
      }
      state.wallet.methods = items;
      state.wallet.loaded = true;
      return items;
    });
  }

  function renderWalletMethods(kind) {
    var listEl = kind === 'withdraw' ? els.wdrMethods : els.depMethods;
    if (!listEl) return;
    var items = (state.wallet.methods || []).filter(function (m) {
      return kind === 'withdraw' ? !!m.withdrawal_enabled : !!m.deposit_enabled;
    });
    if (!items.length) {
      listEl.textContent = kind === 'withdraw'
        ? 'Şu an çekim yöntemi yok.'
        : 'Şu an yatırım yöntemi yok.';
      return;
    }
    listEl.innerHTML = '';
    items.forEach(function (m) {
      var key = methodKey(m);
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tg-method';
      btn.setAttribute('data-method', key);
      var logo = String(m.logo_url || '');
      btn.innerHTML =
        (logo ? '<img src="' + logo.replace(/"/g, '&quot;') + '" alt="" loading="lazy">' : '<span class="tg-method-fallback"></span>') +
        '<span class="tg-method-copy"><strong></strong><em></em></span>';
      btn.querySelector('strong').textContent = m.name || key;
      btn.querySelector('em').textContent = methodLimitsText(m) || (m.type || '');
      btn.addEventListener('click', function () {
        Array.prototype.forEach.call(listEl.querySelectorAll('.tg-method'), function (el) {
          el.classList.toggle('is-active', el === btn);
        });
        if (kind === 'withdraw') selectWithdrawMethod(m);
        else selectDepositMethod(m);
      });
      listEl.appendChild(btn);
    });
  }

  function selectDepositMethod(m) {
    state.wallet.depositMethod = m;
    if (els.depForm) els.depForm.hidden = false;
    if (els.depSelected) els.depSelected.textContent = m.name || methodKey(m);
    if (els.depLimits) els.depLimits.textContent = methodLimitsText(m);
    if (els.depHint) {
      els.depHint.textContent = 'Ödeme Telegram içinde açılır. Bitince geri dönün; bakiye güncellenir.';
    }
    if (els.depAmount) {
      els.depAmount.min = String(m.min_amount || 1);
      els.depAmount.focus();
    }
  }

  function selectWithdrawMethod(m) {
    state.wallet.withdrawMethod = m;
    if (els.wdrForm) els.wdrForm.hidden = false;
    if (els.wdrSelected) els.wdrSelected.textContent = m.name || methodKey(m);
    if (els.wdrLimits) els.wdrLimits.textContent = methodLimitsText(m);
    if (els.wdrHint) els.wdrHint.textContent = '';
    if (els.wdrAmount) els.wdrAmount.min = String(m.min_amount || 1);
    renderWithdrawFields(m);
  }

  function renderWithdrawFields(m) {
    if (!els.wdrFields) return;
    var key = methodKey(m);
    var html = '';
    if (key === 'banktransfer') {
      html =
        '<label class="tg-field"><span>IBAN</span>' +
        '<input id="tgWdrAccount" type="text" autocomplete="off" placeholder="TR00 0000 0000 0000 0000 0000 00" maxlength="34"></label>';
    } else if (key === 'crypto') {
      html =
        '<label class="tg-field"><span>Ağ</span>' +
        '<select id="tgWdrNetwork">' +
        '<option value="65bd7bd5964700005d002ae4">USDT TRC20</option>' +
        '<option value="65bd7bba964700005d002ae1">Bitcoin</option>' +
        '<option value="65bd7bc1964700005d002ae2">Litecoin</option>' +
        '</select></label>' +
        '<label class="tg-field"><span>Cüzdan adresi</span>' +
        '<input id="tgWdrAccount" type="text" autocomplete="off" placeholder="Cüzdan adresi"></label>';
    } else if (key === 'wallet') {
      html =
        '<label class="tg-field"><span>Mega Wallet hesap no</span>' +
        '<input id="tgWdrAccount" type="text" inputmode="numeric" autocomplete="off" placeholder="10 haneli hesap no" maxlength="10"></label>';
    } else {
      html =
        '<label class="tg-field"><span>Hesap / IBAN</span>' +
        '<input id="tgWdrAccount" type="text" autocomplete="off" placeholder="Hesap bilgisi"></label>';
    }
    els.wdrFields.innerHTML = html;
  }

  function openPaymentUrl(url) {
    if (!url) return;
    openOverlay(url, 'Ödeme', { kind: 'payment' });
  }

  function rememberPendingDeposit(trx) {
    if (!trx) return;
    try { sessionStorage.setItem(PENDING_DEP_KEY, trx); } catch (e) {}
  }

  function clearPendingDeposit() {
    try { sessionStorage.removeItem(PENDING_DEP_KEY); } catch (e) {}
  }

  function pollDepositStatus(trx) {
    if (!trx) return Promise.resolve();
    return request('/payment/status?trx=' + encodeURIComponent(trx)).then(function (res) {
      var data = (res.json && res.json.data) || {};
      if (data.confirmed || data.status === 'confirmed') {
        clearPendingDeposit();
        toast('Yatırım onaylandı');
        return refreshBalance();
      }
      if (data.terminal && !data.confirmed) {
        clearPendingDeposit();
        if (els.depHint) els.depHint.textContent = 'İşlem tamamlanamadı: ' + (data.status || 'reddedildi');
        return null;
      }
      return refreshBalance();
    }).catch(function () {
      return refreshBalance();
    });
  }

  function resumePendingDeposit() {
    var trx = '';
    try { trx = String(sessionStorage.getItem(PENDING_DEP_KEY) || ''); } catch (e) {}
    if (!trx) {
      refreshBalance();
      return;
    }
    if (els.depHint) els.depHint.textContent = 'Ödeme durumu kontrol ediliyor…';
    pollDepositStatus(trx).then(function () {
      if (els.depHint && sessionStorage.getItem(PENDING_DEP_KEY)) {
        els.depHint.textContent = 'Ödeme henüz onaylanmadı. Biraz sonra bakiye yenilenecek.';
      }
    });
  }

  function submitDeposit() {
    if (state.wallet.depositBusy) return;
    var m = state.wallet.depositMethod;
    if (!m) {
      toast('Ödeme yöntemi seçin');
      return;
    }
    var amount = parseFloat(String(els.depAmount && els.depAmount.value || '').replace(',', '.'));
    var min = Number(m.min_amount || 0);
    var max = Number(m.max_amount || 0);
    if (!(amount > 0)) {
      toast('Geçerli bir tutar girin');
      return;
    }
    if (min > 0 && amount < min) {
      toast('Minimum tutar ' + min.toLocaleString('tr-TR') + ' ₺');
      return;
    }
    if (max > 0 && amount > max) {
      toast('Maksimum tutar ' + max.toLocaleString('tr-TR') + ' ₺');
      return;
    }
    var returnUrl = window.location.origin + '/tg#deposit';
    state.wallet.depositBusy = true;
    els.depSubmit.disabled = true;
    els.depSubmit.textContent = 'Hazırlanıyor…';
    if (els.depHint) els.depHint.textContent = '';
    request('/deposit-payment', {
      method: 'POST',
      body: {
        amount: amount,
        method: methodKey(m),
        provider: 'megapayz',
        return_url: returnUrl
      }
    }).then(function (res) {
      var data = (res.json && res.json.data) || {};
      var payUrl = String(data.payment_url || data.redirect_url || '').trim();
      var trx = String(data.trx || '').trim();
      if (!res.ok || !payUrl) {
        toast((res.json && res.json.message) || 'Yatırım başlatılamadı');
        if (els.depHint) els.depHint.textContent = (res.json && res.json.message) || 'Yatırım başlatılamadı.';
        return;
      }
      rememberPendingDeposit(trx);
      if (els.depHint) {
        els.depHint.textContent = 'Ödeme ekranı açıldı. Tamamlayınca geri ile kapatın.';
      }
      toast('Ödeme ekranı açılıyor');
      openPaymentUrl(payUrl);
      clearTimeout(state.wallet.pollTimer);
      state.wallet.pollTimer = setTimeout(function () { resumePendingDeposit(); }, 8000);
    }).catch(function (err) {
      toast(err.message || 'Bağlantı hatası');
    }).then(function () {
      state.wallet.depositBusy = false;
      els.depSubmit.disabled = false;
      els.depSubmit.textContent = 'Ödemeye Geç';
    });
  }

  function submitWithdraw() {
    if (state.wallet.withdrawBusy) return;
    var m = state.wallet.withdrawMethod;
    if (!m) {
      toast('Çekim yöntemi seçin');
      return;
    }
    var key = methodKey(m);
    var amount = parseFloat(String(els.wdrAmount && els.wdrAmount.value || '').replace(',', '.'));
    var min = Number(m.min_amount || 0);
    var max = Number(m.max_amount || 0);
    if (!(amount > 0)) {
      toast('Geçerli bir tutar girin');
      return;
    }
    if (min > 0 && amount < min) {
      toast('Minimum tutar ' + min.toLocaleString('tr-TR') + ' ₺');
      return;
    }
    if (max > 0 && amount > max) {
      toast('Maksimum tutar ' + max.toLocaleString('tr-TR') + ' ₺');
      return;
    }
    var accountEl = document.getElementById('tgWdrAccount');
    var account = accountEl ? String(accountEl.value || '').trim() : '';
    var inputFields = {};
    if (key === 'banktransfer') {
      account = account.replace(/\s/g, '').toUpperCase();
      if (!/^TR[0-9]{24}$/.test(account)) {
        toast('Geçerli bir IBAN girin (TR + 24 rakam)');
        return;
      }
    } else if (key === 'crypto') {
      if (!account) {
        toast('Cüzdan adresi zorunlu');
        return;
      }
      var netEl = document.getElementById('tgWdrNetwork');
      var net = netEl ? String(netEl.value || '') : '';
      if (!net) {
        toast('Kripto ağı seçin');
        return;
      }
      inputFields.bank_id = net;
      inputFields.crypto_network = net;
    } else if (key === 'wallet') {
      if (!account) {
        toast('Hesap numarası zorunlu');
        return;
      }
    } else if (!account) {
      toast('Hesap bilgisi zorunlu');
      return;
    }

    var payload = {
      amount: amount,
      payment_method_id: key,
      account_number: account,
      lang: 'tr'
    };
    if (Object.keys(inputFields).length) payload.input_fields = inputFields;

    state.wallet.withdrawBusy = true;
    els.wdrSubmit.disabled = true;
    els.wdrSubmit.textContent = 'Gönderiliyor…';
    if (els.wdrHint) els.wdrHint.textContent = '';
    request('/withdraw-payment', { method: 'POST', body: payload }).then(function (res) {
      var data = (res.json && res.json.data) || {};
      if (!res.ok) {
        var msg = (res.json && res.json.message) || 'Çekim oluşturulamadı';
        toast(msg);
        if (els.wdrHint) els.wdrHint.textContent = msg;
        return;
      }
      var okMsg = (res.json && res.json.message) || 'Çekim talebiniz alındı.';
      if (data.reference_code || data.trx) {
        okMsg += ' Ref: ' + (data.reference_code || data.trx);
      }
      toast(okMsg);
      if (els.wdrHint) els.wdrHint.textContent = okMsg;
      if (els.wdrAmount) els.wdrAmount.value = '';
      if (accountEl) accountEl.value = '';
      refreshBalance();
      if (data.payment_url) openPaymentUrl(String(data.payment_url));
    }).catch(function (err) {
      toast(err.message || 'Bağlantı hatası');
    }).then(function () {
      state.wallet.withdrawBusy = false;
      els.wdrSubmit.disabled = false;
      els.wdrSubmit.textContent = 'Çekim Talebi Oluştur';
    });
  }

  function openSite() {
    // Siteye çıkış yok — tüm akış Mini App içinde kalır.
    toast('Bu işlem Telegram içinde yapılır');
  }

  function loadPromos() {
    if (!els.promoList) return Promise.resolve();
    els.promoList.textContent = 'Yükleniyor…';
    return request('/promotions').then(function (res) {
      var data = (res.json && res.json.data) || {};
      var items = data.promotions || data.items || [];
      if (!res.ok) {
        els.promoList.textContent = (res.json && res.json.message) || 'Promosyonlar alınamadı.';
        return;
      }
      if (!items.length) {
        els.promoList.textContent = 'Aktif promosyon yok.';
        return;
      }
      els.promoList.innerHTML = '';
      items.forEach(function (p) {
        var card = document.createElement('article');
        card.className = 'tg-promo';
        var title = String(p.title || 'Promosyon');
        var desc = String(p.description || p.long_description || '').trim();
        var img = String(p.image_url || '');
        card.innerHTML =
          (img ? '<img src="' + img.replace(/"/g, '&quot;') + '" alt="" loading="lazy">' : '') +
          '<div class="tg-promo-body"><strong></strong><p></p></div>';
        card.querySelector('strong').textContent = title;
        card.querySelector('p').textContent = desc;
        els.promoList.appendChild(card);
      });
    }).catch(function () {
      els.promoList.textContent = 'Promosyonlar alınamadı.';
    });
  }

  function auth() {
    if (!tg || !tg.initData) {
      els.user.textContent = 'Telegram dışı açılış';
      return Promise.reject(new Error('Bu sayfa Telegram Mini App içinde açılmalıdır.'));
    }
    return request('/auth/telegram', {
      method: 'POST',
      body: { init_data: tg.initData }
    }).then(function (res) {
      if (!res.ok || !res.json || !res.json.success) {
        throw new Error((res.json && res.json.message) || ('Giriş başarısız (' + res.status + ')'));
      }
      var data = res.json.data || {};
      var token = String(data.token || data.jwt || '');
      if (!token) throw new Error('Oturum anahtarı alınamadı');
      persistJwt(token);
      state.user = data.user || {};
      var uname = state.user.username || 'oyuncu';
      els.user.textContent = '@' + uname;
      if (els.welcomeName) els.welcomeName.textContent = uname;
      els.accUser.textContent = uname;
      setBalanceText(state.user.balance || 0);
      els.accBonus.textContent = money(state.user.bonus_balance || 0);
      els.accHint.textContent = BRAND + ' hesabınız hazır.';
      syncProfilePanel();
      return state.user;
    });
  }

  function refreshBalance() {
    return request('/balance').then(function (res) {
      if (!res.ok) return;
      var data = (res.json && res.json.data) || {};
      var balObj = data.balance;
      var main = (balObj && typeof balObj === 'object')
        ? (balObj.balance != null ? balObj.balance : balObj.total_balance)
        : (data.balance != null ? data.balance : data.total_balance);
      var bonus = (balObj && typeof balObj === 'object')
        ? (balObj.bonus_balance != null ? balObj.bonus_balance : 0)
        : (data.bonus_balance || 0);
      if (main != null) {
        setBalanceText(main);
      }
      els.accBonus.textContent = money(bonus);
      if (els.profBonus) els.profBonus.textContent = money(bonus);
      if (state.user) {
        if (main != null) state.user.balance = main;
        state.user.bonus_balance = bonus;
      }
    }).catch(function () {});
  }

  function loadWinners() {
    els.winners.textContent = 'Yükleniyor…';
    return request('/winners?limit=6').then(function (res) {
      var data = (res.json && res.json.data) || {};
      var items = data.winners || data.items || [];
      if (!res.ok || !items.length) {
        els.winners.textContent = 'Henüz kazanan yok.';
        return;
      }
      els.winners.innerHTML = '';
      items.slice(0, 6).forEach(function (w) {
        var player = String(w.player || w.user_mask || 'Üye');
        var gameName = String(w.gameName || w.game_name || '');
        var initials = player.replace(/[^a-zA-Z0-9ğüşıöçĞÜŞİÖÇ]/g, '').slice(0, 2).toUpperCase() || 'VR';
        var row = document.createElement('div');
        row.className = 'tg-winner';
        var left = document.createElement('div');
        left.className = 'tg-winner-left';
        var av = document.createElement('div');
        av.className = 'tg-winner-av';
        av.textContent = initials;
        var meta = document.createElement('div');
        meta.className = 'tg-winner-meta';
        var strong = document.createElement('strong');
        strong.textContent = player;
        var game = document.createElement('div');
        game.className = 'tg-winner-game';
        game.textContent = gameName;
        meta.appendChild(strong);
        meta.appendChild(game);
        left.appendChild(av);
        left.appendChild(meta);
        var amt = document.createElement('div');
        amt.className = 'amt';
        amt.textContent = money(w.winAmount != null ? w.winAmount : w.win_amount);
        row.appendChild(left);
        row.appendChild(amt);
        els.winners.appendChild(row);
      });
    }).catch(function () {
      els.winners.textContent = 'Kazananlar alınamadı.';
    });
  }

  function renderHomeFeatured(items) {
    if (!els.homeFeatured) return;
    els.homeFeatured.innerHTML = '';
    var list = (items || []).slice(0, 12);
    if (!list.length) {
      var empty = document.createElement('div');
      empty.className = 'tg-rail-status';
      empty.textContent = 'Öne çıkan oyun yok.';
      els.homeFeatured.appendChild(empty);
      return;
    }
    list.forEach(function (g) {
      var id = String(g.game_id || g.id || '');
      var name = String(g.name || g.title || g.game_name || 'Oyun');
      var cover = String(g.cover || g.image_url || g.thumbnail_url || '');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tg-rail-card';
      btn.innerHTML =
        (cover ? '<img src="' + cover.replace(/"/g, '&quot;') + '" alt="" loading="lazy">' : '<img alt="">') +
        '<span></span>';
      btn.querySelector('span').textContent = name;
      btn.addEventListener('click', function () { launchGame(id, name); });
      els.homeFeatured.appendChild(btn);
    });
  }

  function loadHomeFeatured() {
    if (!els.homeFeatured) return Promise.resolve();
    if (els.homeFeaturedStatus) {
      els.homeFeatured.innerHTML = '';
      var st = document.createElement('div');
      st.className = 'tg-rail-status';
      st.id = 'tgHomeFeaturedStatus';
      st.textContent = 'Yükleniyor…';
      els.homeFeaturedStatus = st;
      els.homeFeatured.appendChild(st);
    }
    return request(gamesPath('slots', 1, '')).then(function (res) {
      if (!res.ok) {
        if (els.homeFeaturedStatus) els.homeFeaturedStatus.textContent = 'Oyunlar alınamadı.';
        return;
      }
      var data = (res.json && res.json.data) || {};
      var items = data.games || data.items || [];
      renderHomeFeatured(items);
    }).catch(function () {
      if (els.homeFeaturedStatus) els.homeFeaturedStatus.textContent = 'Oyunlar alınamadı.';
    });
  }

  function gamesPath(kind, page, q) {
    var qs = 'limit=24&page=' + page;
    if (q) qs += '&search=' + encodeURIComponent(q);
    if (kind === 'live') {
      return '/games?source=gsc&gsc_only=1&game_type=1&' + qs;
    }
    return '/games?' + qs;
  }

  function renderGames(kind, items, append) {
    var grid = kind === 'live' ? els.liveGrid : els.slotsGrid;
    if (!append) grid.innerHTML = '';
    (items || []).forEach(function (g) {
      var id = String(g.game_id || g.id || '');
      var name = String(g.name || g.title || g.game_name || 'Oyun');
      var provider = String(g.provider || g.provider_name || '');
      var cover = String(g.cover || g.image_url || g.thumbnail_url || '');
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'tg-card';
      btn.innerHTML =
        (cover ? '<img src="' + cover.replace(/"/g, '&quot;') + '" alt="" loading="lazy">' : '<img alt="">') +
        '<div class="tg-card-body"><div class="tg-card-title"></div><div class="tg-card-meta"></div></div>';
      btn.querySelector('.tg-card-title').textContent = name;
      btn.querySelector('.tg-card-meta').textContent = provider;
      btn.addEventListener('click', function () { launchGame(id, name); });
      grid.appendChild(btn);
    });
  }

  function loadGames(kind, append) {
    var bag = state[kind];
    var status = kind === 'live' ? els.liveStatus : els.slotsStatus;
    var grid = kind === 'live' ? els.liveGrid : els.slotsGrid;
    var more = kind === 'live' ? els.liveMore : els.slotsMore;
    if (!append) {
      bag.page = 1;
      status.hidden = false;
      status.className = 'tg-status';
      status.textContent = 'Yükleniyor…';
      grid.hidden = true;
      more.hidden = true;
    }
    return request(gamesPath(kind, bag.page, bag.q)).then(function (res) {
      if (!res.ok) {
        status.hidden = false;
        status.className = 'tg-status is-error';
        status.textContent = (res.json && res.json.message) || 'Liste alınamadı.';
        return;
      }
      var data = (res.json && res.json.data) || {};
      var items = data.games || data.items || [];
      var pagination = data.pagination || {};
      bag.hasNext = !!(pagination.hasNext || data.hasNext);
      bag.loaded = true;
      renderGames(kind, items, append);
      status.hidden = true;
      grid.hidden = false;
      more.hidden = !bag.hasNext;
      if (!items.length && !append) {
        status.hidden = false;
        status.className = 'tg-status';
        status.textContent = bag.q ? 'Arama sonucu yok.' : 'Oyun bulunamadı.';
        grid.hidden = true;
      }
    }).catch(function (err) {
      status.hidden = false;
      status.className = 'tg-status is-error';
      status.textContent = err.message || 'Bağlantı hatası.';
    });
  }

  function launchGame(gameId, name) {
    if (!gameId) return;
    toast((name || 'Oyun') + ' açılıyor…');
    if (tg && tg.HapticFeedback) {
      try { tg.HapticFeedback.impactOccurred('light'); } catch (e) {}
    }
    var isGsc = String(gameId).indexOf('gsc:') === 0;
    request('/game-launch', {
      method: 'POST',
      body: {
        game_id: gameId,
        mode: 'real',
        wallet: 'main',
        open_mode: isGsc ? 'redirect' : 'iframe'
      }
    }).then(function (res) {
      var data = (res.json && res.json.data) || {};
      var url = String(data.game_url || data.launch_url || data.url || '').trim();
      var openMode = String(data.open_mode || (isGsc ? 'redirect' : 'iframe')).toLowerCase();
      if (!res.ok || !url) {
        toast((res.json && res.json.message) || 'Oyun açılamadı');
        return;
      }
      // GSC/Pragmatic efinity shells iframe'de kırılır — Telegram in-app browser.
      if (openMode === 'redirect' || isGsc) {
        if (tg && typeof tg.openLink === 'function') {
          try { tg.openLink(url); return; } catch (e2) {}
        }
        window.location.href = url;
        return;
      }
      openOverlay(url, name || 'Oyun', { kind: 'game' });
    }).catch(function (err) {
      toast(err.message || 'Oyun açılamadı');
    });
  }

  function launchSport() {
    els.sportHint.textContent = 'Sporbook hazırlanıyor…';
    els.sportLaunch.disabled = true;
    return request('/sportsbook/launch', { method: 'POST', body: {} }).then(function (res) {
      els.sportLaunch.disabled = false;
      var data = (res.json && res.json.data) || {};
      var url = data.url || data.launch_url || data.game_url || '';
      if (!res.ok || !url) {
        els.sportHint.textContent = (res.json && res.json.message) || 'Spor açılamadı.';
        toast(els.sportHint.textContent);
        return;
      }
      els.sportHint.textContent = '';
      openOverlay(String(url), 'Spor Bahisleri', { kind: 'sport' });
    }).catch(function (err) {
      els.sportLaunch.disabled = false;
      els.sportHint.textContent = err.message || 'Spor açılamadı.';
      toast(els.sportHint.textContent);
    });
  }

  function bindUi() {
    document.body.addEventListener('click', function (ev) {
      var goto = ev.target.closest('[data-goto]');
      if (goto) {
        setPanel(goto.getAttribute('data-goto'));
        return;
      }
    });
    els.nav.addEventListener('click', function (ev) {
      var btn = ev.target.closest('button[data-goto]');
      if (btn) setPanel(btn.getAttribute('data-goto'));
    });
    els.slotsMore.addEventListener('click', function () {
      if (!state.slots.hasNext) return;
      state.slots.page += 1;
      loadGames('slots', true);
    });
    els.liveMore.addEventListener('click', function () {
      if (!state.live.hasNext) return;
      state.live.page += 1;
      loadGames('live', true);
    });
    els.balanceBtn.addEventListener('click', function () {
      refreshBalance().then(function () { toast('Bakiye güncellendi'); });
    });
    document.getElementById('tgRefreshWinners').addEventListener('click', loadWinners);
    els.sportLaunch.addEventListener('click', launchSport);
    if (els.depSubmit) els.depSubmit.addEventListener('click', submitDeposit);
    if (els.wdrSubmit) els.wdrSubmit.addEventListener('click', submitWithdraw);
    if (els.overlayClose) els.overlayClose.addEventListener('click', closeOverlay);
    if (els.overlayRefresh) els.overlayRefresh.addEventListener('click', refreshOverlay);
    document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible') resumePendingDeposit();
    });
    window.addEventListener('focus', function () { resumePendingDeposit(); });
    els.search.addEventListener('input', function () {
      clearTimeout(state.searchTimer);
      state.searchTimer = setTimeout(function () {
        var q = String(els.search.value || '').trim();
        if (state.panel === 'live') {
          state.live.q = q;
          state.live.loaded = false;
          loadGames('live', false);
        } else {
          state.slots.q = q;
          state.slots.loaded = false;
          loadGames('slots', false);
        }
      }, 320);
    });
  }

  if (tg) {
    try {
      tg.ready();
      tg.expand();
      if (tg.disableVerticalSwipes) tg.disableVerticalSwipes();
      if (tg.setHeaderColor) tg.setHeaderColor('#661760');
      if (tg.setBackgroundColor) tg.setBackgroundColor('#0e0124');
      if (tg.MainButton) tg.MainButton.hide();
    } catch (e) {}
  }

  bindUi();

  // Deep link: /tg#account | #deposit | #withdraw | #profile | #promos | #slots...
  var hash = String(window.location.hash || '').replace(/^#/, '');
  if (hash && ['home', 'slots', 'live', 'sport', 'account', 'deposit', 'withdraw', 'profile', 'promos'].indexOf(hash) !== -1) {
    setPanel(hash);
  }

  auth()
    .then(function () {
      return Promise.all([refreshBalance(), loadWinners(), loadHomeFeatured()]);
    })
    .then(function () {
      return null;
    })
    .catch(function (err) {
      els.user.textContent = 'Giriş hatası';
      if (els.welcomeName) els.welcomeName.textContent = 'Giriş gerekli';
      toast(err.message || 'Giriş başarısız');
      if (els.homeFeaturedStatus) {
        els.homeFeaturedStatus.textContent = err.message || 'Telegram girişi başarısız. /start ile tekrar deneyin.';
      }
    });
})();
