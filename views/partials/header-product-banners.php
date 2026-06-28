<?php
if (empty($headerBanners)) {
    include __DIR__ . '/header-banners-data.php';
}
?>
<div class="product-banner-container-bc product-banner-without-titles" role="list">
<?php foreach ($headerBanners as $b) :
    $href = htmlspecialchars($b['href'], ENT_QUOTES, 'UTF-8');
    $aria = htmlspecialchars($b['aria'], ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars($b['alt'], ENT_QUOTES, 'UTF-8');
    $src = headerBannerImageSrc($headerBannerBase, (string) $b['img']);
    $onclick = isset($b['onclick']) && $b['onclick']
        ? ' onclick="' . htmlspecialchars($b['onclick'], ENT_QUOTES, 'UTF-8') . '"'
        : '';
?>
  <a target="_self" class="product-banner-info-bc product-banner-bc" role="listitem" aria-label="<?= $aria ?>" href="<?= $href ?>"<?= $onclick ?>>
    <img alt="<?= $alt ?>" loading="lazy" src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" class="product-banner-img-bc" width="400" height="200" />
  </a>
<?php endforeach; ?>
</div>
