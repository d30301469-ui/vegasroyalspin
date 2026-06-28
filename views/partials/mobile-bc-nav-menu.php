<?php
/** Orijinal: hdr-navigation-scrollable-bc-holder > scrollable > nav.hdr-navigation-scrollable-content > a.hdr-navigation-link-bc */
$mobileNavItems = [
    ['href' => '/sports', 'label' => 'ÖZEL ORAN', 'badge' => 'Özel', 'badgeClass' => 'badge-exclusive'],
    ['href' => '/canli-bahis', 'label' => 'CANLI BAHİS'],
    ['href' => '/sports', 'label' => 'MAÇ ÖNCESİ'],
    ['href' => '/slot', 'label' => 'CASİNO'],
    ['href' => '/livecasino', 'label' => 'CANLI CASİNO'],
    ['href' => '/slot', 'label' => 'OYUNLAR'],
    ['href' => '/sports', 'label' => 'E-SPORLAR'],
    ['href' => '/sports', 'label' => 'SANAL SPORLAR'],
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
      <a href="<?= $href ?>"
         class="<?= $linkClass ?>"
         target="_self"
         aria-label="<?= $label ?>"
         <?= $badge ? ' data-badge="' . $badge . '"' : '' ?>>
        <span class="nav-menu-title"><?= $label ?></span>
      </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
