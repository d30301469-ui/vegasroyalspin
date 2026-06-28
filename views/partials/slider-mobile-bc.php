<?php
/**
 * Mobil ana sayfa slider — m.casinomilyon591 BC DOM (Swiper + fraction pagination)
 * Veri: $sliders (ApiSliders). Mobil görsel (mobile_path) zorunlu; yoksa desktop kullanılır.
 *
 * @var list<array<string, mixed>> $sliders
 */
if (empty($sliders)) {
    return;
}

$renderedSlides = [];
foreach ($sliders as $slider) {
    if (!is_array($slider) || !slider_item_has_media($slider)) {
        continue;
    }
    $mPath = trim((string) ($slider['mobileImageUrl'] ?? $slider['mobile_image_url'] ?? $slider['mobile_path'] ?? ''));
    $dPath = trim((string) ($slider['desktopImageUrl'] ?? $slider['desktop_image_url'] ?? $slider['desktop_path'] ?? $slider['image_url'] ?? ''));
    $usePath = $mPath !== '' ? $mPath : $dPath;
    $imgUrl = slider_build_url($usePath);
    if ($imgUrl === '') {
        continue;
    }
    $renderedSlides[] = [
        'slider' => $slider,
        'imgUrl' => $imgUrl,
        'isVideo' => slider_is_webm($imgUrl),
        'usesMobile' => $mPath !== '',
    ];
}

if ($renderedSlides === []) {
    return;
}

$totalSlides = count($renderedSlides);
?>
<div class="hm-row-bc has-slider" style="grid-template-columns: 12fr;" data-mobile-bc-slider-row>
  <div class="hm-row-bc-inner">
    <div class="slider-bc">
      <div class="carouselWrapper carouselCountEnable carouselArrowsDisabled" data-mobile-bc-slider>
        <div class="swiper mobile-bc-hero-swiper" dir="ltr">
          <div class="swiper-wrapper">
            <?php foreach ($renderedSlides as $idx => $slide): ?>
            <?php
            $slider = $slide['slider'];
            $imgUrl = $slide['imgUrl'];
            $isVideo = $slide['isVideo'];
            $title = htmlspecialchars((string) ($slider['title'] ?? 'Slider'), ENT_QUOTES, 'UTF-8');
            $linkRaw = trim((string) ($slider['sliderLink'] ?? $slider['slider_link'] ?? $slider['link'] ?? ''));
            $linkHref = $linkRaw !== '' ? htmlspecialchars($linkRaw, ENT_QUOTES, 'UTF-8') : 'javascript:void(0)';
            $isExternal = $linkRaw !== '' && preg_match('#^https?://#i', $linkRaw);
            $linkTarget = $linkRaw === '' ? '_self' : ($isExternal ? '_blank' : '_self');
            $linkRel = $isExternal ? ' rel="noopener noreferrer"' : '';
            $isFirst = $idx === 0;
            $loading = $isFirst ? 'eager' : 'lazy';
            $fetchPriority = $isFirst ? 'high' : 'low';
            $imgSrc = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
            ?>
            <div class="swiper-slide" data-swiper-slide-index="<?= (int) $idx ?>">
              <div class="sdr-item-holder-bc" style="aspect-ratio: 16 / 9;">
                <a class="sdr-item-bc"
                   href="<?= $linkHref ?>"
                   <?= $linkRaw === '' ? ' role="button" aria-disabled="true"' : '' ?>
                   target="<?= $linkTarget ?>"
                   aria-label="<?= $title ?>"<?= $linkRel ?>>
                  <?php if ($isVideo): ?>
                  <video class="sdr-image-bc"
                         src="<?= $imgSrc ?>"
                         autoplay
                         muted
                         loop
                         playsinline
                         aria-label="<?= $title ?>"></video>
                  <?php else: ?>
                  <img class="sdr-image-bc"
                       alt="<?= $title ?>"
                       title="<?= $title ?>"
                       loading="<?= $loading ?>"
                       fetchpriority="<?= $fetchPriority ?>"
                       decoding="async"
                       src="<?= $imgSrc ?>">
                  <?php endif; ?>
                </a>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-pagination swiper-pagination-fraction swiper-pagination-horizontal" aria-live="polite"></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
if (!defined('SLIDER_MOBILE_BC_ASSETS')) {
    define('SLIDER_MOBILE_BC_ASSETS', true);
    $sliderMobileJs = BASE_PATH . '/assets/js/slider-mobile-bc.js';
    $sliderMobileVer = (string) (file_exists($sliderMobileJs) ? filemtime($sliderMobileJs) : '1');
?>
<script defer src="/assets/js/slider-mobile-bc.js?v=<?= htmlspecialchars($sliderMobileVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php } ?>
