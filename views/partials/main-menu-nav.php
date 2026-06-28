<?php
/** Ortak ana navigasyon — admin ApiMobileMenu desktop_nav kaynağından render edilir. */
if (!class_exists('ApiMobileMenu', false)) {
    require_once (defined('API_PATH') ? API_PATH : dirname(__DIR__, 2) . '/api') . '/bootstrap.php';
}
$desktopNav = ApiMobileMenu::fetch()['desktop_nav'] ?? ApiMobileMenu::defaultDesktopNav();
if (!is_array($desktopNav) || $desktopNav === []) {
    $desktopNav = ApiMobileMenu::defaultDesktopNav();
}
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
    <nav class="mainMenu">
        <ul>
            <?php foreach ($desktopNav as $item): ?>
                <?php
                if (!is_array($item) || empty($item['enabled'])) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                $href = trim((string) ($item['href'] ?? ''));
                $icon = trim((string) ($item['icon'] ?? ''));
                if ($label === '' || $href === '') {
                    continue;
                }
                ?>
            <li>
                <a href="<?= $h($href) ?>">
                    <?php if ($icon !== ''): ?>
                    <span class="CMSIconSVGWrapper"><i class="<?= $h($icon) ?>"></i></span>
                    <?php endif; ?>
                    <span><?= $h($label) ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>
