<?php
/**
 * Slider partial — veri kaynağı: api/Sliders.php (ApiSliders)
 * $sliderApiCategory = 'home'|'slots'|'live_casino'
 */
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}
if (!defined('API_PATH')) {
    define('API_PATH', BASE_PATH . '/api');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', defined('FRONTEND_URL') ? (string) FRONTEND_URL : 'https://vegasroyalspin.com');
}

if (!class_exists('ApiSliders', false)) {
    require_once BASE_PATH . '/api/bootstrap.php';
}

function slider_build_url(string $path): string
{
    if (class_exists('ApiMediaUrl', false)) {
        return ApiMediaUrl::resolve($path);
    }

    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    $basePath = '';
    if (defined('SITE_URL')) {
        $basePath = (string) (parse_url((string) SITE_URL, PHP_URL_PATH) ?: '');
        $basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
    }

    return $basePath . $path;
}

function slider_is_webm(string $url): bool
{
    return (bool) preg_match('~\.webm(\?.*)?$~i', parse_url($url, PHP_URL_PATH) ?? $url);
}

function slider_is_supported_media_url(string $url): bool
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    if ($path === '') {
        return false;
    }
    return (bool) preg_match('~\.(?:webp|png|jpe?g|gif|avif|svg|webm|mp4)(?:\?.*)?$~i', $path);
}

function slider_make_media(string $url, bool $isVideo, string $title, string $cls = ''): string
{
    $src = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $classes = trim('sdr-image-bc ' . $cls);
    $clsAttr = ' class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '"';
    return $isVideo
        ? "<video$clsAttr src=\"$src\" autoplay muted loop playsinline aria-label=\"$title\"></video>"
        : "<img$clsAttr src=\"$src\" alt=\"$title\">";
}

/**
 * Slider öğesinin medyası olup olmadığını kontrol eder.
 *
 * @param array<string, mixed> $slider Slider verisi
 * @return bool
 */
function slider_item_has_media(array $slider): bool
{
    $dPath = (string) ($slider['desktopImageUrl'] ?? $slider['desktop_image_url'] ?? $slider['desktop_path'] ?? '');
    $mPath = (string) ($slider['mobileImageUrl'] ?? $slider['mobile_image_url'] ?? $slider['mobile_path'] ?? '');

    $dUrl = slider_build_url($dPath);
    $mUrl = slider_build_url($mPath);
    $isMobileSurface = defined('SURFACE') && SURFACE === 'mobile';
    if ($isMobileSurface) {
        return slider_is_supported_media_url($mUrl);
    }

    return slider_is_supported_media_url($dUrl);
}

$sliderApiCategory = $sliderApiCategory ?? 'home';
$sliderFullSized     = $sliderFullSized ?? ($sliderApiCategory === 'home');
// Mobile SURFACE'ta her zaman BC Swiper slider'ı kullan
if (!isset($sliderMobileBc) || $sliderMobileBc === false) {
    $sliderMobileBc = defined('SURFACE') && SURFACE === 'mobile';
} else {
    $sliderMobileBc = !empty($sliderMobileBc);
}
$sliders             = ApiSliders::fetchForCategory($sliderApiCategory);
$sliders             = array_values(array_filter($sliders, 'slider_item_has_media'));
?>
<?php if (!empty($sliders)): ?>
<?php if ($sliderMobileBc): ?>
<?php include __DIR__ . '/slider-mobile-bc.php'; ?>
<?php else: ?>
<?php $totalSlides = count($sliders); ?>
<?php if ($sliderFullSized): ?><div class="hm-row-bc hm-row-slider-bc"><?php endif; ?>
<div class="hero-slider-stage<?= $sliderFullSized ? ' slider-full-sized' : ' slider-boxed' ?>">
<div class="home-hero-slider slider-bc">
    <div class="carousel carouselCountEnable carouselArrowsEnabled carouselWrapper">
        <div class="home-hero-slider-inner slides-holder">
            <div class="slides">
                <?php foreach ($sliders as $sliderIndex => $slider): ?>
                <?php
                $dPath = (string) ($slider['desktopImageUrl'] ?? $slider['desktop_image_url'] ?? $slider['desktop_path'] ?? '');
                $mPath = (string) ($slider['mobileImageUrl'] ?? $slider['mobile_image_url'] ?? $slider['mobile_path'] ?? '');
                $dUrl = slider_build_url($dPath);
                $dMediaOk = slider_is_supported_media_url($dUrl);
                if (!$dMediaOk) {
                    continue;
                }

                $dVid = slider_is_webm($dUrl);

                $title = htmlspecialchars($slider['title'] ?? 'Slider görseli', ENT_QUOTES, 'UTF-8');
                $linkRaw = trim((string) ($slider['sliderLink'] ?? $slider['slider_link'] ?? $slider['link'] ?? ''));
                // Yalnızca güvenli şemalara izin ver: http(s), protokol-bağımsız (//) ve
                // site içi göreli yollar (/...). javascript:, data:, vbscript: vb. engellenir.
                $linkSafe = '';
                if ($linkRaw !== '') {
                    $scheme = strtolower((string) parse_url($linkRaw, PHP_URL_SCHEME));
                    if ($scheme === 'http' || $scheme === 'https') {
                        $linkSafe = $linkRaw;
                    } elseif (str_starts_with($linkRaw, '//') || str_starts_with($linkRaw, '/')) {
                        $linkSafe = $linkRaw;
                    }
                }
                $link = $linkSafe !== '' ? htmlspecialchars($linkSafe, ENT_QUOTES, 'UTF-8') : null;

                $src  = htmlspecialchars($dUrl, ENT_QUOTES, 'UTF-8');
                $tag  = $dVid ? 'video' : 'img';
                $attr = $dVid ? 'autoplay muted loop playsinline aria-label="' . $title . '"' : 'alt="' . $title . '"';
                $imgAttrs = $dVid ? '' : ' ';
                $media = "<$tag class=\"sdr-image-bc\" src=\"$src\"$imgAttrs $attr>" . ($dVid ? '</video>' : '');
                ?>
                <div class="sdr-item-holder-bc slide-item<?= ((int) $sliderIndex === 0) ? ' active' : '' ?>">
                    <?php if ($link): ?>
                        <a href="<?= $link ?>" class="sdr-item-bc" target="_blank" rel="noopener noreferrer" aria-label="<?= $title ?>"><?= $media ?></a>
                    <?php else: ?>
                        <div class="sdr-item-bc"><?= $media ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="carousel-count-arrow-container with-count">
                <button class="swiper-button-prev home-hero-slider-counter-prev" type="button" aria-label="Önceki görsel"></button>
                <div class="swiper-pagination home-hero-slider-counter-text">1/<?= $totalSlides ?></div>
                <button class="swiper-button-next home-hero-slider-counter-next" type="button" aria-label="Sonraki görsel"></button>
            </div>
        </div>
    </div>
</div>
</div>
<?php if ($sliderFullSized): ?></div><?php endif; ?>
<?php endif; ?>
<?php
if (!$sliderMobileBc && !defined('SLIDER_ASSETS_IN_HEAD')) {
    $sliderCss = BASE_PATH . '/assets/css/slider.css';
    $sliderJs  = BASE_PATH . '/assets/js/slider.js';
    $sliderVer = (string) max(
        file_exists($sliderCss) ? filemtime($sliderCss) : 0,
        file_exists($sliderJs) ? filemtime($sliderJs) : 0
    ) ?: '1';
?>
<link href="/assets/css/slider.css?v=<?= $sliderVer ?>" rel="stylesheet">
<script defer src="/assets/js/slider.js?v=<?= $sliderVer ?>"></script>
<?php } ?>
<?php endif; ?>
