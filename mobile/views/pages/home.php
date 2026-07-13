<?php
$mobileHead = MOBILE_PATH . '/views/layouts/head.php';
if (is_file($mobileHead) && filesize($mobileHead) > 0) {
	include $mobileHead;
} else {
	include VIEW_PATH . '/layouts/head_full.php';
}
?>
<?php include MOBILE_PATH . '/views/partials/header.php'; ?>

<?php
// Mobil custom banner slider — localized image
$bannerImage = '/assets/images/slider-banner-main.webp';
?>
<div class="hm-row-bc has-slider" style="grid-template-columns: 12fr;">
  <div class="hm-row-bc-inner">
    <div class="slider-bc">
      <div class="carouselWrapper carouselCountEnable carouselArrowsDisabled" data-mobile-bc-slider>
        <div class="swiper mobile-bc-hero-swiper swiper-initialized swiper-horizontal swiper-ios swiper-backface-hidden" dir="ltr">
          <div class="swiper-wrapper">
            <div class="swiper-slide swiper-slide-active swiper-slide-next" data-swiper-slide-index="0" style="width: 390px;">
              <div class="sdr-item-holder-bc" style="aspect-ratio: 1291 / 50;">
                <a class="sdr-item-bc" aria-label="Başlık">
                  <img alt="Başlık" loading="eager" decoding="async" src="<?= htmlspecialchars($bannerImage, ENT_QUOTES, 'UTF-8') ?>" fetchpriority="high" class="sdr-image-bc" title="Başlık">
                </a>
              </div>
            </div>
          </div>
          <div class="swiper-pagination swiper-pagination-fraction swiper-pagination-horizontal swiper-pagination-lock">
            <span class="swiper-pagination-current">1</span> / <span class="swiper-pagination-total">1</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$sliderMobileBc = true;
$sliderApiCategory = 'home';
include VIEW_PATH . '/partials/slider.php';
?>
<?php include VIEW_PATH . '/partials/main-content.php'; ?>

<?php $homeJsVer = (string) (is_file(BASE_PATH . '/assets/js/home.js') ? filemtime(BASE_PATH . '/assets/js/home.js') : time()); ?>
<?php $winnersJsVer = (string) (is_file(BASE_PATH . '/assets/js/winners.js') ? filemtime(BASE_PATH . '/assets/js/winners.js') : $homeJsVer); ?>
<?php $jackpotJsVer = (string) (is_file(BASE_PATH . '/assets/js/jackpot.js') ? filemtime(BASE_PATH . '/assets/js/jackpot.js') : $homeJsVer); ?>
<script src="/assets/js/jackpot.js?v=<?= htmlspecialchars($jackpotJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/winners.js?v=<?= htmlspecialchars($winnersJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="/assets/js/home.js?v=<?= htmlspecialchars($homeJsVer, ENT_QUOTES, 'UTF-8') ?>"></script>
