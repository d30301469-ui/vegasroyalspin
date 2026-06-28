/**
 * Bootstrap modal yerine kullanılan polyfill — data-dismiss / data-bs-dismiss ve jQuery .modal('show'/'hide') destekler.
 * Bootstrap JS kaldırıldığı için bu script head'de yüklenir.
 * window.global: Eski Angular polyfills kaldırıldığı için bazı kütüphanelerin Node-style global beklentisi burada sağlanıyor.
 */
(function() {
  'use strict';
  if (typeof window !== 'undefined' && typeof window.global === 'undefined') {
    window.global = window;
  }

  function getModal(el) {
    if (!el) return null;
    var target = el.closest && el.closest('.modal');
    if (target) return target;
    var id = (el.getAttribute && (el.getAttribute('data-bs-dismiss') || el.getAttribute('data-dismiss')));
    if (id === 'modal') {
      var p = el.parentElement;
      while (p && !p.classList.contains('modal')) p = p.parentElement;
      return p;
    }
    return null;
  }

  function showModal(modalEl) {
    if (!modalEl || !modalEl.classList) return;
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.setAttribute('data-modal-backdrop', '');
    modalEl.parentNode.insertBefore(backdrop, modalEl);
  }

  function hideModal(modalEl) {
    if (!modalEl || !modalEl.classList) return;
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    var parent = modalEl.parentNode;
    if (parent) {
      var backdrops = parent.querySelectorAll('[data-modal-backdrop]');
      for (var i = 0; i < backdrops.length; i++) backdrops[i].remove();
    }
    if (typeof window.jQuery !== 'undefined') {
      window.jQuery(modalEl).trigger('hidden.bs.modal');
    }
  }

  document.addEventListener('click', function(e) {
    var el = e.target;
    if (el.hasAttribute && (el.getAttribute('data-bs-dismiss') === 'modal' || el.getAttribute('data-dismiss') === 'modal')) {
      var modal = getModal(el);
      if (modal) { e.preventDefault(); hideModal(modal); }
      return;
    }
    if (el.classList && el.classList.contains('btn-close')) {
      var m = getModal(el);
      if (m) { e.preventDefault(); hideModal(m); }
    }
    // Dışarı (backdrop) tıklanınca modalı kapat — backdrop elementine tıklanırsa
    if (el.hasAttribute && el.getAttribute('data-modal-backdrop') !== null) {
      var modalToClose = el.nextElementSibling;
      if (modalToClose && modalToClose.classList && modalToClose.classList.contains('modal')) {
        e.preventDefault();
        hideModal(modalToClose);
      }
    }
    // Dışarı tıklanınca kapat — tıklanan doğrudan .modal overlay ise (içerik kutusu değil)
    if (el.classList && el.classList.contains('modal') && el.classList.contains('show') && e.target === el) {
      e.preventDefault();
      hideModal(el);
    }
  }, true);

  window.showModalById = function(id) {
    var el = document.getElementById(id);
    if (el) showModal(el);
  };
  window.hideModalById = function(id) {
    var el = document.getElementById(id);
    if (el) hideModal(el);
  };

  if (typeof window.jQuery !== 'undefined') {
    window.jQuery.fn.modal = function(action) {
      return this.each(function() {
        if (action === 'show') showModal(this);
        else if (action === 'hide') hideModal(this);
      });
    };
  }
})();
