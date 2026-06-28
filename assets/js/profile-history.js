/**
 * Profile history domain.
 * Owns deposit, withdraw, casino and sports history endpoints.
 */
(function (w) {
    'use strict';

    var Api = w.MaltabetProfileApi || {};

    var moduleApi = {
        endpoints: {
            deposits: '/api/v2/deposit-history',
            withdraws: '/api/v2/withdraw-history',
            games: '/api/v2/game-history',
            casinoGames: '/api/v2/profile/casino-game-history',
            sportsDetail: '/api/v2/profile/spor-bet-detail',
            gameDetail: '/api/v2/profile/game-history-detail'
        },
        list: function (endpoint, query) {
            var path = this.endpoints[endpoint] || endpoint;
            if (query) {
                path = Api.appendQuery ? Api.appendQuery(path, query) : path + (path.indexOf('?') >= 0 ? '&' : '?') + query;
            }
            return Api.fetchJson ? Api.fetchJson(path) : Promise.reject(new Error('Profile API helper is not loaded.'));
        }
    };

    w.MaltabetProfileHistory = Api.registerDomain ? Api.registerDomain('history', moduleApi) : moduleApi;
})(window);
