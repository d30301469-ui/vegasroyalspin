(function () {
  'use strict';

  function getPanel() { return document.getElementById('mprofilePanel'); }
  function getOverlay() { return document.getElementById('mprofileOverlay'); }
  var Shared = window.BetcoAuthShared || {};

  var isOpen = false;
  var balanceMethodsLoaded = false;
  var withdrawMethodsLoaded = false;

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
    var balanceSection = requestedBalanceSection();
    if (balanceSection) {
      showBalancePage(panel, balanceSection);
    } else {
      var section = requestedProfileSection();
      if (section) showProfileDetails(panel, section);
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

  function showProfileMenu(panel) {
    panel = panel || getPanel();
    if (!panel) return;
    panel.classList.remove('mprofile-detail-active');
    panel.classList.remove('mprofile-balance-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'true');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'true');
  }

  function showProfileDetails(panel, sectionName) {
    panel = panel || getPanel();
    if (!panel) return;
    sectionName = sectionName || 'details';
    panel.classList.add('mprofile-detail-active');
    panel.classList.remove('mprofile-balance-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'false');
    var balance = panel.querySelector('[data-mprofile-view="balance"]');
    if (balance) balance.setAttribute('aria-hidden', 'true');
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
    if (!methods || !methods.length) {
      grid.innerHTML = '<p class="dw-methods-empty" role="status">Şu an ' + (kind === 'withdraw' ? 'çekim' : 'para yatırma') + ' için listelenen yöntem bulunmuyor.</p>';
      return;
    }
    grid.innerHTML = methods.map(function (method) {
      var logo = method.logo_url && String(method.logo_url).trim() ? String(method.logo_url).trim() : '';
      var methodId = String(method.method_id || method.id || '').trim();
      var category = balanceCategory(method);
      var cls = (kind === 'withdraw' ? 'withdraw_' : 'deposit_') + (methodId || 'method').toLowerCase().replace(/[^a-z0-9_-]+/g, '');
      var attr = kind === 'withdraw' ? 'data-mbalance-withdraw-method' : 'data-mbalance-method';
      return '<div class="m-nav-items-list-item-bc ' + escapeHtml(cls) + '" ' + attr + ' data-category="' + escapeHtml(category) + '"><div class="nav-ico-w-row-bc">' +
        (logo ? '<img alt="" loading="lazy" decoding="async" src="' + escapeHtml(logo) + '" class="payment-logo">' : '<span class="payment-logo payment-logo--text">' + escapeHtml(method.name || methodId || 'Ödeme') + '</span>') +
        '</div></div>';
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
    panel.classList.add('mprofile-balance-active');
    var detail = panel.querySelector('[data-mprofile-view="details"]');
    if (detail) detail.setAttribute('aria-hidden', 'true');
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
      if (initialBalanceSection) {
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
          window.location.href = menuItem.getAttribute('data-href');
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
        }
      });

      panel.addEventListener('change', function (e) {
        var target = e.target && e.target.closest ? e.target : null;
        var twofaToggle = target && target.closest('#mprofileTwofaToggle');
        if (twofaToggle) submitTwofaToggle(panel, twofaToggle);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }
})();
