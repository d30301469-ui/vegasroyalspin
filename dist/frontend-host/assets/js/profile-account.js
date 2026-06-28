/**
 * Profile account domain.
 * Owns account/profile/password/two-factor/freeze endpoints for future UI moves.
 */
(function (w) {
    'use strict';

    var Api = w.MaltabetProfileApi || {};

    var moduleApi = {
        endpoints: {
            detail: '/api/v2/profile/detail',
            update: '/api/v2/profile/update',
            password: '/api/v2/account/password',
            twoFactor: '/api/v2/two-factor',
            freeze: '/api/v2/account-freeze',
            unfreeze: '/api/v2/account-unfreeze'
        },
        request: function (endpoint, payload) {
            var path = this.endpoints[endpoint] || endpoint;
            return Api.postJson ? Api.postJson(path, payload) : Promise.reject(new Error('Profile API helper is not loaded.'));
        },
        notify: function (type, message, title) {
            if (Api.toastNotify) {
                Api.toastNotify(type, message, title);
            }
        }
    };

    w.MaltabetProfileAccount = Api.registerDomain ? Api.registerDomain('account', moduleApi) : moduleApi;
})(window);
