/**
 * Site branding hydration — site title, logo ve favicon her zaman API'den
 * (guncel veri) gelir. Sunucu tarafi render onbellek zarfindan gelse bile,
 * bu modul sayfa yuklenince /api/v2/site-settings ucundan taze veriyi cekip
 * DOM'u gunceller. Boylece logo/baslik guncel kalir.
 */
(function (w, d) {
    'use strict';

    var API = (typeof w.__SITE_SETTINGS_API__ === 'string' && w.__SITE_SETTINGS_API__ !== '')
        ? w.__SITE_SETTINGS_API__
        : '/api/v2/site-settings';

    function text(value) {
        return value == null ? '' : String(value).trim();
    }

    function branding(settings) {
        return (settings && typeof settings.branding === 'object' && settings.branding) || {};
    }

    function meta(settings) {
        return (settings && typeof settings.meta === 'object' && settings.meta) || {};
    }

    function pickLogo(settings) {
        var b = branding(settings);
        return text(b.logo_url) || text(settings.logo_url) || text(settings.site_logo);
    }

    function pickAnimatedLogo(settings) {
        var b = branding(settings);
        return text(b.logo_animated_url) || text(settings.logo_animated_url);
    }

    function pickName(settings) {
        var b = branding(settings);
        return text(b.site_name) || text(settings.site_adi) || text(settings.site_name);
    }

    function pickTitle(settings) {
        var m = meta(settings);
        return text(m.title) || text(settings.meta_title) || text(settings.site_title);
    }

    function pickFavicon(settings) {
        var b = branding(settings);
        return text(b.favicon_url) || text(settings.favicon_url);
    }

    function logoLinks() {
        var seen = [];
        var selectors = ['a[data-site-logo-link]', 'img[data-site-logo-link]', 'a.headLogo', '.logo-container a.logo'];
        for (var i = 0; i < selectors.length; i++) {
            var nodes = d.querySelectorAll(selectors[i]);
            for (var j = 0; j < nodes.length; j++) {
                if (seen.indexOf(nodes[j]) === -1) {
                    seen.push(nodes[j]);
                }
            }
        }
        return seen;
    }

    function applyLogo(url, animatedUrl, name) {
        if (!url && !animatedUrl) {
            return;
        }
        var links = logoLinks();
        for (var i = 0; i < links.length; i++) {
            var link = links[i];

            // Marker is directly on the <img> (no wrapping link/video structure),
            // e.g. footer logo or login modal logo.
            if (link.tagName === 'IMG') {
                if (url && link.getAttribute('src') !== url) {
                    link.setAttribute('src', url);
                }
                if (name) {
                    link.setAttribute('alt', name);
                }
                continue;
            }

            var video = link.querySelector('video');
            if (video) {
                if (animatedUrl) {
                    var source = video.querySelector('source');
                    if (source && source.getAttribute('src') !== animatedUrl) {
                        source.setAttribute('src', animatedUrl);
                    }
                }
                var fallbackImg = video.querySelector('img');
                if (fallbackImg && url && fallbackImg.getAttribute('src') !== url) {
                    fallbackImg.setAttribute('src', url);
                }
                if (fallbackImg && name) {
                    fallbackImg.setAttribute('alt', name);
                }
                continue;
            }
            var img = link.querySelector('img.hdr-logo-bc') || link.querySelector('img');
            if (!img && url) {
                img = d.createElement('img');
                img.className = 'hdr-logo-bc';
                link.appendChild(img);
            }
            if (img && url && img.getAttribute('src') !== url) {
                img.setAttribute('src', url);
            }
            if (img && name) {
                img.setAttribute('alt', name);
            }
        }
    }

    function applyTitle(title) {
        if (title && d.title !== title) {
            d.title = title;
        }
    }

    function applyFavicon(url) {
        if (!url) {
            return;
        }
        var link = d.getElementById('appFavicon');
        if (link && link.getAttribute('href') !== url) {
            link.setAttribute('href', url);
        }
    }

    function settingsRoot(settings) {
        if (!settings || typeof settings !== 'object') {
            return null;
        }
        if (settings.site_settings && typeof settings.site_settings === 'object') {
            return settings.site_settings;
        }
        return settings;
    }

    function truthyFlag(value) {
        if (value === true || value === 1 || value === '1') {
            return true;
        }
        var normalized = String(value == null ? '' : value).trim().toLowerCase();
        return normalized === 'true' || normalized === 'on' || normalized === 'yes';
    }

    /**
     * Turnstile widget bayraklari sayfa bootstrap'inden gelir; stale CMS cache
     * nedeniyle kapali kalabiliyor. API hydrate sonrasi globals'i senkronize et.
     */
    function syncTurnstile(settings) {
        var root = settingsRoot(settings);
        if (!root) {
            return;
        }
        var enabled = truthyFlag(root.turnstile_enabled != null ? root.turnstile_enabled : settings.turnstile_enabled);
        var siteKey = text(root.turnstile_site_key != null ? root.turnstile_site_key : settings.turnstile_site_key);
        var prevEnabled = w.__TURNSTILE_ENABLED__ === true || w.__TURNSTILE_ENABLED__ === 1 || w.__TURNSTILE_ENABLED__ === '1';
        var prevKey = typeof w.__TURNSTILE_SITE_KEY__ === 'string' ? w.__TURNSTILE_SITE_KEY__.trim() : '';
        w.__TURNSTILE_ENABLED__ = enabled ? 1 : 0;
        w.__TURNSTILE_SITE_KEY__ = siteKey;
        // Secret asla client'a tasinmasin.
        if (w.__SITE_SETTINGS__ && typeof w.__SITE_SETTINGS__ === 'object') {
            try {
                delete w.__SITE_SETTINGS__.turnstile_secret_key;
                if (w.__SITE_SETTINGS__.site_settings && typeof w.__SITE_SETTINGS__.site_settings === 'object') {
                    delete w.__SITE_SETTINGS__.site_settings.turnstile_secret_key;
                }
            } catch (eDel) {
                /* ignore */
            }
        }
        // Stale bootstrap false idi, API true geldiyse acik modalda widget'i yeniden dene.
        if (enabled && siteKey !== '' && (!prevEnabled || prevKey !== siteKey)) {
            if (typeof w.__ensureLoginTurnstileWidget === 'function') {
                w.setTimeout(w.__ensureLoginTurnstileWidget, 50);
            }
            if (typeof w.__ensureRegisterTurnstileWidget === 'function') {
                w.setTimeout(w.__ensureRegisterTurnstileWidget, 50);
            }
        }
    }

    function apply(settings) {
        if (!settings || typeof settings !== 'object') {
            return;
        }
        syncTurnstile(settings);
        applyLogo(pickLogo(settings), pickAnimatedLogo(settings), pickName(settings));
        applyTitle(pickTitle(settings));
        applyFavicon(pickFavicon(settings));
    }

    function refresh() {
        if (typeof w.fetch !== 'function') {
            return;
        }
        w.fetch(API, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' }
        }).then(function (res) {
            return res && res.ok ? res.json() : null;
        }).then(function (json) {
            if (!json || typeof json !== 'object') {
                return;
            }
            var data = (json.data && typeof json.data === 'object') ? json.data : json;
            w.__SITE_SETTINGS__ = data;
            apply(data);
        }).catch(function () {
            /* sessiz gec — sunucu render'i zaten bir deger gosteriyor */
        });
    }

    // 1) Gomulu ayarlardan aninda uygula (varsa).
    try {
        apply(w.__SITE_SETTINGS__);
    } catch (e) {
        /* ignore */
    }

    // 2) API'den taze veriyi cekip guncelle.
    if (d.readyState === 'loading') {
        d.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }
})(window, document);
