/**
 * Shared profile API helpers.
 *
 * This file is the first extraction point from profile.js. Feature modules
 * should use this namespace instead of redefining URL, auth header and fetch
 * helpers locally.
 */
(function (w) {
    'use strict';

    var Shared = w.BetcoAuthShared || {};

    function apiUrl(path) {
        return Shared.apiUrl ? Shared.apiUrl(path) : path;
    }

    function appendQuery(url, query) {
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + query;
    }

    function memberAuthHeaders(extra) {
        if (Shared.memberAuthHeaders) {
            return Shared.memberAuthHeaders(extra);
        }
        var headers = extra || {};
        var csrf = (w.__CSRF_TOKEN__ || '').trim();
        if (csrf) headers['X-CSRF-Token'] = csrf;
        return headers;
    }

    function memberCredentials() {
        return Shared.memberCredentials ? Shared.memberCredentials() : 'same-origin';
    }

    function toastNotify(type, message, title) {
        var msg = String(message || '').trim();
        if (!msg) {
            return;
        }
        if (w.MaltabetToast) {
            w.MaltabetToast.show(msg, { type: type || 'info', title: title });
        } else {
            alert(title ? title + ': ' + msg : msg);
        }
    }

    function fetchJson(path, options) {
        var requestOptions = options || {};
        var creds = requestOptions.credentials;
        requestOptions.credentials = (!creds || creds === 'same-origin')
            ? memberCredentials()
            : creds;
        requestOptions.headers = requestOptions.headers || memberAuthHeaders({ Accept: 'application/json' });

        return fetch(apiUrl(path), requestOptions).then(function (response) {
            return response.text().then(function (text) {
                var json = null;
                try {
                    json = text ? JSON.parse(String(text).replace(/^\uFEFF/, '').trim()) : null;
                } catch (error) {
                    json = null;
                }
                return {
                    ok: response.ok,
                    status: response.status,
                    data: json,
                    text: text
                };
            });
        });
    }

    function postJson(path, payload, extraHeaders) {
        return fetchJson(path, {
            method: 'POST',
            headers: memberAuthHeaders(Object.assign({
                Accept: 'application/json',
                'Content-Type': 'application/json'
            }, extraHeaders || {})),
            body: JSON.stringify(payload || {})
        });
    }

    var domains = {};

    function registerDomain(name, moduleApi) {
        var key = String(name || '').trim();
        if (!key || !moduleApi) {
            return moduleApi;
        }
        domains[key] = moduleApi;
        return moduleApi;
    }

    w.MaltabetProfileApi = {
        apiUrl: apiUrl,
        appendQuery: appendQuery,
        memberAuthHeaders: memberAuthHeaders,
        memberCredentials: memberCredentials,
        toastNotify: toastNotify,
        fetchJson: fetchJson,
        postJson: postJson,
        registerDomain: registerDomain,
        domains: domains
    };
})(window);
