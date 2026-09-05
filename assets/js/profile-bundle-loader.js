/**
 * Lazy-loads profile JS modules on non-profile pages so casino/sportbook
 * navigations avoid parsing ~400KB of profile code up front.
 */
(function (w, d) {
    'use strict';

    if (w.__ProfileBundleLoader) {
        return;
    }

    var bundleScripts = Array.isArray(w.__PROFILE_BUNDLE_SCRIPTS__) ? w.__PROFILE_BUNDLE_SCRIPTS__ : [];
    var bundleStyles = Array.isArray(w.__PROFILE_BUNDLE_STYLES__) ? w.__PROFILE_BUNDLE_STYLES__ : [];
    var state = { promise: null, loaded: false, pendingUrl: null };

    function loadStylesheet(href) {
        return new Promise(function (resolve) {
            if (!href) {
                resolve();
                return;
            }
            if (d.querySelector('link[rel="stylesheet"][href="' + href.replace(/"/g, '\\"') + '"]')) {
                resolve();
                return;
            }
            var link = d.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.onload = function () { resolve(); };
            link.onerror = function () { resolve(); };
            d.head.appendChild(link);
        });
    }

    function loadStylesSequential(urls) {
        return urls.reduce(function (chain, url) {
            return chain.then(function () { return loadStylesheet(url); });
        }, Promise.resolve());
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            if (!src) {
                resolve();
                return;
            }
            if (d.querySelector('script[data-profile-bundle="' + src + '"]')) {
                resolve();
                return;
            }
            var s = d.createElement('script');
            s.src = src;
            s.async = false;
            s.setAttribute('data-profile-bundle', src);
            s.onload = function () { resolve(); };
            s.onerror = function () { reject(new Error('profile bundle script failed: ' + src)); };
            d.head.appendChild(s);
        });
    }

    function loadSequential(urls) {
        return urls.reduce(function (chain, url) {
            return chain.then(function () { return loadScript(url); });
        }, Promise.resolve());
    }

    function flushPendingUrl() {
        var pending = state.pendingUrl;
        state.pendingUrl = null;
        if (!pending || typeof w.__openProfileModalUrl !== 'function') {
            return;
        }
        w.__openProfileModalUrl(pending);
    }

    function ensureLoaded() {
        if (state.loaded) {
            return Promise.resolve();
        }
        if (state.promise) {
            return state.promise;
        }
        state.promise = loadStylesSequential(bundleStyles).then(function () {
            return loadSequential(bundleScripts);
        }).then(function () {
            state.loaded = true;
            flushPendingUrl();
            try {
                d.dispatchEvent(new CustomEvent('profile-bundle-ready'));
            } catch (eEvt) { /* ignore */ }
        }).catch(function () {
            state.promise = null;
        });
        return state.promise;
    }

    function stubOpenProfileModalUrl(url) {
        if (typeof w.__profileModalUrlReal === 'function') {
            return w.__profileModalUrlReal(url);
        }
        state.pendingUrl = url;
        ensureLoaded();
        return true;
    }

    w.__openProfileModalUrl = stubOpenProfileModalUrl;
    w.__ensureProfileBundle = ensureLoaded;

    function isProfileModalLink(link) {
        if (!link) {
            return false;
        }
        var mode = (link.getAttribute('data-nav-mode') || '').trim().toLowerCase();
        if (mode === 'modal') {
            return true;
        }
        if (link.id === 'profileLinkModal') {
            return true;
        }
        var href = (link.getAttribute('data-profile-modal-href') || link.getAttribute('href') || '').trim();
        if (!href || href === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }
        try {
            return new URL(href, w.location.origin).pathname.indexOf('/profile/') === 0;
        } catch (eUrl) {
            return false;
        }
    }

    d.addEventListener('click', function (e) {
        if (state.loaded) {
            return;
        }
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var link = e.target && e.target.closest ? e.target.closest('a[href], [data-profile-modal-href]') : null;
        if (!link || !isProfileModalLink(link)) {
            return;
        }
        if ((link.getAttribute('target') || '').toLowerCase() === '_blank') {
            return;
        }
        var raw = (link.getAttribute('data-profile-modal-href') || link.getAttribute('href') || '').trim();
        if (!raw || raw === '#') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        stubOpenProfileModalUrl(raw);
    }, true);

    if (w.__PROFILE_BUNDLE_IDLE__) {
        var schedule = typeof w.requestIdleCallback === 'function'
            ? function (fn) { w.requestIdleCallback(fn, { timeout: 4000 }); }
            : function (fn) { w.setTimeout(fn, 3000); };
        schedule(function () { ensureLoaded(); });
    }

    w.__ProfileBundleLoader = { ensureLoaded: ensureLoaded };
})(window, document);
