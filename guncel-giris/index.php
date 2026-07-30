<?php
/**
 * AMP SEO — Güncel Giriş landing (standalone).
 * Ana site bootstrap/DB gerektirmez; open_basedir altında bağımsız çalışır.
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$siteName = 'Vegasroyalspin';
$siteDesc = 'Güvenilir casino ve bahis';

$logoUrl = 'https://ik.imagekit.io/vegasroyalspin/logo/logo-vegasroyalspin.webp?updatedAt=1784722389832';
$faviconUrl = 'https://ik.imagekit.io/vegasroyalspin/logo/site-ico.webp?updatedAt=1784924351291';
// Kaynak ikon 312x206; kare gerektiren yerlerde ImageKit ile marka zeminine dolgulanır.
$iconSquare = static fn (int $size): string => 'https://ik.imagekit.io/vegasroyalspin/logo/site-ico.webp'
    . '?tr=w-' . $size . ',h-' . $size . ',cm-pad_resize,bg-07040F&updatedAt=1784924351291';

$faviconHref = $faviconUrl;
$appleTouch = $iconSquare(180);
$favicon32 = $iconSquare(32);
$favicon16 = $iconSquare(16);
$faviconIco = $faviconUrl;

$baseUrl = 'https://vegasroyalspin.com';
$loginUrl = $baseUrl . '/login';
$registerUrl = $baseUrl . '/register';
// Canonical her ortamda resmi path; production host'ta ana sayfaya düşürme.
$pageUrl = $baseUrl . '/guncel-giris/';

// Sosyal paylaşım önizlemesinde geniş logo yerine kare favicon kullanılır.
$ogImageSize = 512;
$ogImage = $iconSquare($ogImageSize);
$logoAbs = $logoUrl;
$hostLabel = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: 'vegasroyalspin.com');
$datePublished = '2026-01-15';
$dateModified = '2026-07-30';

$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$pageTitle = $siteName . ' Güvenli Güncel Giriş 2026 | Resmi SSL Adres';
$pageDesc = $siteName . ' güvenli güncel giriş: resmi ' . $hostLabel . ' adresi, SSL korumalı erişim, sahte site uyarısı ve güvenli üyelik. Hesabınızı koruyarak casino ve spora giriş yapın.';
$pageKeywords = implode(', ', [
    $siteName . ' güvenli giriş',
    $siteName . ' güncel giriş',
    'vegasroyalspin resmi site',
    'vegasroyalspin ssl',
    'sahte site uyarısı',
    $siteName . ' güvenli adres',
    $hostLabel . ' giriş',
]);

$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        [
            '@type' => 'Organization',
            '@id' => $baseUrl . '/#organization',
            'name' => $siteName,
            'alternateName' => ['Vegas Royal Spin', 'Vegasroyalspin', 'VegasRoyalSpin'],
            'url' => $baseUrl . '/',
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => $baseUrl . '/#logo',
                'url' => $logoAbs,
                'contentUrl' => $logoAbs,
                'width' => 5438,
                'height' => 571,
                'caption' => $siteName,
            ],
            'image' => ['@id' => $baseUrl . '/#logo'],
            'description' => $siteDesc,
            'sameAs' => [
                $baseUrl . '/',
            ],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $baseUrl . '/#website',
            'url' => $baseUrl . '/',
            'name' => $siteName,
            'alternateName' => ['Vegas Royal Spin', 'Vegasroyalspin'],
            'description' => $siteDesc,
            'inLanguage' => 'tr-TR',
            'publisher' => ['@id' => $baseUrl . '/#organization'],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $pageUrl . '#webpage',
            'url' => $pageUrl,
            'name' => $pageTitle,
            'headline' => $siteName . ' Güvenli Güncel Giriş 2026',
            'description' => $pageDesc,
            'isPartOf' => ['@id' => $baseUrl . '/#website'],
            'about' => ['@id' => $baseUrl . '/#organization'],
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => $ogImage,
                'width' => $ogImageSize,
                'height' => $ogImageSize,
                'caption' => $siteName,
            ],
            'inLanguage' => 'tr-TR',
            'datePublished' => $datePublished,
            'dateModified' => $dateModified,
            'breadcrumb' => ['@id' => $pageUrl . '#breadcrumb'],
            'mainEntity' => ['@id' => $pageUrl . '#faq'],
            'speakable' => [
                '@type' => 'SpeakableSpecification',
                'cssSelector' => ['#hero-title', '#address-title', '.hero-lead'],
            ],
            'potentialAction' => [
                [
                    '@type' => 'ReadAction',
                    'target' => [$pageUrl],
                ],
            ],
        ],
        [
            '@type' => 'BreadcrumbList',
            '@id' => $pageUrl . '#breadcrumb',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Ana Sayfa',
                    'item' => $baseUrl . '/',
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Güncel Giriş',
                    'item' => $pageUrl,
                ],
            ],
        ],
        [
            '@type' => 'HowTo',
            '@id' => $pageUrl . '#howto',
            'name' => $siteName . ' güvenli güncel giriş nasıl yapılır?',
            'description' => $siteName . ' resmi adresini doğrulayarak SSL korumalı girişe 3 adımda ulaşın.',
            'inLanguage' => 'tr-TR',
            'totalTime' => 'PT2M',
            'step' => [
                [
                    '@type' => 'HowToStep',
                    'position' => 1,
                    'name' => 'Resmi adresi doğrulayın',
                    'text' => 'Adres çubuğunda https://' . $hostLabel . ' yazdığını kontrol edin. Farklı domain veya kısa linklere tıklamayın.',
                    'url' => $pageUrl . '#how-title',
                ],
                [
                    '@type' => 'HowToStep',
                    'position' => 2,
                    'name' => 'SSL kilit simgesini kontrol edin',
                    'text' => 'Tarayıcıda kilit / HTTPS göstergesini doğrulayın. Bu sayfadaki Güvenli Giriş butonu sizi resmi SSL giriş ekranına götürür.',
                    'url' => $pageUrl . '#how-title',
                ],
                [
                    '@type' => 'HowToStep',
                    'position' => 3,
                    'name' => 'Hesabınıza güvenle girin',
                    'text' => 'Bilgilerinizi yalnızca resmi ' . $siteName . ' formunda girin. Şifrenizi asla SMS veya e-posta ile paylaşmayın.',
                    'url' => $loginUrl,
                ],
            ],
        ],
        [
            '@type' => 'FAQPage',
            '@id' => $pageUrl . '#faq',
            'inLanguage' => 'tr-TR',
            'mainEntity' => [
                [
                    '@type' => 'Question',
                    'name' => $siteName . ' güvenli güncel giriş adresi nedir?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $siteName . ' resmi ve güvenli güncel giriş adresi https://' . $hostLabel . ' üzerindedir. Yalnızca HTTPS ile açılan resmi domaini kullanın.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => $siteName . ' giriş güvenli mi?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Evet. Resmi site SSL ile korunur. Bu sayfa sizi sahte / phishing sitelere değil, doğrulanmış ' . $hostLabel . ' giriş ekranına yönlendirir.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Sahte ' . $siteName . ' sitesini nasıl anlarım?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Domain adında harf/ek farkı, HTTP (kilit yok), şüpheli kısa linkler ve şifre isteyen mesajlar sahte site işaretidir. Yalnızca https://' . $hostLabel . ' kullanın.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Güncel giriş neden değişir?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Erişim kısıtlamaları veya önbellek sorunları resmi adrese ulaşmayı zorlaştırabilir. Bu güvenli güncel giriş sayfasını yer imine ekleyerek her zaman doğrulanmış https://' . $hostLabel . ' adresine yönlendirilirsiniz.',
                    ],
                ],
                [
                    '@type' => 'Question',
                    'name' => 'Şifremi kimseyle paylaşmalı mıyım?',
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => 'Hayır. ' . $siteName . ' şifrenizi SMS, Telegram veya e-posta ile asla istemez. Giriş bilgilerinizi yalnızca resmi sitedeki forma yazın.',
                    ],
                ],
            ],
        ],
    ],
];
?>
<!doctype html>
<html ⚡ lang="tr">
<head>
  <meta charset="utf-8">
  <script async src="https://cdn.ampproject.org/v0.js"></script>
  <script async custom-element="amp-fx-collection" src="https://cdn.ampproject.org/v0/amp-fx-collection-0.1.js"></script>
  <meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1">
  <link rel="canonical" href="<?= $e($pageUrl) ?>">
  <link rel="amphtml" href="<?= $e($pageUrl) ?>">
  <title><?= $e($pageTitle) ?></title>
  <meta name="description" content="<?= $e($pageDesc) ?>">
  <meta name="keywords" content="<?= $e($pageKeywords) ?>">
  <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
  <meta name="googlebot" content="index, follow, max-snippet:-1, max-image-preview:large">
  <meta name="bingbot" content="index, follow">
  <meta name="author" content="<?= $e($siteName) ?>">
  <meta name="publisher" content="<?= $e($siteName) ?>">
  <meta name="theme-color" content="#07040f">
  <meta name="color-scheme" content="dark">
  <meta name="format-detection" content="telephone=no">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <meta name="language" content="Turkish">
  <meta name="geo.region" content="TR">
  <link rel="alternate" hreflang="tr" href="<?= $e($pageUrl) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= $e($pageUrl) ?>">
  <meta property="og:locale" content="tr_TR">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= $e($siteName) ?>">
  <meta property="og:title" content="<?= $e($siteName . ' Güvenli Güncel Giriş 2026 | Resmi SSL Adres') ?>">
  <meta property="og:description" content="<?= $e($pageDesc) ?>">
  <meta property="og:url" content="<?= $e($pageUrl) ?>">
  <meta property="og:image" content="<?= $e($ogImage) ?>">
  <meta property="og:image:secure_url" content="<?= $e($ogImage) ?>">
  <meta property="og:image:type" content="image/webp">
  <meta property="og:image:width" content="<?= $e((string) $ogImageSize) ?>">
  <meta property="og:image:height" content="<?= $e((string) $ogImageSize) ?>">
  <meta property="og:image:alt" content="<?= $e($siteName . ' güvenli giriş') ?>">
  <meta property="og:updated_time" content="<?= $e($dateModified) ?>T12:00:00+03:00">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= $e($siteName . ' Güvenli Güncel Giriş 2026') ?>">
  <meta name="twitter:description" content="<?= $e('Resmi SSL adresi: https://' . $hostLabel . ' — sahte sitelere karşı güvenli giriş.') ?>">
  <meta name="twitter:image" content="<?= $e($ogImage) ?>">
  <meta name="twitter:image:alt" content="<?= $e($siteName . ' logo') ?>">
  <link rel="icon" href="<?= $e($faviconHref) ?>">
  <link rel="icon" sizes="32x32" href="<?= $e($favicon32) ?>">
  <link rel="icon" sizes="16x16" href="<?= $e($favicon16) ?>">
  <link rel="shortcut icon" href="<?= $e($faviconIco) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $e($appleTouch) ?>">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&amp;display=swap">
  <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?></script>
  <style amp-boilerplate>body{-webkit-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-moz-animation:-amp-start 8s steps(1,end) 0s 1 normal both;-ms-animation:-amp-start 8s steps(1,end) 0s 1 normal both;animation:-amp-start 8s steps(1,end) 0s 1 normal both}@-webkit-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-moz-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-ms-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@-o-keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}@keyframes -amp-start{from{visibility:hidden}to{visibility:visible}}</style>
  <noscript><style amp-boilerplate>body{-webkit-animation:none;-moz-animation:none;-ms-animation:none;animation:none}</style></noscript>
  <style amp-custom>
    :root {
      --p: #850f83;
      --p-lite: #c43bc0;
      --p-deep: #4a0a49;
      --g: #109121;
      --g-lite: #1ad134;
      --gold: #e9c40a;
      --bg: #07040f;
      --bg-2: #0c0719;
      --surface: rgba(20, 11, 36, 0.66);
      --surface-2: rgba(28, 15, 50, 0.55);
      --hair: rgba(255, 255, 255, 0.08);
      --hair-2: rgba(196, 59, 192, 0.28);
      --text: #f5f2fb;
      --muted: #a99cc4;
      --lilac: #dcc9ff;
      --max: 1280px;
      --ease: cubic-bezier(0.22, 1, 0.36, 1);
      --font: "Inter", "Segoe UI", Roboto, Arial, Helvetica, sans-serif;
    }

    * { box-sizing: border-box; }
    html { scroll-behavior: smooth; scroll-padding-top: 92px; }

    body {
      margin: 0;
      font-family: var(--font);
      background: var(--bg);
      color: var(--text);
      line-height: 1.6;
      letter-spacing: -0.005em;
      -webkit-font-smoothing: antialiased;
      overflow-x: hidden;
    }

    a { color: inherit; text-decoration: none; }

    /* ── Ambient background layers ── */
    .ambient {
      position: fixed;
      inset: 0;
      z-index: 0;
      pointer-events: none;
      overflow: hidden;
      background:
        radial-gradient(ellipse 55% 40% at 12% 12%, rgba(133, 15, 131, 0.4), transparent 62%),
        radial-gradient(ellipse 48% 38% at 88% 8%, rgba(24, 8, 92, 0.55), transparent 58%),
        radial-gradient(ellipse 45% 32% at 72% 78%, rgba(16, 145, 33, 0.1), transparent 62%),
        linear-gradient(180deg, #0d0720 0%, var(--bg) 58%, #050309 100%);
    }

    .grid-layer {
      position: absolute;
      inset: -50% -10%;
      background-image:
        linear-gradient(rgba(196, 59, 192, 0.07) 1px, transparent 1px),
        linear-gradient(90deg, rgba(196, 59, 192, 0.07) 1px, transparent 1px);
      background-size: 64px 64px;
      transform: perspective(600px) rotateX(58deg);
      transform-origin: 50% 0%;
      opacity: 0.5;
      animation: gridPan 22s linear infinite;
    }

    @keyframes gridPan {
      to { background-position: 0 512px, 512px 0; }
    }

    .beam {
      position: absolute;
      top: -30%;
      left: 50%;
      width: 60vw;
      height: 160%;
      background: linear-gradient(180deg, rgba(196, 59, 192, 0.14), transparent 70%);
      filter: blur(40px);
      transform: translateX(-50%) rotate(8deg);
      animation: beamSway 14s ease-in-out infinite;
    }

    @keyframes beamSway {
      0%, 100% { transform: translateX(-58%) rotate(6deg); opacity: 0.55; }
      50% { transform: translateX(-42%) rotate(-6deg); opacity: 0.9; }
    }

    .orb {
      position: absolute;
      border-radius: 50%;
      filter: blur(70px);
      opacity: 0.5;
      animation: orbFloat 15s ease-in-out infinite;
    }

    .orb-a {
      width: 420px; height: 420px;
      top: -90px; left: -110px;
      background: radial-gradient(circle, rgba(196, 59, 192, 0.6), transparent 70%);
    }

    .orb-b {
      width: 540px; height: 540px;
      top: 24%; right: -180px;
      background: radial-gradient(circle, rgba(90, 10, 88, 0.6), transparent 70%);
      animation-delay: -5s;
      animation-duration: 19s;
    }

    .orb-c {
      width: 360px; height: 360px;
      bottom: 6%; left: 32%;
      background: radial-gradient(circle, rgba(16, 145, 33, 0.2), transparent 70%);
      animation-delay: -9s;
      animation-duration: 17s;
    }

    @keyframes orbFloat {
      0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
      50% { transform: translate3d(34px, 46px, 0) scale(1.09); }
    }

    .page { position: relative; z-index: 1; }

    /* ── Header ── */
    .topbar {
      position: sticky;
      top: 0;
      z-index: 50;
      backdrop-filter: blur(22px) saturate(140%);
      -webkit-backdrop-filter: blur(22px) saturate(140%);
      background: rgba(7, 4, 15, 0.78);
      border-bottom: 1px solid rgba(133, 15, 131, 0.3);
    }

    .topbar::after {
      content: "";
      position: absolute;
      left: 0; right: 0; bottom: -1px;
      height: 1px;
      background: linear-gradient(90deg, transparent, var(--p-lite), var(--gold), var(--p-lite), transparent);
      background-size: 200% 100%;
      opacity: 0.7;
      animation: lineFlow 6s linear infinite;
    }

    @keyframes lineFlow {
      to { background-position: 200% 0; }
    }

    .topbar-inner {
      max-width: var(--max);
      margin: 0 auto;
      padding: 0.8rem 1.25rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 0.7rem;
    }

    .brand {
      position: relative;
      display: block;
      flex-shrink: 1;
      min-width: 0;
      width: 150px;
      max-width: 42vw;
      transition: transform 0.45s var(--ease), filter 0.45s var(--ease);
    }

    .brand:hover {
      transform: translateY(-1px) scale(1.02);
      filter: drop-shadow(0 4px 18px rgba(196, 59, 192, 0.5));
    }

    .brand amp-img { display: block; width: 100%; }
    .brand amp-img img { object-fit: contain; object-position: left center; }

    .nav-cta { display: flex; gap: 0.45rem; flex-shrink: 0; }

    /* ── Buttons ── */
    .btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      padding: 0.6rem 0.9rem;
      border-radius: 4px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      white-space: nowrap;
      border: 1px solid transparent;
      overflow: hidden;
      transition: transform 0.4s var(--ease), box-shadow 0.4s var(--ease), background 0.4s var(--ease), border-color 0.4s var(--ease);
    }

    .btn::after {
      content: "";
      position: absolute;
      top: 0; bottom: 0;
      width: 45%;
      background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.28), transparent);
      transform: translateX(-260%) skewX(-18deg);
      animation: sheen 4s ease-in-out infinite;
    }

    @keyframes sheen {
      0%, 62% { transform: translateX(-260%) skewX(-18deg); }
      100% { transform: translateX(320%) skewX(-18deg); }
    }

    .btn:hover { transform: translateY(-2px); }

    .btn-login {
      background: rgba(133, 15, 131, 0.14);
      border-color: rgba(196, 59, 192, 0.6);
      color: #fff;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.04);
    }

    .btn-login:hover {
      background: rgba(133, 15, 131, 0.3);
      border-color: var(--p-lite);
      box-shadow: 0 8px 26px rgba(196, 59, 192, 0.35);
    }

    .btn-register {
      background: linear-gradient(135deg, #a815a6 0%, #850f83 48%, #59095a 100%);
      border-color: rgba(255, 255, 255, 0.14);
      color: #fff;
      box-shadow: 0 8px 26px rgba(133, 15, 131, 0.4);
    }

    .btn-register:hover { box-shadow: 0 14px 38px rgba(196, 59, 192, 0.5); }

    .btn-green {
      background: linear-gradient(135deg, #1ad134 0%, #109121 55%, #0a6414 100%);
      border-color: rgba(255, 255, 255, 0.12);
      color: #fff;
      box-shadow: 0 8px 26px rgba(16, 145, 33, 0.38);
    }

    .btn-green:hover { box-shadow: 0 14px 42px rgba(26, 209, 52, 0.45); }

    .btn-lg {
      padding: 0.88rem 1.25rem;
      font-size: 0.8rem;
      letter-spacing: 0.08em;
      flex: 1 1 auto;
      min-width: 0;
      max-width: 100%;
    }

    .btn svg { width: 15px; height: 15px; flex-shrink: 0; }

    /* ── Hero ── */
    .hero {
      position: relative;
      min-height: calc(100vh - 64px);
      display: flex;
      align-items: center;
      padding: 2.75rem 1.25rem 3.5rem;
    }

    .hero-grid {
      width: 100%;
      max-width: var(--max);
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 2.75rem;
      align-items: center;
    }

    .hero-copy { position: relative; z-index: 2; }

    .crumb {
      margin: 0 0 1.1rem;
      font-size: 0.74rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--muted);
      animation: fadeUp 0.7s var(--ease) both;
      list-style: none;
      padding: 0;
      display: flex;
      flex-wrap: wrap;
      gap: 0.35rem;
      align-items: center;
    }

    .crumb li { display: inline-flex; align-items: center; gap: 0.35rem; }
    .crumb a { color: var(--lilac); transition: color 0.3s var(--ease); }
    .crumb a:hover { color: var(--gold); }
    .crumb [aria-current="page"] { color: var(--p-lite); }

    .hero-kicker {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      margin: 0 0 1.2rem;
      padding: 0.46rem 0.95rem;
      border-radius: 4px;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--lilac);
      background: linear-gradient(120deg, rgba(133, 15, 131, 0.3), rgba(133, 15, 131, 0.1));
      border: 1px solid rgba(196, 59, 192, 0.4);
      animation: fadeUp 0.8s 0.05s var(--ease) both;
    }

    .hero-kicker .spark {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: var(--gold);
      box-shadow: 0 0 10px var(--gold);
      animation: pulseGold 1.8s ease-out infinite;
    }

    @keyframes pulseGold {
      0% { box-shadow: 0 0 0 0 rgba(233, 196, 10, 0.6); }
      70% { box-shadow: 0 0 0 9px rgba(233, 196, 10, 0); }
      100% { box-shadow: 0 0 0 0 rgba(233, 196, 10, 0); }
    }

    .hero h1 {
      margin: 0 0 1.1rem;
      font-size: clamp(1.95rem, 8.4vw, 4.35rem);
      overflow-wrap: break-word;
      line-height: 1.06;
      font-weight: 800;
      letter-spacing: -0.035em;
      animation: fadeUp 0.9s 0.12s var(--ease) both;
    }

    .hero h1 .grad {
      display: inline-block;
      background: linear-gradient(102deg, #fff 8%, #d8ccef 28%, #fff 44%, #e0c8ff 62%, #fff 84%);
      background-size: 240% auto;
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      animation: textShine 6s linear infinite;
    }

    @keyframes textShine {
      to { background-position: 240% center; }
    }

    .hero h1 .line2 {
      display: block;
      font-weight: 300;
      color: var(--lilac);
      font-size: 0.52em;
      letter-spacing: 0.02em;
      margin-top: 0.35rem;
    }

    .hero-lead {
      margin: 0 0 2.1rem;
      max-width: 44ch;
      font-size: 1.08rem;
      font-weight: 400;
      color: var(--muted);
      animation: fadeUp 0.9s 0.2s var(--ease) both;
    }

    .hero-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      margin-bottom: 1.85rem;
      animation: fadeUp 0.9s 0.28s var(--ease) both;
    }

    .trust-row {
      display: flex;
      flex-wrap: wrap;
      gap: 0.6rem;
      animation: fadeUp 0.9s 0.36s var(--ease) both;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 0.45rem;
      padding: 0.5rem 0.85rem;
      border-radius: 4px;
      font-size: 0.79rem;
      font-weight: 500;
      color: var(--lilac);
      background: rgba(255, 255, 255, 0.035);
      border: 1px solid var(--hair);
      transition: border-color 0.35s var(--ease), background 0.35s var(--ease);
    }

    .chip:hover {
      border-color: rgba(196, 59, 192, 0.5);
      background: rgba(196, 59, 192, 0.1);
    }

    .chip svg { width: 15px; height: 15px; color: var(--g-lite); }

    .live-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--g-lite);
      animation: pulseGreen 1.9s ease-out infinite;
    }

    @keyframes pulseGreen {
      0% { box-shadow: 0 0 0 0 rgba(26, 209, 52, 0.6); }
      70% { box-shadow: 0 0 0 11px rgba(26, 209, 52, 0); }
      100% { box-shadow: 0 0 0 0 rgba(26, 209, 52, 0); }
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(24px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ── Hero card ── */
    .hero-visual {
      position: relative;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 290px;
      animation: fadeUp 1s 0.22s var(--ease) both;
    }

    .hero-card {
      position: relative;
      width: 100%;
      max-width: 470px;
      padding: 2.1rem 1.85rem 1.85rem;
      border-radius: 6px;
      background:
        linear-gradient(158deg, rgba(46, 20, 78, 0.8), rgba(11, 7, 26, 0.94));
      border: 1px solid rgba(196, 59, 192, 0.34);
      box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.07),
        0 34px 90px rgba(0, 0, 0, 0.6),
        0 0 70px rgba(133, 15, 131, 0.2);
      overflow: hidden;
    }

    .hero-card::before {
      content: "";
      position: absolute;
      inset: -60% -30% auto;
      height: 220px;
      background: linear-gradient(90deg, transparent, rgba(233, 196, 10, 0.12), rgba(196, 59, 192, 0.3), transparent);
      transform: rotate(-10deg);
      animation: sweep 7s ease-in-out infinite;
    }

    @keyframes sweep {
      0%, 100% { opacity: 0.3; transform: translateX(-10%) rotate(-10deg); }
      50% { opacity: 0.85; transform: translateX(10%) rotate(-10deg); }
    }

    .card-top {
      position: relative;
      z-index: 1;
      display: flex;
      align-items: center;
      gap: 0.85rem;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--hair);
    }

    .card-tag {
      margin-right: auto;
      font-size: 0.66rem;
      font-weight: 700;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--muted);
    }

    .card-live {
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--g-lite);
    }

    .hero-card-logo {
      position: relative;
      z-index: 1;
      margin: 0 auto 1.4rem;
      max-width: 330px;
      filter: drop-shadow(0 14px 34px rgba(196, 59, 192, 0.3));
      animation: logoFloat 5s ease-in-out infinite;
    }

    @keyframes logoFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-9px); }
    }

    .hero-card-logo amp-img { display: block; margin: 0 auto; }
    .hero-card-logo amp-img img { object-fit: contain; }

    .card-domain {
      position: relative;
      z-index: 1;
      margin: 0 0 1.4rem;
      padding: 0.85rem 1rem;
      border-radius: 4px;
      text-align: center;
      font-size: 1.02rem;
      font-weight: 700;
      letter-spacing: 0.01em;
      color: var(--gold);
      background: rgba(0, 0, 0, 0.32);
      border: 1px dashed rgba(233, 196, 10, 0.38);
      word-break: break-all;
    }

    .card-badge {
      flex-shrink: 0;
      width: 44px;
      height: 44px;
      padding: 8px;
      border-radius: 4px;
      background: rgba(6, 3, 14, 0.6);
      border: 1px solid rgba(196, 59, 192, 0.45);
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06), 0 0 22px rgba(133, 15, 131, 0.35);
      animation: crownBob 6s ease-in-out infinite;
    }

    .card-badge amp-img { display: block; width: 100%; }
    .card-badge amp-img img { object-fit: contain; }

    @keyframes crownBob {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-4px); }
    }

    .stat-row {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 0.7rem;
    }

    .stat {
      text-align: center;
      padding: 0.9rem 0.4rem;
      border-radius: 4px;
      background: rgba(255, 255, 255, 0.035);
      border: 1px solid var(--hair);
      border-top: 2px solid rgba(233, 196, 10, 0.5);
      transition: transform 0.4s var(--ease), border-top-color 0.4s var(--ease);
    }

    .stat:hover {
      transform: translateY(-3px);
      border-top-color: var(--gold);
    }

    .stat strong {
      display: block;
      font-size: 1.02rem;
      font-weight: 800;
      color: var(--gold);
      margin-bottom: 0.1rem;
    }

    .stat span {
      font-size: 0.66rem;
      font-weight: 500;
      letter-spacing: 0.09em;
      text-transform: uppercase;
      color: var(--muted);
    }

    /* ── Ticker ── */
    .ticker {
      position: relative;
      border-top: 1px solid var(--hair);
      border-bottom: 1px solid var(--hair);
      background: rgba(11, 6, 24, 0.6);
      overflow: hidden;
      padding: 0.85rem 0;
    }

    .ticker-track {
      display: flex;
      width: max-content;
      animation: marquee 26s linear infinite;
    }

    .ticker-track span {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0 1.6rem;
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--muted);
      white-space: nowrap;
    }

    .ticker-track span::after {
      content: "";
      width: 5px; height: 5px;
      border-radius: 50%;
      background: var(--p-lite);
    }

    @keyframes marquee {
      to { transform: translateX(-50%); }
    }

    /* ── Security panels ── */
    .security-banner {
      max-width: var(--max);
      margin: 0 auto;
      padding: 2.5rem 1.25rem 0;
    }

    .security-alert {
      display: flex;
      gap: 0.85rem;
      align-items: flex-start;
      padding: 1.15rem 1.15rem;
      border-radius: 6px;
      background: rgba(16, 145, 33, 0.1);
      border: 1px solid rgba(26, 209, 52, 0.35);
      border-left: 3px solid var(--g-lite);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
    }

    .security-alert.warn {
      background: rgba(233, 196, 10, 0.08);
      border-color: rgba(233, 196, 10, 0.4);
      border-left-color: var(--gold);
    }

    .security-alert-body { min-width: 0; }

    .security-alert-ico {
      flex-shrink: 0;
      width: 42px;
      height: 42px;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--g-lite);
      background: rgba(16, 145, 33, 0.18);
      border: 1px solid rgba(26, 209, 52, 0.35);
    }

    .security-alert.warn .security-alert-ico {
      color: var(--gold);
      background: rgba(233, 196, 10, 0.14);
      border-color: rgba(233, 196, 10, 0.4);
    }

    .security-alert-ico svg { width: 22px; height: 22px; }

    .security-alert-title {
      display: block;
      margin: 0 0 0.4rem;
      font-size: 0.95rem;
      font-weight: 700;
      letter-spacing: -0.01em;
      color: var(--text);
    }

    .security-alert p {
      margin: 0;
      font-size: 0.92rem;
      color: var(--muted);
      line-height: 1.65;
    }

    .security-alert p strong,
    .hero-lead strong {
      color: var(--gold);
      font-weight: 700;
      overflow-wrap: anywhere;
    }

    .security-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.9rem;
      align-items: stretch;
    }

    .security-item {
      display: flex;
      gap: 1rem;
      align-items: flex-start;
      padding: 1.35rem 1.4rem;
      border-radius: 6px;
      background: rgba(17, 9, 32, 0.75);
      border: 1px solid var(--hair);
      border-left: 2px solid var(--g);
      transition: transform 0.4s var(--ease), border-left-color 0.4s var(--ease);
    }

    .security-item:hover {
      transform: translateY(-3px);
      border-left-color: var(--gold);
    }

    .security-check {
      flex-shrink: 0;
      width: 1.6rem;
      height: 1.6rem;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, rgba(26, 209, 52, 0.35), rgba(16, 145, 33, 0.55));
      color: #fff;
      margin-top: 0.15rem;
    }

    .security-check svg { width: 14px; height: 14px; }

    .security-item h3 {
      margin: 0 0 0.35rem;
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: -0.01em;
    }

    .security-item p {
      margin: 0;
      font-size: 0.91rem;
      line-height: 1.6;
      color: var(--muted);
    }

    .security-item code,
    .verify-box code {
      padding: 0.12rem 0.4rem;
      border-radius: 3px;
      color: var(--gold);
      background: rgba(233, 196, 10, 0.1);
      border: 1px solid rgba(233, 196, 10, 0.28);
      font-family: Consolas, "Courier New", monospace;
      font-size: 0.88em;
      overflow-wrap: anywhere;
    }

    .verify-box {
      margin-top: 1.5rem;
      padding: 1.25rem 1.35rem;
      border-radius: 6px;
      background: rgba(0, 0, 0, 0.28);
      border: 1px dashed rgba(196, 59, 192, 0.4);
      font-size: 0.9rem;
      line-height: 1.65;
      color: var(--muted);
    }

    .verify-box code {
      display: inline-block;
      margin-top: 0.55rem;
      padding: 0.4rem 0.7rem;
      font-size: 0.92rem;
      letter-spacing: 0.02em;
    }

    /* ── Sections ── */
    .section {
      max-width: var(--max);
      margin: 0 auto;
      padding: 4rem 1.25rem;
    }

    .section-head {
      margin-bottom: 2.15rem;
      max-width: 660px;
    }

    .eyebrow {
      display: inline-flex;
      align-items: center;
      gap: 0.55rem;
      margin: 0 0 0.75rem;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--p-lite);
    }

    .eyebrow::before {
      content: "";
      width: 26px;
      height: 1px;
      background: linear-gradient(90deg, var(--p-lite), transparent);
    }

    .section-head h2 {
      margin: 0 0 0.75rem;
      font-size: clamp(1.75rem, 3.3vw, 2.45rem);
      font-weight: 800;
      letter-spacing: -0.03em;
      line-height: 1.15;
    }

    .section-head p {
      margin: 0;
      color: var(--muted);
      font-size: 1.03rem;
    }

    /* ── Address ── */
    .address-block {
      position: relative;
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 1.35rem;
      padding: 1.75rem 1.85rem;
      border-radius: 6px;
      background: var(--surface);
      border: 1px solid transparent;
      background-clip: padding-box;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.4);
      overflow: hidden;
    }

    .address-block::before {
      content: "";
      position: absolute;
      inset: 0;
      padding: 1px;
      border-radius: 6px;
      background: linear-gradient(120deg, var(--p-deep), var(--p-lite), var(--gold), var(--p-lite), var(--p-deep));
      background-size: 300% 300%;
      animation: borderFlow 6s linear infinite;
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor;
      mask-composite: exclude;
      pointer-events: none;
    }

    @keyframes borderFlow {
      to { background-position: 300% 0; }
    }

    .address-label {
      margin: 0 0 0.4rem;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--lilac);
    }

    .address-url {
      margin: 0;
      font-size: clamp(1.25rem, 2.6vw, 1.72rem);
      font-weight: 800;
      letter-spacing: -0.02em;
      color: var(--gold);
      text-shadow: 0 0 28px rgba(233, 196, 10, 0.3);
      word-break: break-all;
    }

    /* ── Features ── */
    .features {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.2rem;
    }

    .feature {
      position: relative;
      padding: 1.85rem 1.55rem;
      border-radius: 6px;
      background: linear-gradient(168deg, var(--surface-2), rgba(9, 6, 20, 0.95));
      border: 1px solid var(--hair-2);
      border-top: 2px solid rgba(133, 15, 131, 0.8);
      transition: transform 0.45s var(--ease), border-top-color 0.45s var(--ease), box-shadow 0.45s var(--ease);
      overflow: hidden;
    }

    .feature::after {
      content: "";
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 20% 0%, rgba(196, 59, 192, 0.14), transparent 60%);
      opacity: 0;
      transition: opacity 0.45s var(--ease);
      pointer-events: none;
    }

    .feature:hover {
      transform: translateY(-6px);
      border-top-color: var(--gold);
      box-shadow: 0 22px 48px rgba(0, 0, 0, 0.45);
    }

    .feature:hover::after { opacity: 1; }

    .feature-ico {
      position: relative;
      z-index: 1;
      width: 46px;
      height: 46px;
      margin-bottom: 1.2rem;
      border-radius: 4px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--lilac);
      background: linear-gradient(135deg, rgba(133, 15, 131, 0.45), rgba(16, 145, 33, 0.16));
      border: 1px solid rgba(255, 255, 255, 0.1);
      transition: transform 0.45s var(--ease), color 0.45s var(--ease);
    }

    .feature:hover .feature-ico {
      transform: translateY(-2px) scale(1.06);
      color: var(--gold);
    }

    .feature-ico svg { width: 22px; height: 22px; display: block; }

    .feature h3 {
      position: relative;
      z-index: 1;
      margin: 0 0 0.55rem;
      font-size: 1.1rem;
      font-weight: 700;
      letter-spacing: -0.015em;
    }

    .feature p {
      position: relative;
      z-index: 1;
      margin: 0;
      font-size: 0.94rem;
      color: var(--muted);
    }

    /* ── Steps ── */
    .steps {
      display: grid;
      grid-template-columns: 1fr;
      gap: 1.05rem;
      counter-reset: step;
      list-style: none;
      margin: 0;
      padding: 0;
    }

    .step {
      position: relative;
      display: flex;
      gap: 1.15rem;
      align-items: flex-start;
      padding: 1.6rem 1.5rem;
      border-radius: 6px;
      background: rgba(17, 9, 32, 0.72);
      border: 1px solid var(--hair);
      border-left: 2px solid rgba(196, 59, 192, 0.6);
      transition: transform 0.45s var(--ease), border-left-color 0.45s var(--ease), background 0.45s var(--ease);
    }

    .step:hover {
      transform: translateY(-5px);
      border-left-color: var(--gold);
      background: rgba(28, 15, 50, 0.8);
    }

    .step::before {
      counter-increment: step;
      content: "0" counter(step);
      flex-shrink: 0;
      width: 2.7rem;
      height: 2.7rem;
      border-radius: 4px;
      background: linear-gradient(135deg, var(--p-lite), var(--p));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.88rem;
      font-weight: 800;
      letter-spacing: 0.03em;
      box-shadow: 0 0 22px rgba(196, 59, 192, 0.36);
    }

    .step h3 {
      margin: 0 0 0.4rem;
      font-size: 1.04rem;
      font-weight: 700;
      letter-spacing: -0.015em;
    }

    .step p { margin: 0; font-size: 0.92rem; color: var(--muted); }

    /* ── FAQ ── */
    .faq details {
      margin-bottom: 0.75rem;
      padding: 1.15rem 1.3rem;
      background: rgba(17, 9, 32, 0.72);
      border: 1px solid var(--hair-2);
      border-radius: 6px;
      transition: border-color 0.35s var(--ease), background 0.35s var(--ease);
    }

    .faq details:hover { border-color: rgba(196, 59, 192, 0.45); }

    .faq details[open] {
      border-color: rgba(196, 59, 192, 0.55);
      background: rgba(28, 15, 50, 0.85);
    }

    .faq summary {
      cursor: pointer;
      font-weight: 600;
      font-size: 1rem;
      letter-spacing: -0.01em;
      list-style: none;
      padding-right: 1.75rem;
      position: relative;
    }

    .faq summary::-webkit-details-marker { display: none; }

    .faq summary::after {
      content: "+";
      position: absolute;
      right: 0;
      top: 50%;
      transform: translateY(-50%);
      color: var(--gold);
      font-weight: 400;
      font-size: 1.4rem;
      line-height: 1;
      transition: transform 0.35s var(--ease);
    }

    .faq details[open] summary::after {
      content: "−";
      transform: translateY(-50%) rotate(180deg);
    }

    .faq details p {
      margin: 0.9rem 0 0;
      font-size: 0.94rem;
      color: var(--muted);
      animation: fadeUp 0.45s var(--ease) both;
    }

    /* ── CTA band ── */
    .cta-band {
      position: relative;
      width: calc(100% - 2.5rem);
      max-width: calc(var(--max) - 2.5rem);
      margin: 1rem auto 0;
      padding: 3rem 1.25rem;
      text-align: center;
      border-radius: 6px;
      background:
        radial-gradient(ellipse at 50% 0%, rgba(196, 59, 192, 0.32), transparent 58%),
        linear-gradient(135deg, #2c0a44 0%, #150a52 48%, #100120 100%);
      border: 1px solid rgba(196, 59, 192, 0.38);
      box-shadow: 0 34px 90px rgba(0, 0, 0, 0.5);
      overflow: hidden;
    }

    .cta-band::after {
      content: "";
      position: absolute;
      width: 220%;
      height: 220%;
      top: -60%;
      left: -60%;
      background: conic-gradient(from 0deg, transparent, rgba(233, 196, 10, 0.1), transparent 32%);
      animation: spinSlow 18s linear infinite;
      pointer-events: none;
    }

    @keyframes spinSlow { to { transform: rotate(360deg); } }

    .cta-inner { position: relative; z-index: 1; }

    .cta-band h2 {
      margin: 0 0 0.6rem;
      font-size: clamp(1.65rem, 3.1vw, 2.3rem);
      font-weight: 800;
      letter-spacing: -0.03em;
    }

    .cta-band p {
      margin: 0 0 1.75rem;
      color: var(--lilac);
      font-size: 1.03rem;
    }

    .cta-band .hero-actions {
      justify-content: center;
      margin-bottom: 0;
      animation: none;
    }

    /* ── Footer ── */
    .site-footer {
      padding: 3rem 1.25rem 2.5rem;
      text-align: center;
      color: var(--muted);
      font-size: 0.84rem;
    }

    .footer-logo {
      display: block;
      width: 190px;
      max-width: 60vw;
      margin: 0 auto 1.5rem;
      opacity: 0.75;
      transition: opacity 0.4s var(--ease);
    }

    .footer-logo:hover { opacity: 1; }
    .footer-logo amp-img { display: block; width: 100%; }
    .footer-logo amp-img img { object-fit: contain; }

    .site-footer nav {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 0.85rem 1.6rem;
      margin-bottom: 1.5rem;
    }

    .site-footer nav a {
      position: relative;
      color: var(--lilac);
      opacity: 0.85;
      padding-bottom: 2px;
      transition: opacity 0.3s var(--ease), color 0.3s var(--ease);
    }

    .site-footer nav a::after {
      content: "";
      position: absolute;
      left: 0; right: 100%;
      bottom: 0;
      height: 1px;
      background: var(--gold);
      transition: right 0.35s var(--ease);
    }

    .site-footer nav a:hover { opacity: 1; color: var(--gold); }
    .site-footer nav a:hover::after { right: 0; }

    .age-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      margin-bottom: 1rem;
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.22);
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--lilac);
    }

    .visually-hidden {
      position: absolute;
      width: 1px; height: 1px;
      padding: 0; margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    /* ── Desktop ── */
    @media (min-width: 600px) {
      .topbar-inner { padding: 0.9rem 1.5rem; gap: 1rem; }
      .brand { width: 200px; max-width: 40vw; }
      .nav-cta { gap: 0.6rem; }

      .hero { min-height: calc(100vh - 76px); padding: 3.25rem 1.5rem 4rem; }
      .section { padding: 4.5rem 1.5rem; }
      .security-banner { padding: 2.75rem 1.5rem 0; }
      .site-footer { padding: 3.25rem 1.5rem 2.75rem; }

      .cta-band {
        width: calc(100% - 3rem);
        max-width: calc(var(--max) - 3rem);
        padding: 3.75rem 1.5rem;
      }

      .security-alert { gap: 1.1rem; padding: 1.35rem 1.5rem; }
      .security-alert p strong,
      .hero-lead strong,
      .security-item code,
      .verify-box code { white-space: nowrap; }

      .btn {
        padding: 0.74rem 1.3rem;
        font-size: 0.76rem;
        letter-spacing: 0.1em;
      }

      .btn-lg {
        flex: 0 0 auto;
        padding: 1rem 2rem;
        font-size: 0.88rem;
        letter-spacing: 0.11em;
        min-width: 176px;
      }
    }

    @media (min-width: 900px) {
      .topbar-inner { padding: 1.05rem 2rem; }
      .brand { width: 240px; max-width: 240px; }
      .btn { padding: 0.74rem 1.4rem; font-size: 0.78rem; }
      .btn-lg { padding: 1.02rem 2.1rem; font-size: 0.9rem; }

      .hero {
        padding: 4.75rem 2rem 5.5rem;
        min-height: calc(100vh - 89px);
      }

      .hero-grid {
        grid-template-columns: 1.12fr 0.92fr;
        gap: 4.25rem;
      }

      .hero h1 { max-width: 12ch; }
      .hero-visual { min-height: 440px; }

      .hero-card {
        padding: 2.5rem 2.2rem 2.1rem;
        max-width: 505px;
      }

      .features { grid-template-columns: repeat(3, 1fr); gap: 1.4rem; }
      .security-grid { grid-template-columns: repeat(2, 1fr); gap: 1.2rem; }
      .steps { grid-template-columns: repeat(3, 1fr); gap: 1.3rem; }
      .step { flex-direction: column; }
      .section { padding: 5.75rem 2rem; }
      .section-head { margin-bottom: 2.6rem; }
      .address-block { padding: 2.1rem 2.35rem; }
      .security-banner { padding: 3.5rem 2rem 0; }
      .security-alert { padding: 1.5rem 1.75rem; }
      .security-item { padding: 1.5rem 1.6rem; }

      .cta-band {
        width: calc(100% - 4rem);
        max-width: calc(var(--max) - 4rem);
        margin-top: 2rem;
        padding: 4.5rem 2rem;
      }
    }

    @media (min-width: 1200px) {
      .hero-grid { gap: 5.25rem; }
      .hero h1 { font-size: 4.5rem; }
    }

    @media (prefers-reduced-motion: reduce) {
      .orb, .beam, .grid-layer, .btn::after, .hero-card-logo, .card-badge,
      .hero-card::before, .address-block::before, .cta-band::after,
      .hero h1 .grad, .live-dot, .hero-kicker .spark, .topbar::after,
      .ticker-track, .crumb, .hero-kicker, .hero h1, .hero-lead,
      .hero-actions, .trust-row, .hero-visual, .faq details p {
        animation-name: none;
        animation-duration: 0s;
      }
    }
  </style>
</head>
<body>
  <div class="ambient" aria-hidden="true">
    <div class="grid-layer"></div>
    <div class="beam"></div>
    <div class="orb orb-a"></div>
    <div class="orb orb-b"></div>
    <div class="orb orb-c"></div>
  </div>

  <div class="page">
    <header class="topbar">
      <div class="topbar-inner">
        <a class="brand" href="<?= $e($baseUrl . '/') ?>" aria-label="<?= $e($siteName . ' ana sayfa') ?>">
          <amp-img
            src="<?= $e($logoUrl) ?>"
            width="240"
            height="54"
            alt="<?= $e($siteName) ?>"
            layout="responsive"
          ></amp-img>
        </a>
        <nav class="nav-cta" aria-label="Hızlı işlemler">
          <a class="btn btn-login" href="<?= $e($loginUrl) ?>">Giriş</a>
          <a class="btn btn-register" href="<?= $e($registerUrl) ?>">Kayıt</a>
        </nav>
      </div>
    </header>

    <main>
      <section class="hero" aria-labelledby="hero-title">
        <div class="hero-grid">
          <div class="hero-copy">
            <nav aria-label="Breadcrumb">
              <ol class="crumb">
                <li><a href="<?= $e($baseUrl . '/') ?>"><?= $e($siteName) ?></a><span aria-hidden="true">/</span></li>
                <li><span aria-current="page">Güncel Giriş</span></li>
              </ol>
            </nav>
            <p class="hero-kicker"><span class="spark" aria-hidden="true"></span> SSL · Resmi adres · Sahte site koruması</p>
            <h1 id="hero-title">
              <span class="grad"><?= $e($siteName) ?></span>
              <span class="line2">Güvenli Güncel Giriş</span>
            </h1>
            <p class="hero-lead">
              Hesabınızı koruyun: yalnızca resmi <strong><?= $e($hostLabel) ?></strong> adresinden giriş yapın.
              Bu sayfa sizi SSL korumalı platforma bağlar; sahte sitelere karşı doğrulanmış güncel giriş rehberidir.
            </p>
            <div class="hero-actions">
              <a class="btn btn-green btn-lg" href="<?= $e($loginUrl) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"></path>
                  <path d="M9.5 12.2l1.9 1.9 3.6-3.6"></path>
                </svg>
                Güvenli Giriş
              </a>
              <a class="btn btn-register btn-lg" href="<?= $e($registerUrl) ?>">Güvenli Kayıt</a>
            </div>
            <div class="trust-row">
              <span class="chip"><span class="live-dot" aria-hidden="true"></span> Doğrulanmış adres</span>
              <span class="chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"></path>
                  <path d="M9.5 12.2l1.9 1.9 3.6-3.6"></path>
                </svg>
                HTTPS / SSL
              </span>
              <span class="chip">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <rect x="4" y="10.5" width="16" height="10.5" rx="1"></rect>
                  <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path>
                </svg>
                Phishing koruması
              </span>
            </div>
          </div>

          <div class="hero-visual">
            <div class="hero-card">
              <div class="card-top">
                <span class="card-badge" aria-hidden="true">
                  <amp-img
                    src="<?= $e($faviconUrl) ?>"
                    width="42"
                    height="28"
                    alt="<?= $e($siteName . ' ikon') ?>"
                    layout="responsive"
                  ></amp-img>
                </span>
                <span class="card-tag">Güvenli platform</span>
                <span class="card-live"><span class="live-dot" aria-hidden="true"></span> SSL aktif</span>
              </div>
              <div class="hero-card-logo">
                <amp-img
                  src="<?= $e($logoUrl) ?>"
                  width="330"
                  height="82"
                  alt="<?= $e($siteName) ?>"
                  layout="responsive"
                ></amp-img>
              </div>
              <p class="card-domain">https://<?= $e($hostLabel) ?></p>
              <div class="stat-row">
                <div class="stat"><strong>SSL</strong><span>Şifreli</span></div>
                <div class="stat"><strong>Resmi</strong><span>Domain</span></div>
                <div class="stat"><strong>18+</strong><span>Güvenli</span></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <div class="ticker" aria-hidden="true">
        <div class="ticker-track">
          <span>SSL korumalı giriş</span><span>Resmi domain</span><span>Sahte site uyarısı</span>
          <span>HTTPS zorunlu</span><span>Şifre güvenliği</span><span>Doğrulanmış adres</span>
          <span>SSL korumalı giriş</span><span>Resmi domain</span><span>Sahte site uyarısı</span>
          <span>HTTPS zorunlu</span><span>Şifre güvenliği</span><span>Doğrulanmış adres</span>
        </div>
      </div>

      <div class="security-banner" amp-fx="fade-in-scroll" data-duration="600ms">
        <div class="security-alert" role="status">
          <div class="security-alert-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"></path>
              <path d="M9.5 12.2l1.9 1.9 3.6-3.6"></path>
            </svg>
          </div>
          <div class="security-alert-body">
            <strong class="security-alert-title">Güven doğrulaması</strong>
            <p>
              Yalnızca <strong>https://<?= $e($hostLabel) ?></strong> üzerinden giriş yapın.
              Adres çubuğunda kilit simgesi yoksa veya domain farklıysa işlemi durdurun — bu bir sahte site olabilir.
            </p>
          </div>
        </div>
      </div>

      <section class="section" aria-labelledby="address-title" amp-fx="fade-in-scroll" data-duration="700ms">
        <div class="section-head">
          <span class="eyebrow">Doğrulanmış adres</span>
          <h2 id="address-title"><?= $e($siteName) ?> güvenli güncel giriş</h2>
          <p>
            Resmi <?= $e($hostLabel) ?> adresi hesabınızı ve bakiyenizi korur.
            Bu linki yer imine ekleyin; kısa link ve SMS’teki şüpheli adreslere tıklamayın.
          </p>
        </div>
        <div class="address-block">
          <div>
            <p class="address-label">Resmi HTTPS domain</p>
            <p class="address-url"><?= $e($baseUrl) ?></p>
          </div>
          <a class="btn btn-green btn-lg" href="<?= $e($baseUrl . '/') ?>">Güvenli Siteye Git</a>
        </div>
        <div class="verify-box">
          Tarayıcı adres çubuğunda birebir şunu görmelisiniz:
          <br>
          <code>https://<?= $e($hostLabel) ?></code>
        </div>
      </section>

      <section class="section" aria-labelledby="security-title">
        <div class="section-head" amp-fx="fly-in-bottom" data-duration="600ms" data-margin-start="12%">
          <span class="eyebrow">Güven kontrol listesi</span>
          <h2 id="security-title">Giriş öncesi 4 güvenlik kontrolü</h2>
          <p>
            <?= $e($siteName) ?> güvenli girişi, hesabınızı phishing ve sahte sitelere karşı korumak için bu adımları izlemenizi önerir.
          </p>
        </div>
        <div class="security-grid">
          <article class="security-item" amp-fx="fly-in-bottom" data-duration="600ms" data-margin-start="8%">
            <div class="security-check" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
              <h3>Domain birebir aynı mı?</h3>
              <p>vegasroyalspin.com yazımını kontrol edin; ekstra harf / tire / .net benzeri uzantılara dikkat edin.</p>
            </div>
          </article>
          <article class="security-item" amp-fx="fly-in-bottom" data-duration="700ms" data-margin-start="8%">
            <div class="security-check" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
              <h3>HTTPS kilit var mı?</h3>
              <p>Adres <code>https://</code> ile başlamalı. HTTP veya “güvenli değil” uyarısı varsa giriş yapmayın.</p>
            </div>
          </article>
          <article class="security-item" amp-fx="fly-in-bottom" data-duration="800ms" data-margin-start="8%">
            <div class="security-check" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
              <h3>Şifre istenen mesaj mı?</h3>
              <p><?= $e($siteName) ?> şifrenizi SMS, Telegram veya e-posta ile asla sormaz. Yalnızca site formunu kullanın.</p>
            </div>
          </article>
          <article class="security-item" amp-fx="fly-in-bottom" data-duration="900ms" data-margin-start="8%">
            <div class="security-check" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"></path></svg>
            </div>
            <div>
              <h3>Bu sayfadan mı geliyorsunuz?</h3>
              <p>Güncel giriş linkini buradan kaydedin. Bilinmeyen kısaltılmış URL’lere tıklamayın.</p>
            </div>
          </article>
        </div>
      </section>

      <section class="section" aria-labelledby="why-title">
        <div class="section-head" amp-fx="fly-in-bottom" data-duration="600ms" data-margin-start="12%">
          <span class="eyebrow">Neden güvenli?</span>
          <h2 id="why-title"><?= $e($siteName) ?> güven odaklı giriş</h2>
          <p>
            Bu AMP sayfası yalnızca resmi siteye yönlendirir; hesap güvenliği, SSL ve sahte site farkındalığı önceliklidir.
          </p>
        </div>
        <div class="features">
          <article class="feature" amp-fx="fly-in-bottom" data-duration="600ms" data-margin-start="8%">
            <div class="feature-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <rect x="4" y="10.5" width="16" height="10.5" rx="1"></rect>
                <path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path>
                <path d="M12 15v2.5"></path>
              </svg>
            </div>
            <h3>SSL şifreli bağlantı</h3>
            <p>Giriş ve kayıt trafiği HTTPS ile korunur; verileriniz üçüncü parti sahte formlara gitmez.</p>
          </article>
          <article class="feature" amp-fx="fly-in-bottom" data-duration="750ms" data-margin-start="8%">
            <div class="feature-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z"></path>
                <path d="M9.5 12.2l1.9 1.9 3.6-3.6"></path>
              </svg>
            </div>
            <h3>Resmi domain doğrulama</h3>
            <p>Yalnızca <?= $e($hostLabel) ?> kabul edilir. Benzer yazımlı sahte sitelerden uzak durun.</p>
          </article>
          <article class="feature" amp-fx="fly-in-bottom" data-duration="900ms" data-margin-start="8%">
            <div class="feature-ico" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="8.5"></circle>
                <path d="M12 8v5"></path>
                <circle cx="12" cy="16" r="0.8" fill="currentColor"></circle>
              </svg>
            </div>
            <h3>Phishing / sahte site uyarısı</h3>
            <p>Şüpheli link, ödeme talebi veya şifre isteyen mesajlarda işlemi durdurun ve resmi adrese dönün.</p>
          </article>
        </div>
      </section>

      <section class="section" aria-labelledby="how-title">
        <div class="section-head" amp-fx="fly-in-bottom" data-duration="600ms" data-margin-start="12%">
          <span class="eyebrow">Güvenli 3 adım</span>
          <h2 id="how-title">Nasıl güvenli giriş yapılır?</h2>
          <p>Doğrulama → SSL kontrolü → yalnızca resmi form. Bu sırayı atlamayın.</p>
        </div>
        <ol class="steps">
          <li class="step" amp-fx="fly-in-bottom" data-duration="600ms" data-margin-start="8%">
            <div>
              <h3>Resmi adresi doğrulayın</h3>
              <p>Adres çubuğunda https://<?= $e($hostLabel) ?> göründüğünden emin olun.</p>
            </div>
          </li>
          <li class="step" amp-fx="fly-in-bottom" data-duration="750ms" data-margin-start="8%">
            <div>
              <h3>SSL kilit simgesini kontrol edin</h3>
              <p>Tarayıcıda kilit / güvenli bağlantı yoksa giriş bilgisi girmeyin.</p>
            </div>
          </li>
          <li class="step" amp-fx="fly-in-bottom" data-duration="900ms" data-margin-start="8%">
            <div>
              <h3>Hesabınıza güvenle girin</h3>
              <p>Şifrenizi yalnızca resmi <?= $e($siteName) ?> formuna yazın; kimseyle paylaşmayın.</p>
            </div>
          </li>
        </ol>
      </section>

      <section class="section" aria-labelledby="warn-title" amp-fx="fade-in-scroll" data-duration="700ms">
        <div class="security-alert warn" role="note">
          <div class="security-alert-ico" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <path d="M10.3 3.7 1.8 18.2A2 2 0 0 0 3.5 21h17a2 2 0 0 0 1.7-2.8L13.7 3.7a2 2 0 0 0-3.4 0Z"></path>
              <path d="M12 9v5"></path>
              <circle cx="12" cy="17" r="0.8" fill="currentColor"></circle>
            </svg>
          </div>
          <div class="security-alert-body">
            <strong class="security-alert-title" id="warn-title">Önemli güvenlik uyarısı</strong>
            <p>
              <?= $e($siteName) ?> asla e-posta, SMS veya sosyal medya üzerinden şifre, kart numarası veya OTP istemez.
              Böyle bir talep alırsanız mesajı yok sayın ve yalnızca <strong><?= $e($baseUrl) ?></strong> üzerinden işlem yapın.
            </p>
          </div>
        </div>
      </section>

      <section class="section faq" aria-labelledby="faq-title" amp-fx="fade-in-scroll" data-duration="800ms">
        <div class="section-head">
          <span class="eyebrow">Güven SSS</span>
          <h2 id="faq-title">Güvenlik soruları</h2>
          <p><?= $e($siteName) ?> güvenli güncel giriş hakkında en kritik sorular.</p>
        </div>

        <details>
          <summary><?= $e($siteName) ?> güvenli güncel giriş adresi nedir?</summary>
          <p>Resmi ve güvenli adres <strong>https://<?= $e($hostLabel) ?></strong> üzerindedir. Yalnızca HTTPS ile açılan resmi domaini kullanın.</p>
        </details>
        <details>
          <summary><?= $e($siteName) ?> giriş güvenli mi?</summary>
          <p>Evet. Resmi site SSL ile korunur. Bu sayfa sizi sahte / phishing sitelere değil, doğrulanmış <?= $e($hostLabel) ?> giriş ekranına yönlendirir.</p>
        </details>
        <details>
          <summary>Sahte <?= $e($siteName) ?> sitesini nasıl anlarım?</summary>
          <p>Domainde harf farkı, HTTP (kilit yok), şüpheli kısa linkler ve şifre isteyen mesajlar sahte site işaretidir. Yalnızca https://<?= $e($hostLabel) ?> kullanın.</p>
        </details>
        <details>
          <summary>Güncel giriş neden değişir?</summary>
          <p>Erişim kısıtlamaları veya önbellek sorunları resmi adrese ulaşmayı zorlaştırabilir. Bu güvenli güncel giriş sayfasını yer imine ekleyerek her zaman doğrulanmış <strong>https://<?= $e($hostLabel) ?></strong> adresine yönlendirilirsiniz.</p>
        </details>
        <details>
          <summary>Şifremi kimseyle paylaşmalı mıyım?</summary>
          <p>Hayır. <?= $e($siteName) ?> şifrenizi SMS, Telegram veya e-posta ile asla istemez. Giriş bilgilerinizi yalnızca resmi sitedeki forma yazın.</p>
        </details>
      </section>

      <section class="cta-band" aria-labelledby="cta-title" amp-fx="fade-in-scroll" data-duration="700ms">
        <div class="cta-inner">
          <h2 id="cta-title">Güvenli <?= $e($siteName) ?> girişine geçin</h2>
          <p>Resmi SSL adresiyle hesabınızı koruyarak giriş veya kayıt olun.</p>
          <div class="hero-actions">
            <a class="btn btn-green btn-lg" href="<?= $e($loginUrl) ?>">Güvenli Giriş</a>
            <a class="btn btn-register btn-lg" href="<?= $e($registerUrl) ?>">Güvenli Kayıt</a>
          </div>
        </div>
      </section>
    </main>

    <footer class="site-footer">
      <a class="footer-logo" href="<?= $e($baseUrl . '/') ?>" aria-label="<?= $e($siteName) ?>">
        <amp-img
          src="<?= $e($logoUrl) ?>"
          width="190"
          height="47"
          alt="<?= $e($siteName) ?>"
          layout="responsive"
        ></amp-img>
      </a>
      <nav aria-label="Alt bağlantılar">
        <a href="<?= $e($baseUrl . '/') ?>">Ana Sayfa</a>
        <a href="<?= $e($loginUrl) ?>">Giriş</a>
        <a href="<?= $e($registerUrl) ?>">Kayıt</a>
        <a href="<?= $e($baseUrl . '/gizlilik-politikasi') ?>">Gizlilik</a>
        <a href="<?= $e($baseUrl . '/genel-sartlar') ?>">Genel Şartlar</a>
      </nav>
      <div class="age-badge" aria-label="18 yaş sınırı">18+</div>
      <p>
        &copy; 2026 <?= $e($siteName) ?> — Güvenli giriş · SSL · Resmi adres.
        <span class="visually-hidden">Güven odaklı güncel giriş AMP sayfası.</span>
      </p>
    </footer>
  </div>
</body>
</html>
