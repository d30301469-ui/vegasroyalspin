<?php

declare(strict_types=1);

/**
 * Vegasroyalspin Telegram Mini App — full casino shell.
 */
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/frontend_session.php';
    frontend_session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';

$brand = 'Vegasroyalspin';
$assetVersion = (string) (@filemtime(__DIR__ . '/../assets/js/telegram-miniapp.js') ?: time());
$cssVersion = (string) (@filemtime(__DIR__ . '/../assets/css/telegram-miniapp.css') ?: time());

$tgBranding = [];
$tgAyar = [];
if (isset($siteBranding) && is_array($siteBranding)) {
    $tgBranding = $siteBranding;
} elseif (isset($GLOBALS['siteBranding']) && is_array($GLOBALS['siteBranding'])) {
    $tgBranding = $GLOBALS['siteBranding'];
}
if (isset($ayar) && is_array($ayar)) {
    $tgAyar = $ayar;
} elseif (isset($GLOBALS['ayar']) && is_array($GLOBALS['ayar'])) {
    $tgAyar = $GLOBALS['ayar'];
}
$tgLogoUrl = '';
if (function_exists('cms_asset_url')) {
    $tgLogoUrl = cms_asset_url((string) ($tgBranding['logo_url'] ?? $tgAyar['logo_url'] ?? ''));
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="theme-color" content="#120023">
    <title><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <link rel="stylesheet" href="/assets/css/telegram-miniapp.css?v=<?= htmlspecialchars($cssVersion, ENT_QUOTES, 'UTF-8') ?>">
    <?php include __DIR__ . '/../views/partials/member-api-layout-script.php'; ?>
</head>
<body class="tg-app">
    <header class="tg-top">
        <div class="tg-brand-block">
            <?php if ($tgLogoUrl !== ''): ?>
                <img class="tg-logo" src="<?= htmlspecialchars($tgLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?>" width="140" height="36">
            <?php endif; ?>
            <div class="tg-brand-text<?= $tgLogoUrl !== '' ? ' tg-brand-text--with-logo' : '' ?>">
                <?php if ($tgLogoUrl === ''): ?>
                    <div class="tg-brand"><?= htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <div class="tg-user" id="tgUser">Bağlanıyor…</div>
            </div>
        </div>
        <div class="tg-top-actions">
            <button type="button" class="tg-deposit" data-goto="deposit">Yatır</button>
            <button type="button" class="tg-chip" id="tgBalanceBtn" title="Bakiyeyi yenile">
                <span id="tgBalance">—</span>
            </button>
        </div>
    </header>

    <div class="tg-search-wrap" id="tgSearchWrap" hidden>
        <input class="tg-search" id="tgSearch" type="search" placeholder="Oyun veya sağlayıcı ara…" autocomplete="off" enterkeyhint="search">
    </div>

    <main class="tg-main" id="tgMain">
        <section class="tg-panel is-active" data-panel="home">
            <section class="tg-welcome" id="tgWelcome">
                <div class="tg-welcome-copy">
                    <p class="tg-welcome-hello">Hoş geldin</p>
                    <h1 class="tg-welcome-name" id="tgWelcomeName">—</h1>
                </div>
                <div class="tg-welcome-bal">
                    <span class="tg-welcome-bal-label">Bakiye</span>
                    <strong id="tgHomeBalance">—</strong>
                    <button type="button" class="tg-welcome-deposit" data-goto="deposit">Yatır</button>
                </div>
            </section>

            <section class="tg-products" aria-label="Kategoriler">
                <button type="button" class="tg-product tg-product--slots" data-goto="slots">
                    <span class="tg-product-glow" aria-hidden="true"></span>
                    <span class="tg-product-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
                    <span class="tg-product-copy">
                        <strong>Slotlar</strong>
                        <em>Binlerce oyun</em>
                    </span>
                    <span class="tg-product-cta">Aç</span>
                </button>
                <button type="button" class="tg-product tg-product--live" data-goto="live">
                    <span class="tg-product-glow" aria-hidden="true"></span>
                    <span class="tg-product-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2"/></svg></span>
                    <span class="tg-product-copy">
                        <strong>Canlı Casino</strong>
                        <em>Gerçek masalar</em>
                    </span>
                    <span class="tg-product-cta">Aç</span>
                </button>
                <button type="button" class="tg-product tg-product--sport" data-goto="sport">
                    <span class="tg-product-glow" aria-hidden="true"></span>
                    <span class="tg-product-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 9.5h17M3.5 14.5h17"/></svg></span>
                    <span class="tg-product-copy">
                        <strong>Spor</strong>
                        <em>Canlı bahis</em>
                    </span>
                    <span class="tg-product-cta">Aç</span>
                </button>
            </section>

            <section class="tg-block tg-block--rail">
                <div class="tg-block-head">
                    <h2>Öne Çıkanlar</h2>
                    <button type="button" class="tg-linkish" data-goto="slots">Tümü</button>
                </div>
                <div class="tg-rail" id="tgHomeFeatured">
                    <div class="tg-rail-status" id="tgHomeFeaturedStatus">Yükleniyor…</div>
                </div>
            </section>

            <section class="tg-block">
                <div class="tg-block-head">
                    <h2>Son Kazananlar</h2>
                    <button type="button" class="tg-linkish" id="tgRefreshWinners">Yenile</button>
                </div>
                <div class="tg-winners" id="tgWinners">Yükleniyor…</div>
            </section>
        </section>

        <section class="tg-panel" data-panel="slots">
            <div class="tg-status" id="tgSlotsStatus">Slotlar yükleniyor…</div>
            <div class="tg-grid" id="tgSlotsGrid" hidden></div>
            <button type="button" class="tg-more" id="tgSlotsMore" hidden>Daha fazla</button>
        </section>

        <section class="tg-panel" data-panel="live">
            <div class="tg-status" id="tgLiveStatus">Canlı masalar yükleniyor…</div>
            <div class="tg-grid" id="tgLiveGrid" hidden></div>
            <button type="button" class="tg-more" id="tgLiveMore" hidden>Daha fazla</button>
        </section>

        <section class="tg-panel" data-panel="sport">
            <div class="tg-sport-card">
                <h2>Spor Bahisleri</h2>
                <p>Canlı ve prematch bahisler Telegram içinde açılır.</p>
                <button type="button" class="tg-btn tg-btn-primary" id="tgSportLaunch">Spor’u Aç</button>
                <p class="tg-hint" id="tgSportHint"></p>
            </div>
        </section>

        <section class="tg-panel" data-panel="deposit">
            <div class="tg-wallet">
                <div class="tg-wallet-head">
                    <button type="button" class="tg-back" data-goto="account" aria-label="Geri">‹</button>
                    <h2>Para Yatır</h2>
                </div>
                <p class="tg-wallet-lead">Yöntem seçin, tutarı girin. Ödeme Telegram içinde açılır.</p>
                <div class="tg-method-list" id="tgDepMethods">Yöntemler yükleniyor…</div>
                <div class="tg-wallet-form" id="tgDepForm" hidden>
                    <div class="tg-selected-method" id="tgDepSelected"></div>
                    <label class="tg-field">
                        <span>Tutar (₺)</span>
                        <input id="tgDepAmount" type="number" inputmode="decimal" min="1" step="1" placeholder="0">
                    </label>
                    <p class="tg-hint" id="tgDepLimits"></p>
                    <button type="button" class="tg-btn tg-btn-primary" id="tgDepSubmit">Ödemeye Geç</button>
                    <p class="tg-hint" id="tgDepHint"></p>
                </div>
            </div>
        </section>

        <section class="tg-panel" data-panel="withdraw">
            <div class="tg-wallet">
                <div class="tg-wallet-head">
                    <button type="button" class="tg-back" data-goto="account" aria-label="Geri">‹</button>
                    <h2>Para Çek</h2>
                </div>
                <p class="tg-wallet-lead">Talebiniz uygulama içinde oluşturulur. Onay sonrası hesabınıza aktarılır.</p>
                <div class="tg-method-list" id="tgWdrMethods">Yöntemler yükleniyor…</div>
                <div class="tg-wallet-form" id="tgWdrForm" hidden>
                    <div class="tg-selected-method" id="tgWdrSelected"></div>
                    <div id="tgWdrFields"></div>
                    <label class="tg-field">
                        <span>Tutar (₺)</span>
                        <input id="tgWdrAmount" type="number" inputmode="decimal" min="1" step="1" placeholder="0">
                    </label>
                    <p class="tg-hint" id="tgWdrLimits"></p>
                    <button type="button" class="tg-btn tg-btn-primary" id="tgWdrSubmit">Çekim Talebi Oluştur</button>
                    <p class="tg-hint" id="tgWdrHint"></p>
                </div>
            </div>
        </section>

        <section class="tg-panel" data-panel="profile">
            <div class="tg-wallet">
                <div class="tg-wallet-head">
                    <button type="button" class="tg-back" data-goto="account" aria-label="Geri">‹</button>
                    <h2>Profil</h2>
                </div>
                <div class="tg-account" id="tgProfileBox">
                    <div class="tg-account-row"><span>Kullanıcı</span><strong id="tgProfUser">—</strong></div>
                    <div class="tg-account-row"><span>ID</span><strong id="tgProfId">—</strong></div>
                    <div class="tg-account-row"><span>Ana bakiye</span><strong id="tgProfBal">—</strong></div>
                    <div class="tg-account-row"><span>Bonus</span><strong id="tgProfBonus">—</strong></div>
                    <div class="tg-account-row"><span>Para birimi</span><strong id="tgProfCur">TRY</strong></div>
                </div>
                <div class="tg-account-actions">
                    <button type="button" class="tg-btn tg-btn-primary" data-goto="deposit">Para Yatır</button>
                    <button type="button" class="tg-btn tg-btn-ghost" data-goto="withdraw">Para Çek</button>
                </div>
                <p class="tg-hint">Hesap bilgileriniz Telegram oturumuyla bağlıdır.</p>
            </div>
        </section>

        <section class="tg-panel" data-panel="promos">
            <div class="tg-wallet">
                <div class="tg-wallet-head">
                    <button type="button" class="tg-back" data-goto="account" aria-label="Geri">‹</button>
                    <h2>Promosyonlar</h2>
                </div>
                <div class="tg-promo-list" id="tgPromoList">Yükleniyor…</div>
            </div>
        </section>

        <section class="tg-panel" data-panel="account">
            <div class="tg-account" id="tgAccount">
                <div class="tg-account-row"><span>Kullanıcı</span><strong id="tgAccUser">—</strong></div>
                <div class="tg-account-row"><span>Ana bakiye</span><strong id="tgAccBal">—</strong></div>
                <div class="tg-account-row"><span>Bonus</span><strong id="tgAccBonus">—</strong></div>
            </div>
            <div class="tg-account-actions">
                <button type="button" class="tg-btn tg-btn-primary" data-goto="deposit">Para Yatır</button>
                <button type="button" class="tg-btn tg-btn-ghost" data-goto="withdraw">Para Çek</button>
                <button type="button" class="tg-btn tg-btn-ghost" data-goto="profile">Profil</button>
                <button type="button" class="tg-btn tg-btn-ghost" data-goto="promos">Promosyonlar</button>
            </div>
            <p class="tg-hint" id="tgAccHint"></p>
        </section>
    </main>

    <div class="tg-overlay" id="tgOverlay" hidden>
        <div class="tg-overlay-bar">
            <button type="button" class="tg-back" id="tgOverlayClose" aria-label="Kapat">‹</button>
            <strong id="tgOverlayTitle">Vegasroyalspin</strong>
            <button type="button" class="tg-overlay-refresh" id="tgOverlayRefresh" title="Yenile">↻</button>
        </div>
        <div class="tg-overlay-body">
            <div class="tg-overlay-loader" id="tgOverlayLoader">Yükleniyor…</div>
            <iframe class="tg-overlay-frame" id="tgOverlayFrame" title="Vegasroyalspin" allow="payment *; fullscreen *; clipboard-read; clipboard-write" allowfullscreen></iframe>
        </div>
    </div>

    <nav class="tg-nav" id="tgNav">
        <button type="button" class="is-active" data-goto="home">
            <span class="tg-nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 10.5 12 4l8 6.5V20a1 1 0 0 1-1 1h-5v-6H10v6H5a1 1 0 0 1-1-1z"/></svg></span>
            <span>Ana</span>
        </button>
        <button type="button" data-goto="slots">
            <span class="tg-nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/></svg></span>
            <span>Slot</span>
        </button>
        <button type="button" data-goto="live">
            <span class="tg-nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="2"/></svg></span>
            <span>Canlı</span>
        </button>
        <button type="button" data-goto="sport">
            <span class="tg-nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M3.5 9.5h17M3.5 14.5h17"/></svg></span>
            <span>Spor</span>
        </button>
        <button type="button" data-goto="account">
            <span class="tg-nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 19.5c1.5-3.2 3.8-4.8 7-4.8s5.5 1.6 7 4.8"/></svg></span>
            <span>Hesap</span>
        </button>
    </nav>

    <div class="tg-toast" id="tgToast" hidden></div>

    <script src="/assets/js/telegram-miniapp.js?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
