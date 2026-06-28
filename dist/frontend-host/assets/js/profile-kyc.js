/**
 * Profile KYC domain.
 * Keeps identity/document endpoints isolated even when KYC UI is embedded in profile.js.
 */
(function (w) {
    'use strict';

    var Api = w.MaltabetProfileApi || {};

    var moduleApi = {
        endpoints: {
            profile: '/api/v2/profile/detail',
            updateProfile: '/api/v2/profile/update'
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

    w.MaltabetProfileKyc = Api.registerDomain ? Api.registerDomain('kyc', moduleApi) : moduleApi;
})(window);
