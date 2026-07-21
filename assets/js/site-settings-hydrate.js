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

    function apply(settings) {
        if (!settings || typeof settings !== 'object') {
            return;
        }
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
