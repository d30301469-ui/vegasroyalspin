<?php
/** Orijinal: hdr-navigation-scrollable-bc-holder > scrollable > nav.hdr-navigation-scrollable-content > a.hdr-navigation-link-bc */
$mobileNavItems = [
    ['href' => '/sportbook', 'label' => 'SPOR', 'badge' => 'Özel', 'badgeClass' => 'badge-exclusive'],
    ['href' => '/slot', 'label' => 'CASİNO'],
    ['href' => '/livecasino', 'label' => 'CANLI CASİNO'],
    ['href' => '/bgaming', 'label' => 'BGaming', 'badge' => 'YENİ', 'badgeClass' => 'badge-new'],
    ['href' => '/promotions', 'label' => 'PROMOSYONLAR'],
];
?>
<div class="hdr-navigation-scrollable-bc-holder" data-mobile-nav-strip>
  <div class="hdr-navigation-scrollable-bc scroll-start" data-scroll-lock-scrollable="">
    <nav class="hdr-navigation-scrollable-content" aria-label="Ürün menüsü">
      <?php foreach ($mobileNavItems as $item):
        $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        $badge = isset($item['badge']) ? htmlspecialchars($item['badge'], ENT_QUOTES, 'UTF-8') : '';
        $badgeClass = isset($item['badgeClass']) ? htmlspecialchars($item['badgeClass'], ENT_QUOTES, 'UTF-8') : '';
        $linkClass = 'hdr-navigation-link-bc' . ($badgeClass ? ' ' . $badgeClass : '');
        ?>
      <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
         class="<?= htmlspecialchars($linkClass, ENT_QUOTES, 'UTF-8') ?>"
         target="_self"
         aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
         <?= $badge ? ' data-badge="' . $badge . '"' : '' ?>>
        <span class="nav-menu-title"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
