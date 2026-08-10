/**
 * Optional runtime usage probe for dynamic JS (profile.js, header.js, ...).
 *
 * Enable:
 *   localStorage.setItem('JS_USAGE_PROBE', '1'); location.reload();
 * Or:  ?js_usage=1
 *
 * Dump:
 *   copy(JSON.stringify(window.__JS_USAGE_DUMP__(), null, 2))
 * Then save as tools/reports/js-runtime-usage.json
 *
 * Disable:
 *   localStorage.removeItem('JS_USAGE_PROBE')
 *
 * @dynamic-file
 */
(function (w) {
    'use strict';

    function enabled() {
        try {
            if (w.localStorage && w.localStorage.getItem('JS_USAGE_PROBE') === '1') return true;
        } catch (e) {}
        try {
            return /(?:^|[?&])js_usage=1(?:&|$)/.test(String(w.location && w.location.search || ''));
        } catch (e2) {
            return false;
        }
    }

    if (!enabled()) return;

    var hits = Object.create(null); // fileGuess -> { name: true }
    var globalHits = Object.create(null);

    function guessFile() {
        // Best-effort: currentScript or 'unknown'
        try {
            if (document.currentScript && document.currentScript.src) {
                var u = document.currentScript.src.split('?')[0];
                var idx = u.indexOf('/assets/');
                if (idx >= 0) return u.slice(idx + 1);
                idx = u.indexOf('/mobile/');
                if (idx >= 0) return u.slice(idx + 1);
                return u;
            }
        } catch (e) {}
        return 'unknown';
    }

    function mark(name, file) {
        if (!name || typeof name !== 'string') return;
        if (name.length < 3 || name.length > 80) return;
        globalHits[name] = true;
        var f = file || guessFile();
        if (!hits[f]) hits[f] = Object.create(null);
        hits[f][name] = true;
    }

    // Wrap future window function assignments
    var pending = Object.create(null);
    function wrapAssignable(name, value) {
        if (typeof value !== 'function') return value;
        if (value.__jsUsageWrapped) return value;
        var wrapped = function () {
            mark(name, 'window');
            mark(value.name || name, 'window');
            return value.apply(this, arguments);
        };
        wrapped.__jsUsageWrapped = true;
        try {
            Object.defineProperty(wrapped, 'name', { value: value.name || name, configurable: true });
        } catch (e) {}
        return wrapped;
    }

    // Proxy-ish: intercept defines on window for known pattern via periodic scan
    function scanWindowExports() {
        var keys;
        try {
            keys = Object.keys(w);
        } catch (e) {
            return;
        }
        for (var i = 0; i < keys.length; i++) {
            var k = keys[i];
            if (!k || k.indexOf('__') === 0 && k.length < 6) continue;
            var v = w[k];
            if (typeof v !== 'function') continue;
            if (v.__jsUsageWrapped) continue;
            // Only wrap project-looking names / double-underscore APIs
            if (!(k.indexOf('__') === 0 || /^[a-z][A-Za-z0-9]+$/.test(k) || /^[A-Z]/.test(k))) continue;
            if (pending[k]) continue;
            try {
                w[k] = wrapAssignable(k, v);
                pending[k] = true;
            } catch (err) {}
        }
    }

    w.__JS_USAGE_MARK__ = function (name, file) {
        mark(name, file || 'manual');
    };

    w.__JS_USAGE_DUMP__ = function () {
        var files = {};
        Object.keys(hits).forEach(function (f) {
            files[f] = Object.keys(hits[f]).sort();
        });
        // Also expose flat window hits under a synthetic bucket
        files['window'] = Object.keys(globalHits).sort();
        return {
            generated_at: new Date().toISOString(),
            note: 'Paste into tools/reports/js-runtime-usage.json (map window hits into real file keys as needed)',
            files: files
        };
    };

    function boot() {
        scanWindowExports();
        setInterval(scanWindowExports, 2500);
        try {
            console.info('[js-usage-probe] enabled — dump via copy(JSON.stringify(__JS_USAGE_DUMP__(),null,2))');
        } catch (e) {}
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window);
