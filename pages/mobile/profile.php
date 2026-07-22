<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/frontend_session.php';
    metropol_frontend_session_start();
}

require_once defined('BASE_PATH') ? BASE_PATH . '/core/bootstrap.php' : __DIR__ . '/../../core/bootstrap.php';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: /');
    exit;
}

$queryProfileOpen = trim((string) ($_GET['profile'] ?? ''));
$queryAccount = strtolower(trim((string) ($_GET['account'] ?? 'profile')));
$queryPage = strtolower(trim((string) ($_GET['page'] ?? 'details')));

$profilePageMap = [
    'profile|details' => ['file' => 'details.php', 'extra_get' => []],
    'profile|change-password' => ['file' => 'change-password.php', 'extra_get' => []],
    'profile|two-factor-authentication' => ['file' => 'two-factor.php', 'extra_get' => []],
    'profile|timeout-limits' => ['file' => 'freeze-account.php', 'extra_get' => []],

    'balance|deposit' => ['file' => 'deposit-withdraw.php', 'extra_get' => []],
    'balance|withdraw' => ['file' => 'withdraw.php', 'extra_get' => []],
    'balance|history' => ['file' => 'deposit-withdraw-history.php', 'extra_get' => []],
    'balance|info' => ['file' => 'deposit-withdraw.php', 'extra_get' => ['bilgi' => '1']],
    'balance|withdraws' => ['file' => 'withdrawal-status.php', 'extra_get' => []],

    'bet|history' => ['file' => 'bet-history.php', 'extra_get' => []],
    'bet|casino-history' => ['file' => 'casino-history.php', 'extra_get' => []],

    'bonuses|sports' => ['file' => 'bonus-spor.php', 'extra_get' => []],
    'bonuses|casino' => ['file' => 'bonus-casino.php', 'extra_get' => []],
    'bonuses|history' => ['file' => 'bonus-history.php', 'extra_get' => []],
    'bonuses|freespins' => ['file' => 'freespin.php', 'extra_get' => []],
    'bonuses|loyalty-points' => ['file' => 'sadakat-puanlari.php', 'extra_get' => []],

    'messages|inbox' => ['file' => 'messages.php', 'extra_get' => ['box' => 'inbox']],
    'messages|sent' => ['file' => 'messages.php', 'extra_get' => ['box' => 'sent']],
    'messages|new' => ['file' => 'messages.php', 'extra_get' => ['box' => 'new']],
];

$targetKey = $queryAccount . '|' . $queryPage;
$isBalanceHistoryRequest = ($queryProfileOpen === 'open' && $queryAccount === 'balance' && $queryPage === 'history' && (string) ($_GET['fromPayment'] ?? '') !== '1');
if ($isBalanceHistoryRequest) {
    header('Location: /mobile/profile?' . http_build_query(['profile' => 'open', 'account' => 'balance', 'page' => 'deposit', 'openDepositPanel' => '1']));
    exit;
}
$targetEntry = $profilePageMap[$targetKey] ?? $profilePageMap['profile|details'];
$targetProfileFile = (string) ($targetEntry['file'] ?? 'details.php');
$targetExtraGet = is_array($targetEntry['extra_get'] ?? null) ? $targetEntry['extra_get'] : [];

$targetProfilePath = BASE_PATH . '/pages/profile/' . $targetProfileFile;
if (!is_file($targetProfilePath)) {
    $targetProfilePath = BASE_PATH . '/pages/profile/details.php';
    $targetExtraGet = [];
}

$mobileHead = MOBILE_PATH . '/views/layouts/head.php';
if (is_file($mobileHead) && filesize($mobileHead) > 0) {
    include $mobileHead;
} else {
    include VIEW_PATH . '/layouts/head_full.php';
}

include MOBILE_PATH . '/views/partials/header.php';

echo '<div class="centerWrap porfileWrap">';

// Profile içeriklerini mobile sayfada modal fragment gibi render et.
$_GET['modal'] = '1';
$profile_modal = true;
foreach ($targetExtraGet as $k => $v) {
        $_GET[(string) $k] = (string) $v;
}
include $targetProfilePath;

echo '</div>';

?>
<script>
(function () {
    function canonicalMobileProfileUrl(account, page, extras) {
        var params = new URLSearchParams();
        params.set('profile', 'open');
        params.set('account', account);
        params.set('page', page);
        if (extras && typeof extras === 'object') {
            Object.keys(extras).forEach(function (key) {
                if (extras[key] == null || extras[key] === '') return;
                params.set(key, String(extras[key]));
            });
        }
        return '/mobile/profile?' + params.toString();
    }

    function canonicalizeLegacyMobilePath() {
        var path = (window.location.pathname || '').replace(/\/+$/, '');
        var search = new URLSearchParams(window.location.search || '');
        if (path === '/profile/deposit-withdraw-history') {
            window.location.replace(canonicalMobileProfileUrl('balance', 'deposit', { openDepositPanel: '1' }));
            return true;
        }
        if (path === '/profile/deposit-withdraw') {
            if (search.get('bilgi') === '1') {
                window.location.replace(canonicalMobileProfileUrl('balance', 'info'));
                return true;
            }
            if (search.get('openDepositPanel') === '1' || search.get('tab') === 'deposit') {
                window.location.replace(canonicalMobileProfileUrl('balance', 'deposit'));
                return true;
            }
        }
        return false;
    }

    if (canonicalizeLegacyMobilePath()) {
        return;
    }

    function mobileProfileUrl(account, page, extras) {
        var params = new URLSearchParams();
        params.set('profile', 'open');
        params.set('account', account);
        params.set('page', page);
        if (extras && typeof extras === 'object') {
            Object.keys(extras).forEach(function (key) {
                if (extras[key] == null || extras[key] === '') return;
                params.set(key, String(extras[key]));
            });
        }
        return '/mobile/profile?' + params.toString();
    }

    function mapProfilePathToMobileQuery(url) {
        var p = url.pathname || '';
        var q = url.searchParams;

        if (p === '/profile/details') return mobileProfileUrl('profile', 'details');
        if (p === '/profile/change-password') return mobileProfileUrl('profile', 'change-password');
        if (p === '/profile/two-factor') return mobileProfileUrl('profile', 'two-factor-authentication');
        if (p === '/profile/freeze-account') return mobileProfileUrl('profile', 'timeout-limits');

        if (p === '/profile/deposit-withdraw') {
            if (q.get('bilgi') === '1') return mobileProfileUrl('balance', 'info');
            return mobileProfileUrl('balance', 'deposit');
        }
        if (p === '/profile/withdraw') {
            if (q.get('bilgi') === '1') return mobileProfileUrl('balance', 'info');
            return mobileProfileUrl('balance', 'withdraw');
        }
        if (p === '/profile/deposit-withdraw-history') {
            if (q.get('fromPayment') === '1') return mobileProfileUrl('balance', 'history', { fromPayment: '1' });
            return mobileProfileUrl('balance', 'deposit', { openDepositPanel: '1' });
        }
        if (p === '/profile/withdrawal-status') return mobileProfileUrl('balance', 'withdraws');

        if (p === '/profile/bet-history') {
            var filter = q.get('filter') || '';
            return mobileProfileUrl('bet', 'history', filter ? { filter: filter } : null);
        }
        if (p === '/profile/casino-history') return mobileProfileUrl('bet', 'casino-history');

        if (p === '/profile/bonus-spor') return mobileProfileUrl('bonuses', 'sports');
        if (p === '/profile/bonus-casino') return mobileProfileUrl('bonuses', 'casino');
        if (p === '/profile/bonus-history') return mobileProfileUrl('bonuses', 'history');
        if (p === '/profile/freespin') return mobileProfileUrl('bonuses', 'freespins');
        if (p === '/profile/sadakat-puanlari') return mobileProfileUrl('bonuses', 'loyalty-points');

        if (p === '/profile/messages') {
            var box = q.get('box') || 'inbox';
            if (box !== 'inbox' && box !== 'sent' && box !== 'new') box = 'inbox';
            return mobileProfileUrl('messages', box);
        }

        return null;
    }

    document.addEventListener('click', function (e) {
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var link = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!link) return;

        var href = (link.getAttribute('href') || '').trim();
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
        if (href === '/logout' || link.getAttribute('data-nav-mode') === 'page') return;

        var area = link.closest('#profilePlayerSidebar, .profile-mobile-nav, .profile-accordion');
        if (!area) return;

        var url;
        try {
            url = new URL(href, window.location.origin);
        } catch (err) {
            return;
        }
        if (url.origin !== window.location.origin) return;

        var next = mapProfilePathToMobileQuery(url);
        if (!next) return;

        e.preventDefault();
        window.location.href = next;
    }, true);

})();
</script>
<?php

include MOBILE_PATH . '/views/partials/footer.php';
