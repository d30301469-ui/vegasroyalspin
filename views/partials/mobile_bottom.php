<?php
/** Mobil alt bar + tam ekran menü — admin ApiMobileMenu kaynağından render edilir. */
if (!class_exists('ApiMobileMenu', false)) {
    require_once (defined('API_PATH') ? API_PATH : dirname(__DIR__, 2) . '/api') . '/bootstrap.php';
}
$ayar = isset($ayar) && is_array($ayar) ? $ayar : [];
$siteBranding = isset($siteBranding) && is_array($siteBranding) ? $siteBranding : [];
$mobileMenuPayload = ApiMobileMenu::fetch();
$mobileMenuSections = is_array($mobileMenuPayload['sections'] ?? null) ? $mobileMenuPayload['sections'] : [];
$mobileTabBar = is_array($mobileMenuPayload['tab_bar'] ?? null) && $mobileMenuPayload['tab_bar'] !== []
    ? $mobileMenuPayload['tab_bar']
    : ApiMobileMenu::defaultTabBar();
$mobileMenuBranding = is_array($siteBranding ?? null) ? $siteBranding : [];
$mobileMenuSiteName = (string) ($mobileMenuBranding['site_name'] ?? $ayar['site_adi'] ?? 'VegasRoyalSpin');
$mobileMenuLogoUrl = (string) ($mobileMenuBranding['logo_url'] ?? $ayar['logo_url'] ?? '');
if (class_exists('ApiMediaUrl', false)) {
    $mobileMenuLogoUrl = ApiMediaUrl::resolve($mobileMenuLogoUrl);
}
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="mobFooter">
    <?php foreach ($mobileTabBar as $tab): ?>
        <?php
        if (!is_array($tab) || empty($tab['enabled'])) {
            continue;
        }
        $type = strtolower(trim((string) ($tab['type'] ?? 'link')));
        $label = trim((string) ($tab['label'] ?? ''));
        $icon = trim((string) ($tab['icon'] ?? ''));
        $href = trim((string) ($tab['href'] ?? ''));
        $elementId = trim((string) ($tab['id'] ?? ''));
        $aria = trim((string) ($tab['aria_label'] ?? $label));
        if ($label === '') {
            continue;
        }
        ?>
        <?php if ($type === 'button'): ?>
            <a href="#" class="mobFooter-item"<?= $elementId !== '' ? ' id="' . $h($elementId) . '"' : '' ?> title="<?= $h($aria) ?>">
                <?php if ($icon !== ''): ?>
                    <span class="CMSIconSVGWrapper"><i class="<?= $h($icon) ?>"></i></span>
                <?php endif; ?>
                <span class="mobFooter-label"><?= $h($label) ?></span>
            </a>
        <?php elseif ($type === 'menu'): ?>
            <a class="mobFooter-item" id="<?= $h($elementId !== '' ? $elementId : 'menu-toggle') ?>" href="#" aria-label="<?= $h($aria) ?>">
                <?php if ($icon !== ''): ?>
                    <span class="CMSIconSVGWrapper"><i class="<?= $h($icon) ?>"></i></span>
                <?php endif; ?>
                <span class="mobFooter-label"><?= $h($label) ?></span>
            </a>
        <?php else: ?>
            <a href="<?= $h($href !== '' ? $href : '#') ?>" class="mobFooter-item" aria-label="<?= $h($aria) ?>">
                <?php if ($icon !== ''): ?>
                    <span class="CMSIconSVGWrapper"><i class="<?= $h($icon) ?>"></i></span>
                <?php endif; ?>
                <span class="mobFooter-label"><?= $h($label) ?></span>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="mobileMenu-overlay" id="mobileMenu-overlay"></div>
<aside class="mobileMenu" id="mobileMenu">
    <div class="mobileMenu-header">
        <a href="/" class="mobileMenu-logo" data-site-logo-link>
            <img src="<?= $h($mobileMenuLogoUrl) ?>" alt="<?= $h($mobileMenuSiteName) ?>">
        </a>
        <button class="mobileMenu-close" id="mobileMenu-close" type="button" aria-label="Kapat">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" x2="6" y1="6" y2="18"/><line x1="6" x2="18" y1="6" y2="18"/>
            </svg>
        </button>
    </div>

    <div class="mobileMenu-body">
        <?php foreach ($mobileMenuSections as $section): ?>
            <?php
            if (!is_array($section)) {
                continue;
            }
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $layout = strtolower(trim((string) ($section['layout'] ?? ($sectionTitle === '' ? 'grid' : 'list'))));
            $items = is_array($section['items'] ?? null) ? $section['items'] : [];
            $enabledItems = array_values(array_filter($items, static fn ($item) => is_array($item) && !empty($item['enabled'])));
            if ($enabledItems === []) {
                continue;
            }
            ?>
            <div class="mobileMenu-section">
                <?php if ($sectionTitle !== ''): ?>
                    <h3 class="mobileMenu-section-title"><?= $h($sectionTitle) ?></h3>
                <?php endif; ?>
                <?php if ($layout === 'grid'): ?>
                    <div class="mobileMenu-grid">
                        <?php foreach ($enabledItems as $item): ?>
                            <?php
                            $icon = trim((string) ($item['icon'] ?? ''));
                            $target = (string) ($item['target'] ?? '_self');
                            ?>
                            <a href="<?= $h((string) ($item['href'] ?? '#')) ?>"
                               class="mobileMenu-card"
                               target="<?= $h($target === '_blank' ? '_blank' : '_self') ?>"
                               <?= $target === '_blank' ? 'rel="noopener"' : '' ?>>
                                <?php if ($icon !== ''): ?>
                                    <span class="mobileMenu-card-icon"><i class="<?= $h($icon) ?>"></i></span>
                                <?php endif; ?>
                                <span class="mobileMenu-card-label"><?= $h((string) ($item['label'] ?? '')) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mobileMenu-list">
                        <?php foreach ($enabledItems as $item): ?>
                            <?php
                            $icon = trim((string) ($item['icon'] ?? ''));
                            $target = (string) ($item['target'] ?? '_self');
                            ?>
                            <a href="<?= $h((string) ($item['href'] ?? '#')) ?>"
                               class="mobileMenu-list-item"
                               target="<?= $h($target === '_blank' ? '_blank' : '_self') ?>"
                               <?= $target === '_blank' ? 'rel="noopener"' : '' ?>>
                                <?php if ($icon !== ''): ?>
                                    <span class="mobileMenu-list-icon"><i class="<?= $h($icon) ?>"></i></span>
                                <?php endif; ?>
                                <span><?= $h((string) ($item['label'] ?? '')) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</aside>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
