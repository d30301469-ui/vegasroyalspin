<?php
/** Alt tab bar — admin ApiMobileMenu tab_bar + hamburger menü */
$liveBadge = isset($mobileTabLiveBadge) ? (string) $mobileTabLiveBadge : '208';
if (!class_exists('ApiMobileMenu', false)) {
    require_once (defined('API_PATH') ? API_PATH : dirname(__DIR__, 3) . '/api') . '/bootstrap.php';
}
$mobileMenuPayload = ApiMobileMenu::fetch();
$mobileMenuTitle = (string) ($mobileMenuPayload['title'] ?? 'Menü');
$mobileMenuSections = is_array($mobileMenuPayload['sections'] ?? null) ? $mobileMenuPayload['sections'] : [];
$mobileTabBar = is_array($mobileMenuPayload['tab_bar'] ?? null) && $mobileMenuPayload['tab_bar'] !== []
    ? $mobileMenuPayload['tab_bar']
    : ApiMobileMenu::defaultTabBar();
$mobileMenuClass = static function (string $value): string {
    return trim((string) preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $value));
};
?>
<div class="tab-navigation-w-bc">
  <?php foreach ($mobileTabBar as $tab): ?>
    <?php
    if (!is_array($tab) || empty($tab['enabled'])) {
        continue;
    }
    $type = strtolower(trim((string) ($tab['type'] ?? 'link')));
    $label = trim((string) ($tab['label'] ?? ''));
    $icon = trim((string) ($tab['icon'] ?? ''));
    $badge = trim((string) ($tab['badge'] ?? ''));
    if ($badge === '' && $type === 'link' && str_contains(strtolower($label), 'canlı')) {
        $badge = $liveBadge;
    }
    $aria = trim((string) ($tab['aria_label'] ?? $label));
    $href = trim((string) ($tab['href'] ?? ''));
    $elementId = trim((string) ($tab['id'] ?? ''));
    $extraClass = $type === 'menu' ? ' menu' : '';
    $badgeClass = $badge !== '' ? ' count-odd-animation badge- count-blink-even' : '';
    ?>
    <?php if ($type === 'button'): ?>
      <button class="tab-nav-item-bc<?= $extraClass !== '' ? htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') : '' ?>"
              type="button"
              aria-label="<?= htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') ?>"
              <?= $elementId !== '' ? 'id="' . htmlspecialchars($elementId, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <?php if ($icon !== ''): ?>
          <i class="tab-nav-icon-bc <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
        <?php endif; ?>
        <p class="tab-nav-title-bc ellipsis"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
      </button>
    <?php elseif ($type === 'menu'): ?>
      <div class="tab-nav-item-bc menu"
           id="<?= htmlspecialchars($elementId !== '' ? $elementId : 'menu-toggle', ENT_QUOTES, 'UTF-8') ?>"
           role="button"
           tabindex="0"
           aria-label="<?= htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') ?>"
           aria-haspopup="true"
           aria-expanded="false"
           aria-controls="mobileMenu">
        <?php if ($icon !== ''): ?>
          <i class="tab-nav-icon-bc <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
        <?php endif; ?>
        <p class="tab-nav-title-bc ellipsis"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
      </div>
    <?php else: ?>
      <a class="tab-nav-item-bc<?= htmlspecialchars($badgeClass, ENT_QUOTES, 'UTF-8') ?>"
         aria-label="<?= htmlspecialchars($aria, ENT_QUOTES, 'UTF-8') ?>"
         href="<?= htmlspecialchars($href !== '' ? $href : '#', ENT_QUOTES, 'UTF-8') ?>"
         <?= $badge !== '' ? 'data-badge="' . htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <?php if ($icon !== ''): ?>
          <i class="tab-nav-icon-bc <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i>
        <?php endif; ?>
        <p class="tab-nav-title-bc ellipsis"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
      </a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<div class="hdr-nav-menu-holder-bc" id="mobileMenu" aria-hidden="true">
  <div class="m-navigation-container-bc">
    <div class="m-nav-title-row-bc">
      <div class="m-nav-title-content-bc"><?= htmlspecialchars($mobileMenuTitle, ENT_QUOTES, 'UTF-8') ?></div>
      <button type="button" class="closed-n-p-bc" id="mobileMenu-close" aria-label="Kapat">
        <i class="bc-i-close-remove" aria-hidden="true"></i>
      </button>
    </div>
    <div class="m-nav-info-w-container-bc">
      <div class="m-nav-menu-list-bc">
        <?php foreach ($mobileMenuSections as $section): ?>
          <?php
          if (!is_array($section)) {
              continue;
          }
          $sectionTitle = trim((string) ($section['title'] ?? ''));
          $items = is_array($section['items'] ?? null) ? $section['items'] : [];
          ?>
          <?php if ($sectionTitle !== ''): ?>
            <p class="m-nav-section-title"><?= htmlspecialchars($sectionTitle, ENT_QUOTES, 'UTF-8') ?></p>
          <?php endif; ?>
          <?php foreach ($items as $item): ?>
            <?php
            if (!is_array($item) || empty($item['enabled'])) {
                continue;
            }
            $href = (string) ($item['href'] ?? '#');
            $target = (string) ($item['target'] ?? '_self');
            $isBlank = $target === '_blank';
            $iconClass = $mobileMenuClass((string) ($item['icon'] ?? ''));
            $badge = trim((string) ($item['badge'] ?? ''));
            ?>
            <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
               class="m-nav-items-list-item-bc app-nav-link"
               target="<?= htmlspecialchars($isBlank ? '_blank' : '_self', ENT_QUOTES, 'UTF-8') ?>"
               <?= $isBlank ? 'rel="noopener"' : '' ?>>
              <?php if ($iconClass !== ''): ?>
                <i class="m-nav-icon-bc <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
              <?php endif; ?>
              <span class="m-nav-list-item-title-bc"><?= htmlspecialchars((string) ($item['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
              <?php if ($badge !== ''): ?>
                <span class="m-nav-category"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
