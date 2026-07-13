<?php
include __DIR__ . '/header-banners-data.php';
$banners = $headerBanners;
$bannerBase = $headerBannerBase;
$homeIsMobile = function_exists('isMobile') && isMobile();
// Ürün banner'ları mobilde de slider altında gösterilir (3x2 grid)
$hideHomeBannersOnMobile = false;
$homeSurface = $homeIsMobile ? 'mobile' : 'desktop';

if (!function_exists('homeSectionH')) {
    function homeSectionH(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('homeSectionJs')) {
    function homeSectionJs(mixed $value): string
    {
        $encoded = json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($encoded) ? $encoded : '""';
    }
}

if (!function_exists('homeSectionByKey')) {
    function homeSectionByKey(array $sections, string $key): ?array
    {
        foreach ($sections as $section) {
            if (is_array($section) && (string) ($section['section_key'] ?? '') === $key) {
                return $section;
            }
        }

        return null;
    }
}

if (!function_exists('homeEnsureSections')) {
    /**
     * Fill missing/empty critical sections from default payload so homepage never renders blank.
     *
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    function homeEnsureSections(array $sections, string $surface = 'all'): array
    {
        if (!class_exists('ApiHomepageSections', false)) {
            return $sections;
        }

        $defaults = ApiHomepageSections::defaultSections($surface);
        $required = ['withdrawal-banner', 'casino', 'live-casino'];

        $byKey = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $key = (string) ($section['section_key'] ?? '');
            if ($key === '') {
                continue;
            }
            $byKey[$key] = $section;
        }

        foreach ($required as $key) {
            $current = $byKey[$key] ?? null;
            $fallback = homeSectionByKey($defaults, $key);
            if (!is_array($fallback)) {
                continue;
            }

            if (!is_array($current)) {
                $byKey[$key] = $fallback;
                continue;
            }

            $type = (string) ($current['type'] ?? 'games');
            if ($type === 'games') {
                $items = is_array($current['payload']['items'] ?? null) ? $current['payload']['items'] : [];
                if ($items === []) {
                    $byKey[$key] = $fallback;
                }
            } elseif ($type === 'banner') {
                $img = trim((string) ($current['payload']['image_url'] ?? ''));
                if ($img === '') {
                    $byKey[$key] = $fallback;
                }
            }
        }

        return array_values($byKey);
    }
}

if (!function_exists('homeRenderBannerSection')) {
    function homeRenderBannerSection(?array $section): void
    {
        $payload = is_array($section['payload'] ?? null) ? $section['payload'] : [];
        $image = trim((string) ($payload['image_url'] ?? ''));
        if ($image === '') {
            return;
        }
        $alt = (string) ($payload['alt'] ?? '');
        $href = trim((string) ($payload['href'] ?? ''));
        $onclick = trim((string) ($payload['onclick'] ?? ''));

        // Mobil yüzeyde (UA veya m. host) bu alanda kullanıcıdan gelen URL ile birebir banner kullan.
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $isMobileSurface = (function_exists('isMobile') && isMobile()) || strpos($host, 'm.') === 0;
        $forceMobileBanner = false;
        if ($isMobileSurface) {
            $image = 'assets/images/slider-banner-main.webp';
            $forceMobileBanner = true;
            if (trim($alt) === '') {
                $alt = 'Artık çekimlerinizde limitlerde takılmak yok';
            }
        }

        if ($forceMobileBanner) {
            $absFile = defined('BASE_PATH') ? BASE_PATH . '/' . ltrim($image, '/') : '';
            $ver = (is_string($absFile) && $absFile !== '' && is_file($absFile)) ? (string) filemtime($absFile) : (string) time();
            $src = '/' . ltrim($image, '/') . '?v=' . rawurlencode($ver);
        } else {
            $src = preg_match('#^https?://#i', $image) ? $image : (function_exists('asset_url') ? asset_url($image) : '/' . ltrim($image, '/'));
        }
        ?>
        <div class="live-casino-banner-wrap" style="display:block !important;">
            <div class="live-casino-banner">
                <?php if ($href !== ''): ?>
                <a href="<?= homeSectionH($href) ?>"<?= $onclick !== '' ? ' onclick="' . homeSectionH($onclick) . '"' : '' ?>>
                <?php endif; ?>
                    <img
                        src="<?= homeSectionH($src) ?>"
                        alt="<?= homeSectionH($alt) ?>"
                        loading="lazy"
                        width="1200"
                        height="360"
                    />
                <?php if ($href !== ''): ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('homeRenderGameCard')) {
    function homeRenderGameCard(array $card): void
    {
        $gameId = (int) ($card['game_id'] ?? 0);
        $title = trim((string) ($card['title'] ?? ''));
        $image = trim((string) ($card['image_url'] ?? ''));
        if ($title === '' || $image === '') {
            return;
        }
        $alt = trim((string) ($card['alt'] ?? $title));
        $link = trim((string) ($card['link'] ?? ''));
        $class = (string) ($card['size'] ?? 'normal') === 'featured' ? 'game-cta' : 'game-item';
        $imageFit = (string) ($card['image_fit'] ?? 'fill');
        $imageFit = in_array($imageFit, ['cover', 'fill'], true) ? $imageFit : 'fill';
        $imageScale = $imageFit === 'fill' ? 1 : max(40, min(120, (int) ($card['image_scale'] ?? 100))) / 100;
        $onclick = $gameId > 0
            ? 'handlePlay(' . $gameId . ')'
            : ($link !== '' ? 'window.location.href=' . homeSectionJs($link) : '');
        $playHref = $link !== '' ? $link : '#';
        $playOnclick = $gameId > 0
            ? 'handlePlay(' . $gameId . '); return false;'
            : ($link !== '' ? '' : 'event.stopPropagation(); return false;');
        $demoHref = $gameId > 0
            ? '/play?game_id=' . rawurlencode((string) $gameId) . '&mode=fun&demo=1'
            : '#';
        $demoOnclick = $gameId > 0
            ? 'handleDemo(' . $gameId . '); return false;'
            : 'event.stopPropagation(); return false;';
        ?>
        <div class="<?= homeSectionH($class) ?>"<?= $onclick !== '' ? ' onclick="' . homeSectionH($onclick) . '"' : '' ?>>
            <img loading="lazy" decoding="async" src="<?= homeSectionH($image) ?>" alt="<?= homeSectionH($alt) ?>" width="200" height="200" style="object-fit: <?= homeSectionH($imageFit) ?>; --home-image-scale: <?= homeSectionH((string) $imageScale) ?>;">
            <div class="game-overlay">
                <div class="game-overlay-top">
                    <span class="game-fav"><i class="far fa-star"></i></span>
                    <a href="#" class="game-info-btn" onclick="event.stopPropagation(); return false;" aria-label="Bilgi"><i class="fas fa-info-circle"></i></a>
                </div>
                <div class="game-title-wrap">
                    <p class="game-title-text"><?= homeSectionH($title) ?></p>
                </div>
                <div class="game-actions">
                    <a href="<?= homeSectionH($playHref) ?>" class="play-btn"<?= $playOnclick !== '' ? ' onclick="' . homeSectionH($playOnclick) . '"' : '' ?>>OYNA</a>
                    <a href="<?= homeSectionH($demoHref) ?>" class="demo-btn" onclick="<?= homeSectionH($demoOnclick) ?>">DEMO</a>
                </div>
            </div>
        </div>
        <?php
    }
}

if (!function_exists('homeRenderGameSection')) {
    function homeRenderGameSection(?array $section, string $fallbackTitle, string $fallbackHref, bool $isLive = false): void
    {
        if (!is_array($section)) {
            return;
        }
        $payload = is_array($section['payload'] ?? null) ? $section['payload'] : [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if ($items === []) {
            return;
        }
        $title = trim((string) ($section['title'] ?? $fallbackTitle));
        $href = trim((string) ($payload['href'] ?? $fallbackHref));
        ?>
        <section class="title-container<?= $isLive ? ' live-casino-title' : '' ?>">
            <div class="title">
                <h2<?= $isLive ? ' class="live-casino-heading"' : '' ?>><?= homeSectionH($title !== '' ? $title : $fallbackTitle) ?></h2>
                <?php if ($href !== ''): ?>
                <a href="<?= homeSectionH($href) ?>">Tümü</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="games-container<?= $isLive ? ' live-casino' : '' ?>">
            <div class="games-inner">
                <?php foreach ($items as $card): ?>
                    <?php if (is_array($card)) { homeRenderGameCard($card); } ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}

try {
    require_once dirname(__DIR__, 2) . '/api/bootstrap.php';
    $homeSections = ApiHomepageSections::fetchForSurface($homeSurface);
    $homeSections = homeEnsureSections(is_array($homeSections) ? $homeSections : [], $homeSurface);
} catch (Throwable) {
    $homeSections = class_exists('ApiHomepageSections', false)
        ? ApiHomepageSections::defaultSections($homeSurface)
        : [];
}
?>
<?php if (!$hideHomeBannersOnMobile): ?>
<div class="hm-row-bc hm-row-banners-in-header">
<?php foreach ($banners as $b) :
  $href = htmlspecialchars($b['href'], ENT_QUOTES, 'UTF-8');
  $aria = htmlspecialchars($b['aria'], ENT_QUOTES, 'UTF-8');
  $alt = htmlspecialchars($b['alt'], ENT_QUOTES, 'UTF-8');
  $src = headerBannerImageSrc($bannerBase, (string) $b['img']);
  $onclick = isset($b['onclick']) && $b['onclick'] ? ' onclick="' . htmlspecialchars($b['onclick'], ENT_QUOTES, 'UTF-8') . '"' : '';
?>
  <a target="_self" class="product-banner-info-bc product-banner-bc" aria-label="<?= $aria ?>" href="<?= $href ?>"<?= $onclick ?>>
    <img alt="<?= $alt ?>" loading="lazy" src="<?= $src ?>" class="product-banner-img-bc" width="400" height="200" />
  </a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<section class="ligler-container">
    <div class="ligler-row">
        <?php
        $partnerLogos = [];
        if (!class_exists('ApiFooter', false)) {
            require_once (defined('API_PATH') ? API_PATH : dirname(__DIR__, 2) . '/api') . '/bootstrap.php';
        }
        try {
            $footerPayload = ApiFooter::fetch();
            $partnerLogos = is_array($footerPayload['partner_logos'] ?? null) ? $footerPayload['partner_logos'] : [];
        } catch (Throwable) {
            $partnerLogos = [];
        }

        if ($partnerLogos === []) {
            $liglerDir = 'assets/images/ligler';
            $liglerImages = glob($liglerDir . '/*.{png,jpg,jpeg,webp,svg,gif}', GLOB_BRACE);
            if (!empty($liglerImages)) {
                foreach ($liglerImages as $ligImg) {
                    $partnerLogos[] = [
                        'src' => $ligImg,
                        'alt' => pathinfo($ligImg, PATHINFO_FILENAME),
                        'href' => '',
                    ];
                }
            }
        }

        foreach ($partnerLogos as $logo) {
            if (!is_array($logo)) {
                continue;
            }
            $src = trim((string) ($logo['src'] ?? $logo['image_url'] ?? ''));
            if ($src === '') {
                continue;
            }
            $altText = trim((string) ($logo['alt'] ?? pathinfo($src, PATHINFO_FILENAME)));
            $href = trim((string) ($logo['href'] ?? ''));
            ?>
                <div class="lig-item">
                    <?php if ($href !== ''): ?>
                    <a href="<?= htmlspecialchars($href, ENT_QUOTES); ?>">
                    <?php endif; ?>
                    <img
                        src="<?= htmlspecialchars($src, ENT_QUOTES); ?>"
                        alt="<?= htmlspecialchars($altText, ENT_QUOTES); ?>"
                        loading="lazy"
                        width="80"
                        height="60"
                    />
                    <?php if ($href !== ''): ?>
                    </a>
                    <?php endif; ?>
                </div>
                <?php
        }
        ?>
    </div>
</section>

<?php if (!empty($showJackpotWinnersRow)): ?>
<section class="home-jackpot-winners-section" aria-label="Jackpot ve son kazananlar">
<?php if (function_exists('isMobile') && isMobile()): ?>
  <div class="slot-hero-tabs" data-slot-hero-tabs>
    <div class="slot-hero-tablist" role="tablist" aria-label="Jackpot ve kazananlar">
      <button type="button"
              class="slot-hero-tab slot-hero-tab--active"
              id="home-hero-tab-jackpot"
              role="tab"
              aria-selected="true"
              aria-controls="home-hero-panel-jackpot"
              data-slot-hero-tab="jackpot">JACKPOT</button>
      <button type="button"
              class="slot-hero-tab"
              id="home-hero-tab-winners"
              role="tab"
              aria-selected="false"
              aria-controls="home-hero-panel-winners"
              data-slot-hero-tab="winners">KAZANANLAR</button>
    </div>
    <div class="slot-hero-panels">
      <div class="slot-hero-tabpanel slot-hero-tabpanel--active"
           id="home-hero-panel-jackpot"
           role="tabpanel"
           aria-labelledby="home-hero-tab-jackpot"
           data-slot-hero-panel="jackpot">
        <div class="slot-jackpot-wrap">
          <?php include __DIR__ . '/jackpot.php'; ?>
        </div>
      </div>
      <div class="slot-hero-tabpanel"
           id="home-hero-panel-winners"
           role="tabpanel"
           aria-labelledby="home-hero-tab-winners"
           data-slot-hero-panel="winners"
           hidden>
        <div class="slot-winners-wrap">
          <?php include __DIR__ . '/winners.php'; ?>
        </div>
      </div>
    </div>
  </div>
<?php else: ?>
  <div class="home-jackpot-winners-row">
    <div class="slot-jackpot-wrap">
      <?php include __DIR__ . '/jackpot.php'; ?>
    </div>
    <div class="slot-winners-wrap">
      <?php include __DIR__ . '/winners.php'; ?>
    </div>
  </div>
<?php endif; ?>
</section>
<?php else: ?>
<?php include __DIR__ . '/jackpot.php'; ?>
<?php endif; ?>

<?php
homeRenderBannerSection(homeSectionByKey($homeSections, 'withdrawal-banner'));
homeRenderGameSection(homeSectionByKey($homeSections, 'casino'), 'Casino', '/slot');
homeRenderGameSection(homeSectionByKey($homeSections, 'live-casino'), 'Canl─▒ Casino', '/livecasino', true);
?>

<?php
if (function_exists('isMobile') && isMobile() && defined('MOBILE_PATH')) {
    $mobileFooter = MOBILE_PATH . '/views/partials/footer.php';
    if (file_exists($mobileFooter)) {
        include $mobileFooter;
        return;
    }
}
include __DIR__ . '/footer.php';
?>
