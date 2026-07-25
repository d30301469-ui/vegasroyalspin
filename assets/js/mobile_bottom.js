(function () {
    'use strict';
    /**
     * LEGACY SHIM — menü mantığı mobile/assets/js/navigation.js dosyasında
     * konsolide edildi (tek menü motoru). Bu dosya yalnızca eski önbellekte
     * kalmış HTML'in (sadece mobile_bottom.js yükleyen sayfalar) menüsüz
     * kalmaması için navigation.js'i dinamik yükler. Yeni layout'lar
     * navigation.js'i doğrudan yükler ve bu dosyaya referans vermez.
     */
    if (window.__MOBILE_NAV_ACTIVE__ || window.__MOBILE_NAV_LOADING__) {
        return;
    }
    window.__MOBILE_NAV_LOADING__ = true;

    var script = document.createElement('script');
    script.src = '/mobile/assets/js/navigation.js';
    script.async = false;
    (document.head || document.documentElement).appendChild(script);
})();
