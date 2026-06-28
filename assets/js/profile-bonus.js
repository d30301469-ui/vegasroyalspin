/**
 * Profile bonus domain.
 * Owns active bonus, claim, promo code and referral endpoints.
 */
(function (w) {
    'use strict';

    var Api = w.MaltabetProfileApi || {};

    var moduleApi = {
        endpoints: {
            active: '/api/v2/active-bonus',
            claims: '/api/v2/bonus-claims-me',
            claim: '/api/v2/bonus-claim',
            useCode: '/api/v2/bonus/use-code',
            promocodes: '/api/v2/promocodes',
            promocodeRequest: '/api/v2/promocode-request',
            referrals: '/api/v2/referrals'
        },
        get: function (endpoint) {
            var path = this.endpoints[endpoint] || endpoint;
            return Api.fetchJson ? Api.fetchJson(path) : Promise.reject(new Error('Profile API helper is not loaded.'));
        },
        submit: function (endpoint, payload) {
            var path = this.endpoints[endpoint] || endpoint;
            return Api.postJson ? Api.postJson(path, payload) : Promise.reject(new Error('Profile API helper is not loaded.'));
        },
        notify: function (type, message, title) {
            if (Api.toastNotify) {
                Api.toastNotify(type, message, title);
            }
        }
    };

    w.MaltabetProfileBonus = Api.registerDomain ? Api.registerDomain('bonus', moduleApi) : moduleApi;
})(window);
