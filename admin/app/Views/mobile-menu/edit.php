<?php

$payload = is_array($payload ?? null) ? $payload : [];
$sections = is_array($payload['sections'] ?? null) ? $payload['sections'] : [];
$tabBar = is_array($payload['tab_bar'] ?? null) ? $payload['tab_bar'] : [];
$desktopNav = is_array($payload['desktop_nav'] ?? null) ? $payload['desktop_nav'] : [];
$productBanners = is_array($payload['product_banners'] ?? null) ? $payload['product_banners'] : [];
$productBannerBase = trim((string) ($payload['product_banner_base'] ?? 'assets/images/banners'));
$flash = trim((string) ($flash ?? ''));
$error = trim((string) ($error ?? ''));
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$targets = ['_self' => 'Aynı sekme', '_blank' => 'Yeni sekme'];
$renderItem = static function (int|string $sectionIndex, int|string $itemIndex, array $item = []) use ($h, $targets): void {
    ?>
    <tr data-mobile-menu-item>
        <td>
                <input class="input" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][label]" value="<?= $h($item['label'] ?? '') ?>" placeholder="Örn: Promosyonlar">
        </td>
        <td>
                <input class="input" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][href]" value="<?= $h($item['href'] ?? '') ?>" placeholder="/promotions veya https://...">
        </td>
        <td>
                <input class="input" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][icon]" value="<?= $h($item['icon'] ?? '') ?>" placeholder="bc-i-promotions-3">
        </td>
        <td>
                <input class="input" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][badge]" value="<?= $h($item['badge'] ?? '') ?>" placeholder="PROMOSYON">
        </td>
        <td>
                <select class="select" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][target]">
                    <?php foreach ($targets as $target => $label): ?>
                        <option value="<?= $h($target) ?>" <?= (string) ($item['target'] ?? '_self') === $target ? 'selected' : '' ?>><?= $h($label) ?></option>
                    <?php endforeach; ?>
                </select>
        </td>
        <td>
                <input type="hidden" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][enabled]" value="0">
                <label class="switch">
                    <input type="checkbox" name="sections[<?= $h($sectionIndex) ?>][items][<?= $h($itemIndex) ?>][enabled]" value="1" <?= !array_key_exists('enabled', $item) || !empty($item['enabled']) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    Aktif
                </label>
        </td>
        <td class="mobile-menu-actions-cell">
                <button class="btn btn--ghost" type="button" data-remove-mobile-menu-item>Sil</button>
        </td>
    </tr>
    <?php
};
?>
<style>
    .mobile-menu-manager { display:flex; flex-direction:column; gap:14px; }
    .mobile-menu-section { background:var(--bg-card); border:1px solid var(--border); border-radius:12px; box-shadow:var(--shadow-card); color:var(--t-base); overflow:hidden; }
    .mobile-menu-section-head { align-items:center; background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); border-bottom:1px solid var(--border); display:flex; gap:12px; justify-content:space-between; padding:12px 14px; }
    .mobile-menu-section-title { color:var(--t-base); font-size:13px; font-weight:900; margin:0; }
    .mobile-menu-section-body { display:flex; flex-direction:column; gap:10px; padding:12px; }
    .mobile-menu-section-settings { display:grid; gap:10px; grid-template-columns:minmax(220px, 1fr) auto; }
    .mobile-menu-table-wrap { background:var(--bg-card); border:1px solid var(--border-soft); border-radius:12px; overflow:auto; scrollbar-color:var(--border) transparent; scrollbar-width:thin; }
    .mobile-menu-table-wrap::-webkit-scrollbar { height:6px; width:6px; }
    .mobile-menu-table-wrap::-webkit-scrollbar-thumb { background:var(--border); border-radius:999px; }
    .mobile-menu-table { border-collapse:separate; border-spacing:0; min-width:980px; width:100%; }
    .mobile-menu-table th,
    .mobile-menu-table td { border-bottom:1px solid var(--border-soft); padding:8px; text-align:left; vertical-align:middle; }
    .mobile-menu-table th { background:color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card)); color:var(--t-light); font-size:11px; font-weight:900; letter-spacing:.06em; text-transform:uppercase; }
    .mobile-menu-table td { background:var(--bg-card); color:var(--t-base); }
    .mobile-menu-table tbody tr:nth-child(even) td { background:color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card)); }
    .mobile-menu-table tbody tr:hover td { background:color-mix(in srgb, var(--primary-soft) 64%, var(--bg-card)); }
    .mobile-menu-table tbody tr:last-child td { border-bottom:0; }
    .mobile-menu-table .input,
    .mobile-menu-table .select { min-height:34px; padding:7px 9px; }
    .mobile-menu-actions-cell { text-align:right !important; white-space:nowrap; width:86px; }
    .product-banner-table { min-width:1100px; }
    .product-banner-hint { color:var(--t-muted); font-size:12px; line-height:1.45; }
    @media(max-width:760px) {
        .mobile-menu-section-settings { grid-template-columns:1fr; }
    }
</style>
<section class="admin-surface">
<div class="hero">
    <div class="hero-text">
        <span class="eyebrow">İçerik · Mobil Menü</span>
        <h1 class="hero-title">Mobil Menü <span class="accent">Yönetimi</span></h1>
        <p class="hero-sub">Mobil alt bar, tam ekran menü ve masaüstü ana navigasyon bu ekrandan yönetilir.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= $h(AdminAuth::url('/module?key=mobile-menu-settings')) ?>">Kayıtları Gör</a>
        <button class="btn btn--primary" type="submit" form="mobileMenuSettingsForm">Kaydet</button>
    </div>
</div>

<?php if ($error !== ''): ?>
    <div class="alert danger">
        <span class="ico"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg></span>
        <div class="body"><?= $h($error) ?></div>
    </div>
<?php endif; ?>

<form id="mobileMenuSettingsForm" class="mobile-menu-manager" method="post" action="<?= $h(AdminAuth::url('/mobile-menu')) ?>">
    <input type="hidden" name="_token" value="<?= $h(AdminAuth::csrfToken()) ?>">

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Genel</span>
                <h2 class="card-title">Panel Başlığı</h2>
            </div>
            <span class="badge solid">/api/v2/content/mobile-menu</span>
        </div>
        <div class="form-grid">
            <div class="field span-2">
                <label class="field-label" for="title">Menü başlığı</label>
                <input id="title" class="input" name="title" value="<?= $h($payload['title'] ?? 'Menü') ?>">
                <div class="field-help">Mobil panel üst satırında görünen başlık.</div>
            </div>
        </div>
    </section>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Masaüstü · Ana Menü</span>
                <h2 class="card-title">Header Navigasyon (desktop_nav)</h2>
            </div>
            <span class="badge solid">main-menu-nav.php</span>
        </div>
        <div class="mobile-menu-table-wrap">
            <table class="mobile-menu-table">
                <thead>
                    <tr>
                        <th>Etiket</th>
                        <th>URL</th>
                        <th>İkon (bc-i-*)</th>
                        <th>Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-desktop-nav-list>
                    <?php foreach ($desktopNav as $navIndex => $navItem): ?>
                        <?php $navItem = is_array($navItem) ? $navItem : []; ?>
                        <tr data-desktop-nav-item>
                            <td><input class="input" name="desktop_nav[<?= $h($navIndex) ?>][label]" value="<?= $h($navItem['label'] ?? '') ?>"></td>
                            <td><input class="input" name="desktop_nav[<?= $h($navIndex) ?>][href]" value="<?= $h($navItem['href'] ?? '') ?>"></td>
                            <td><input class="input" name="desktop_nav[<?= $h($navIndex) ?>][icon]" value="<?= $h($navItem['icon'] ?? '') ?>" placeholder="bc-i-slots"></td>
                            <td>
                                <input type="hidden" name="desktop_nav[<?= $h($navIndex) ?>][enabled]" value="0">
                                <label class="switch">
                                    <input type="checkbox" name="desktop_nav[<?= $h($navIndex) ?>][enabled]" value="1" <?= !array_key_exists('enabled', $navItem) || !empty($navItem['enabled']) ? 'checked' : '' ?>>
                                    <span class="track"></span>
                                    Aktif
                                </label>
                            </td>
                            <td class="mobile-menu-actions-cell">
                                <button class="btn btn--ghost" type="button" data-remove-desktop-nav>Sil</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-actionbar" style="margin-top:12px;">
            <button class="admin-action-btn" type="button" data-add-desktop-nav>
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Nav Link Ekle
            </button>
        </div>
    </section>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Mobil · Alt Bar</span>
                <h2 class="card-title">Mobil Alt Tab Bar (tab_bar)</h2>
            </div>
            <span class="badge solid">mobFooter</span>
        </div>
        <div class="mobile-menu-table-wrap">
            <table class="mobile-menu-table">
                <thead>
                    <tr>
                        <th>Tip</th>
                        <th>Etiket</th>
                        <th>URL</th>
                        <th>İkon</th>
                        <th>Element ID</th>
                        <th>Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-tab-bar-list>
                    <?php foreach ($tabBar as $tabIndex => $tabItem): ?>
                        <?php $tabItem = is_array($tabItem) ? $tabItem : []; ?>
                        <tr data-tab-bar-item>
                            <td>
                                <select class="select" name="tab_bar[<?= $h($tabIndex) ?>][type]">
                                    <?php foreach (['link' => 'Link', 'button' => 'Buton', 'menu' => 'Menü'] as $typeValue => $typeLabel): ?>
                                        <option value="<?= $h($typeValue) ?>" <?= (string) ($tabItem['type'] ?? 'link') === $typeValue ? 'selected' : '' ?>><?= $h($typeLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input class="input" name="tab_bar[<?= $h($tabIndex) ?>][label]" value="<?= $h($tabItem['label'] ?? '') ?>"></td>
                            <td><input class="input" name="tab_bar[<?= $h($tabIndex) ?>][href]" value="<?= $h($tabItem['href'] ?? '') ?>"></td>
                            <td><input class="input" name="tab_bar[<?= $h($tabIndex) ?>][icon]" value="<?= $h($tabItem['icon'] ?? '') ?>"></td>
                            <td><input class="input" name="tab_bar[<?= $h($tabIndex) ?>][id]" value="<?= $h($tabItem['id'] ?? '') ?>" placeholder="menu-toggle"></td>
                            <td>
                                <input type="hidden" name="tab_bar[<?= $h($tabIndex) ?>][enabled]" value="0">
                                <label class="switch">
                                    <input type="checkbox" name="tab_bar[<?= $h($tabIndex) ?>][enabled]" value="1" <?= !array_key_exists('enabled', $tabItem) || !empty($tabItem['enabled']) ? 'checked' : '' ?>>
                                    <span class="track"></span>
                                    Aktif
                                </label>
                            </td>
                            <td class="mobile-menu-actions-cell">
                                <button class="btn btn--ghost" type="button" data-remove-tab-bar>Sil</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-actionbar" style="margin-top:12px;">
            <button class="admin-action-btn" type="button" data-add-tab-bar>
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Tab Ekle
            </button>
        </div>
    </section>

    <section class="card admin-compact-card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Header · Ürün Bannerları</span>
                <h2 class="card-title">Ana Sayfa / Header Banner Şeridi</h2>
            </div>
            <span class="badge solid">mobile_menu.product_banners</span>
        </div>
        <div class="form-grid">
            <div class="field span-2">
                <label class="field-label" for="product_banner_base">Görsel klasörü (yerel asset)</label>
                <input id="product_banner_base" class="input" name="product_banner_base" value="<?= $h($productBannerBase !== '' ? $productBannerBase : 'assets/images/banners') ?>" placeholder="assets/images/banners">
                <div class="field-help product-banner-hint">Yerel dosya adları için önek. Tam URL veya <code>/uploads/...</code> yolu görsel alanına yazılabilir.</div>
            </div>
        </div>
        <div class="mobile-menu-table-wrap" style="margin-top:12px;">
            <table class="mobile-menu-table product-banner-table">
                <thead>
                    <tr>
                        <th>Aria / Etiket</th>
                        <th>Alt metin</th>
                        <th>Görsel (dosya veya URL)</th>
                        <th>Hedef URL</th>
                        <th>Giriş kilidi</th>
                        <th>Aktif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody data-product-banner-list>
                    <?php foreach ($productBanners as $bannerIndex => $banner): ?>
                        <?php
                        $banner = is_array($banner) ? $banner : [];
                        $loginGate = !empty($banner['login_gate']);
                        ?>
                        <tr data-product-banner-item>
                            <td><input class="input" name="product_banners[<?= $h($bannerIndex) ?>][aria]" value="<?= $h($banner['aria'] ?? '') ?>" placeholder="SLOT"></td>
                            <td><input class="input" name="product_banners[<?= $h($bannerIndex) ?>][alt]" value="<?= $h($banner['alt'] ?? '') ?>" placeholder="Slot"></td>
                            <td><input class="input" name="product_banners[<?= $h($bannerIndex) ?>][img]" value="<?= $h($banner['img'] ?? '') ?>" placeholder="slot.webp veya /uploads/banners/..."></td>
                            <td><input class="input" name="product_banners[<?= $h($bannerIndex) ?>][href]" value="<?= $h($loginGate ? '' : ($banner['href'] ?? '')) ?>" placeholder="/slot veya {{LIVE_SUPPORT_URL}}" <?= $loginGate ? 'disabled' : '' ?> data-product-banner-href></td>
                            <td>
                                <input type="hidden" name="product_banners[<?= $h($bannerIndex) ?>][login_gate]" value="0">
                                <label class="switch">
                                    <input type="checkbox" name="product_banners[<?= $h($bannerIndex) ?>][login_gate]" value="1" data-product-banner-login-gate <?= $loginGate ? 'checked' : '' ?>>
                                    <span class="track"></span>
                                    Yatırım
                                </label>
                            </td>
                            <td>
                                <input type="hidden" name="product_banners[<?= $h($bannerIndex) ?>][enabled]" value="0">
                                <label class="switch">
                                    <input type="checkbox" name="product_banners[<?= $h($bannerIndex) ?>][enabled]" value="1" <?= !array_key_exists('enabled', $banner) || !empty($banner['enabled']) ? 'checked' : '' ?>>
                                    <span class="track"></span>
                                    Aktif
                                </label>
                            </td>
                            <td class="mobile-menu-actions-cell">
                                <button class="btn btn--ghost" type="button" data-remove-product-banner>Sil</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="admin-actionbar" style="margin-top:12px;">
            <div class="admin-actionbar-left">
                <button class="admin-action-btn" type="button" data-add-product-banner>
                    <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    Banner Ekle
                </button>
            </div>
            <div class="admin-actionbar-right">
                <span class="badge dot">Placeholder: {{LIVE_SUPPORT_URL}}</span>
            </div>
        </div>
    </section>

    <div data-mobile-menu-section-list>
        <?php foreach ($sections as $sectionIndex => $section): ?>
            <?php
            $section = is_array($section) ? $section : [];
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            ?>
            <section class="mobile-menu-section" data-mobile-menu-section>
                <div class="mobile-menu-section-head">
                    <div>
                        <span class="eyebrow">Bölüm</span>
                        <h2 class="mobile-menu-section-title"><?= $h((string) ($section['title'] ?? '') !== '' ? $section['title'] : 'Başlıksız bölüm') ?></h2>
                    </div>
                    <div class="hero-actions">
                        <button class="btn btn--ghost" type="button" data-add-mobile-menu-item>Link Ekle</button>
                        <button class="btn btn--ghost" type="button" data-remove-mobile-menu-section>Bölümü Sil</button>
                    </div>
                </div>
                <div class="mobile-menu-section-body">
                    <div class="mobile-menu-section-settings">
                        <label class="field">
                            <span class="field-label">Bölüm başlığı</span>
                            <input class="input" name="sections[<?= $h($sectionIndex) ?>][title]" value="<?= $h($section['title'] ?? '') ?>" placeholder="Örn: İLETİŞİM">
                        </label>
                        <label class="field">
                            <span class="field-label">Görünüm</span>
                            <select class="select" name="sections[<?= $h($sectionIndex) ?>][layout]">
                                <?php
                                $sectionLayout = (string) ($section['layout'] ?? ((string) ($section['title'] ?? '') === '' ? 'grid' : 'list'));
                                foreach (['grid' => 'Kart grid', 'list' => 'Liste'] as $layoutValue => $layoutLabel):
                                ?>
                                    <option value="<?= $h($layoutValue) ?>" <?= $sectionLayout === $layoutValue ? 'selected' : '' ?>><?= $h($layoutLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <span class="badge solid"><?= count($items) ?> link</span>
                    </div>
                    <div class="mobile-menu-table-wrap">
                        <table class="mobile-menu-table">
                            <thead>
                                <tr>
                                    <th>Link Adı</th>
                                    <th>URL / Path</th>
                                    <th>İkon</th>
                                    <th>Rozet</th>
                                    <th>Açılış</th>
                                    <th>Durum</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody data-mobile-menu-item-list>
                                <?php foreach ($items as $itemIndex => $item): ?>
                                    <?php $renderItem($sectionIndex, $itemIndex, is_array($item) ? $item : []); ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <section class="admin-actionbar">
        <div class="admin-actionbar-left">
            <button class="admin-action-btn" type="button" data-add-mobile-menu-section>
                <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                Yeni Bölüm Ekle
            </button>
        </div>
        <div class="admin-actionbar-right">
            <span class="badge dot success">API: /api/v2/content/mobile-menu</span>
            <button class="btn btn--primary" type="submit">Mobil Menü Ayarlarını Kaydet</button>
        </div>
    </section>
</form>

<template id="mobileMenuSectionTemplate">
    <section class="mobile-menu-section" data-mobile-menu-section>
        <div class="mobile-menu-section-head">
            <div>
                <span class="eyebrow">Bölüm</span>
                <h2 class="mobile-menu-section-title">Yeni bölüm</h2>
            </div>
            <div class="hero-actions">
                <button class="btn btn--ghost" type="button" data-add-mobile-menu-item>Link Ekle</button>
                <button class="btn btn--ghost" type="button" data-remove-mobile-menu-section>Bölümü Sil</button>
            </div>
        </div>
        <div class="mobile-menu-section-body">
            <div class="mobile-menu-section-settings">
                <label class="field">
                    <span class="field-label">Bölüm başlığı</span>
                    <input class="input" name="sections[__SECTION__][title]" value="" placeholder="Örn: İLETİŞİM">
                </label>
                <label class="field">
                    <span class="field-label">Görünüm</span>
                    <select class="select" name="sections[__SECTION__][layout]">
                        <option value="grid">Kart grid</option>
                        <option value="list" selected>Liste</option>
                    </select>
                </label>
                <span class="badge solid">0 link</span>
            </div>
            <div class="mobile-menu-table-wrap">
                <table class="mobile-menu-table">
                    <thead>
                        <tr>
                            <th>Link Adı</th>
                            <th>URL / Path</th>
                            <th>İkon</th>
                            <th>Rozet</th>
                            <th>Açılış</th>
                            <th>Durum</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody data-mobile-menu-item-list></tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<template id="mobileMenuItemTemplate">
    <table><tbody><?php $renderItem('__SECTION__', '__ITEM__', ['enabled' => true, 'target' => '_self']); ?></tbody></table>
</template>

<template id="desktopNavRowTemplate">
    <tr data-desktop-nav-item>
        <td><input class="input" name="desktop_nav[__INDEX__][label]" value="" placeholder="SLOT"></td>
        <td><input class="input" name="desktop_nav[__INDEX__][href]" value="" placeholder="/slot"></td>
        <td><input class="input" name="desktop_nav[__INDEX__][icon]" value="" placeholder="bc-i-slots"></td>
        <td>
            <input type="hidden" name="desktop_nav[__INDEX__][enabled]" value="0">
            <label class="switch">
                <input type="checkbox" name="desktop_nav[__INDEX__][enabled]" value="1" checked>
                <span class="track"></span>
                Aktif
            </label>
        </td>
        <td class="mobile-menu-actions-cell">
            <button class="btn btn--ghost" type="button" data-remove-desktop-nav>Sil</button>
        </td>
    </tr>
</template>

<template id="tabBarRowTemplate">
    <tr data-tab-bar-item>
        <td>
            <select class="select" name="tab_bar[__INDEX__][type]">
                <option value="link">Link</option>
                <option value="button">Buton</option>
                <option value="menu">Menü</option>
            </select>
        </td>
        <td><input class="input" name="tab_bar[__INDEX__][label]" value=""></td>
        <td><input class="input" name="tab_bar[__INDEX__][href]" value=""></td>
        <td><input class="input" name="tab_bar[__INDEX__][icon]" value=""></td>
        <td><input class="input" name="tab_bar[__INDEX__][id]" value=""></td>
        <td>
            <input type="hidden" name="tab_bar[__INDEX__][enabled]" value="0">
            <label class="switch">
                <input type="checkbox" name="tab_bar[__INDEX__][enabled]" value="1" checked>
                <span class="track"></span>
                Aktif
            </label>
        </td>
        <td class="mobile-menu-actions-cell">
            <button class="btn btn--ghost" type="button" data-remove-tab-bar>Sil</button>
        </td>
    </tr>
</template>

<template id="productBannerRowTemplate">
    <tr data-product-banner-item>
        <td><input class="input" name="product_banners[__INDEX__][aria]" value="" placeholder="SLOT"></td>
        <td><input class="input" name="product_banners[__INDEX__][alt]" value="" placeholder="Slot"></td>
        <td><input class="input" name="product_banners[__INDEX__][img]" value="" placeholder="slot.webp"></td>
        <td><input class="input" name="product_banners[__INDEX__][href]" value="" placeholder="/slot" data-product-banner-href></td>
        <td>
            <input type="hidden" name="product_banners[__INDEX__][login_gate]" value="0">
            <label class="switch">
                <input type="checkbox" name="product_banners[__INDEX__][login_gate]" value="1" data-product-banner-login-gate>
                <span class="track"></span>
                Yatırım
            </label>
        </td>
        <td>
            <input type="hidden" name="product_banners[__INDEX__][enabled]" value="0">
            <label class="switch">
                <input type="checkbox" name="product_banners[__INDEX__][enabled]" value="1" checked>
                <span class="track"></span>
                Aktif
            </label>
        </td>
        <td class="mobile-menu-actions-cell">
            <button class="btn btn--ghost" type="button" data-remove-product-banner>Sil</button>
        </td>
    </tr>
</template>

<script>
    (function () {
        var sectionList = document.querySelector('[data-mobile-menu-section-list]');
        var sectionTemplate = document.getElementById('mobileMenuSectionTemplate');
        var itemTemplate = document.getElementById('mobileMenuItemTemplate');
        var bannerList = document.querySelector('[data-product-banner-list]');
        var bannerTemplate = document.getElementById('productBannerRowTemplate');
        var desktopNavList = document.querySelector('[data-desktop-nav-list]');
        var desktopNavTemplate = document.getElementById('desktopNavRowTemplate');
        var tabBarList = document.querySelector('[data-tab-bar-list]');
        var tabBarTemplate = document.getElementById('tabBarRowTemplate');
        if (!sectionList || !sectionTemplate || !itemTemplate) return;

        function nextIndex() {
            return String(Date.now()) + String(Math.floor(Math.random() * 1000));
        }

        function toggleBannerHref(row) {
            var gate = row.querySelector('[data-product-banner-login-gate]');
            var href = row.querySelector('[data-product-banner-href]');
            if (!gate || !href) return;
            href.disabled = gate.checked;
            if (gate.checked) href.value = '';
        }

        document.querySelectorAll('[data-product-banner-item]').forEach(toggleBannerHref);

        document.addEventListener('change', function (event) {
            if (event.target && event.target.matches('[data-product-banner-login-gate]')) {
                var row = event.target.closest('[data-product-banner-item]');
                if (row) toggleBannerHref(row);
            }
        });

        document.addEventListener('click', function (event) {
            var addDesktopNav = event.target.closest('[data-add-desktop-nav]');
            if (addDesktopNav && desktopNavList && desktopNavTemplate) {
                desktopNavList.insertAdjacentHTML('beforeend', desktopNavTemplate.innerHTML.replaceAll('__INDEX__', nextIndex()));
                return;
            }

            var removeDesktopNav = event.target.closest('[data-remove-desktop-nav]');
            if (removeDesktopNav) {
                var navRow = removeDesktopNav.closest('[data-desktop-nav-item]');
                if (navRow) navRow.remove();
                return;
            }

            var addTabBar = event.target.closest('[data-add-tab-bar]');
            if (addTabBar && tabBarList && tabBarTemplate) {
                tabBarList.insertAdjacentHTML('beforeend', tabBarTemplate.innerHTML.replaceAll('__INDEX__', nextIndex()));
                return;
            }

            var removeTabBar = event.target.closest('[data-remove-tab-bar]');
            if (removeTabBar) {
                var tabRow = removeTabBar.closest('[data-tab-bar-item]');
                if (tabRow) tabRow.remove();
                return;
            }

            var addBanner = event.target.closest('[data-add-product-banner]');
            if (addBanner && bannerList && bannerTemplate) {
                var index = String(Date.now()) + String(Math.floor(Math.random() * 1000));
                var html = bannerTemplate.innerHTML.replaceAll('__INDEX__', index);
                bannerList.insertAdjacentHTML('beforeend', html);
                return;
            }

            var removeBanner = event.target.closest('[data-remove-product-banner]');
            if (removeBanner) {
                var bannerRow = removeBanner.closest('[data-product-banner-item]');
                if (bannerRow) bannerRow.remove();
                return;
            }

            var addSection = event.target.closest('[data-add-mobile-menu-section]');
            if (addSection) {
                var sectionIndex = nextSectionIndex();
                var wrapper = document.createElement('div');
                wrapper.innerHTML = sectionTemplate.innerHTML.replaceAll('__SECTION__', sectionIndex);
                var section = wrapper.firstElementChild;
                sectionList.appendChild(section);
                addItem(section);
                return;
            }

            var addItemButton = event.target.closest('[data-add-mobile-menu-item]');
            if (addItemButton) {
                var section = addItemButton.closest('[data-mobile-menu-section]');
                if (section) addItem(section);
                return;
            }

            var removeItem = event.target.closest('[data-remove-mobile-menu-item]');
            if (removeItem) {
                var row = removeItem.closest('[data-mobile-menu-item]');
                if (row) row.remove();
                return;
            }

            var removeSection = event.target.closest('[data-remove-mobile-menu-section]');
            if (removeSection) {
                var section = removeSection.closest('[data-mobile-menu-section]');
                if (section) section.remove();
            }
        });

        function nextSectionIndex() {
            return String(Date.now()) + String(Math.floor(Math.random() * 1000));
        }

        function nextItemIndex(section) {
            return String(section.querySelectorAll('[data-mobile-menu-item]').length + 1) + '_' + String(Date.now());
        }

        function addItem(section) {
            var list = section.querySelector('[data-mobile-menu-item-list]');
            if (!list) return;
            var sectionNameInput = section.querySelector('input[name$="[title]"]');
            var match = sectionNameInput ? sectionNameInput.name.match(/^sections\[([^\]]+)\]/) : null;
            var sectionIndex = match ? match[1] : nextSectionIndex();
            var rowTemplate = itemTemplate.content ? itemTemplate.content.querySelector('tr') : null;
            var html = (rowTemplate ? rowTemplate.outerHTML : itemTemplate.innerHTML).replaceAll('__SECTION__', sectionIndex).replaceAll('__ITEM__', nextItemIndex(section));
            list.insertAdjacentHTML('beforeend', html);
        }
    })();
</script>
</section>
