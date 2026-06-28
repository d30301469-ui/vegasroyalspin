<?php
/**
 * Auth modal arka plan slider katmanı.
 *
 * Beklenen değişkenler:
 * - $authSliderScreen: login|register
 * - $authSliderItems: ApiAuthSliders::fetchFor() sonucu
 */
$authSliderScreen = in_array((string) ($authSliderScreen ?? ''), ['login', 'register'], true) ? (string) $authSliderScreen : 'login';
$authSliderItems = is_array($authSliderItems ?? null) ? array_values($authSliderItems) : [];
$authSliderCount = count($authSliderItems);
?>
<?php if ($authSliderCount > 0): ?>
<div class="auth-slider-bg auth-slider-bg--<?= htmlspecialchars($authSliderScreen, ENT_QUOTES, 'UTF-8') ?>"
     data-auth-slider-screen="<?= htmlspecialchars($authSliderScreen, ENT_QUOTES, 'UTF-8') ?>"
     data-auth-slider-count="<?= (int) $authSliderCount ?>"
     aria-hidden="true">
    <?php foreach ($authSliderItems as $index => $slide): ?>
        <?php
        $mediaPath = trim((string) ($slide['mediaPath'] ?? ''));
        if ($mediaPath === '') {
            continue;
        }
        $title = trim((string) ($slide['title'] ?? ''));
        $alt = trim((string) ($slide['mediaAlt'] ?? ''));
        $alt = $alt !== '' ? $alt : $title;
        ?>
        <div class="auth-slider-bg__slide"
             style="--auth-slide-index: <?= (int) $index ?>; --auth-slide-count: <?= (int) $authSliderCount ?>;">
            <img src="<?= htmlspecialchars($mediaPath, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') ?>"
                 class="auth-slider-bg__image"
                 loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
