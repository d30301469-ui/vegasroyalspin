/**
 * Profile payments domain.
 * Owns payment method, deposit and withdraw endpoints.
 */
(function (w) {
    'use strict';

    var Api = w.MaltabetProfileApi || {};

    var moduleApi = {
        endpoints: {
            methods: '/api/v2/payment-methods',
            deposit: '/api/v2/deposit-payment',
            withdraw: '/api/v2/withdraw-payment',
            balance: '/api/v2/balance'
        },
        get: function (endpoint) {
            var path = this.endpoints[endpoint] || endpoint;
            return Api.fetchJson ? Api.fetchJson(path) : Promise.reject(new Error('Profile API helper is not loaded.'));
        },
        submit: function (endpoint, payload) {
            var path = this.endpoints[endpoint] || endpoint;
            return Api.postJson ? Api.postJson(path, payload) : Promise.reject(new Error('Profile API helper is not loaded.'));
        }
    };

    w.MaltabetProfilePayments = Api.registerDomain ? Api.registerDomain('payments', moduleApi) : moduleApi;
})(window);
