<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (class_exists('ApiMediaUrl') && method_exists('ApiMediaUrl', 'ensureLoaded')) {
    ApiMediaUrl::ensureLoaded();
}

if (!function_exists('promotions_page_sections_from_api')) {
    /**
     * @param array<string, mixed> $p Ham promotions.php kaydı (api.md).
     * @return list<array{title: string, content: string}>
     */
    function promotions_page_sections_from_api(array $p): array
    {
        $out = [];
        $long = trim((string) ($p['long_description'] ?? ''));
        if ($long !== '') {
            $out[] = [
                'title'   => 'KAMPANYA DETAYI',
                'content' => '<p>' . nl2br(htmlspecialchars($long, ENT_QUOTES, 'UTF-8')) . '</p>',
            ];
        }
        $terms = trim((string) ($p['terms'] ?? ''));
        if ($terms !== '') {
            $out[] = [
                'title'   => 'BONUS ŞARTLARI',
                'content' => '<p>' . nl2br(htmlspecialchars($terms, ENT_QUOTES, 'UTF-8')) . '</p>',
            ];
        }
        $gen = trim((string) ($p['general_rules'] ?? ''));
        if ($gen !== '') {
            $out[] = [
                'title'   => 'GENEL KURALLAR',
                'content' => '<p>' . nl2br(htmlspecialchars($gen, ENT_QUOTES, 'UTF-8')) . '</p>',
            ];
        }
        if ($out === []) {
            $desc = trim((string) ($p['description'] ?? ''));
            if ($desc !== '') {
                $out[] = [
                    'title'   => 'ÖZET',
                    'content' => '<p>' . nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')) . '</p>',
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('promotions_page_normalize_image_url')) {
    function promotions_page_normalize_image_url(string $imageUrl): string
    {
        $imageUrl = trim($imageUrl);
        if ($imageUrl === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $imageUrl) === 1) {
            $host = strtolower((string) (parse_url($imageUrl, PHP_URL_HOST) ?? ''));
            if (preg_match('/^(?:icons|cms)\.casinomilyon\d+\.com$/i', $host) === 1) {
                return $imageUrl;
            }
        }

        if (class_exists('ApiMediaUrl', false) && method_exists('ApiMediaUrl', 'resolvePromotionImage')) {
            return (string) ApiMediaUrl::resolvePromotionImage($imageUrl);
        }

        if (preg_match('#^https?://#i', $imageUrl) === 1) {
            $path = (string) (parse_url($imageUrl, PHP_URL_PATH) ?? '');
            if ($path !== '') {
                $imageUrl = $path;
            }
        }

        $imageUrl = '/' . ltrim(str_replace('\\', '/', $imageUrl), '/');
        $lower = strtolower($imageUrl);
        if (str_starts_with($lower, '/storage/uploads/')) {
            return '/uploads/' . ltrim(substr($imageUrl, strlen('/storage/uploads/')), '/');
        }
        if (str_starts_with($lower, '/admin/uploads/')) {
            return '/uploads/' . ltrim(substr($imageUrl, strlen('/admin/uploads/')), '/');
        }

        return $imageUrl;
    }
}
?>
<?php require VIEW_PATH . '/layouts/head.php'; ?>
<?php include VIEW_PATH . '/partials/header.php'; ?>

<section class="mainWrap page-promotions ng-tns-c21-3 ng-star-inserted">
    <div class="centerWrap">
        <router-outlet class="ng-tns-c21-3"></router-outlet>
        <app-promotions-bonus class="ng-star-inserted">
            <app-g6-promotions-bonus class="ng-star-inserted">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12 ng-star-inserted">
                            <div class="row">
                                <div class="col-12 bonusRow ng-star-inserted">
                                    
<?php
// GET /api/v2/content/promotions — API katmanı üzerinden promosyon listesi
$memberJwt = (isset($_SESSION['member_jwt']) && is_string($_SESSION['member_jwt'])) ? $_SESSION['member_jwt'] : null;
$promoApi  = ApiPromotions::fetch([], $memberJwt);
$apiRows   = [];
foreach ($promoApi['promotions'] as $p) {
    if (!is_array($p)) {
        continue;
    }
    $slug = isset($p['category']) && is_string($p['category']) ? $p['category'] : 'slots';
    $apiRows[] = [
        'id'          => isset($p['id']) ? (int) $p['id'] : 0,
        'title'       => isset($p['title']) ? (string) $p['title'] : '',
        'description' => isset($p['description']) ? (string) $p['description'] : '',
        'image_url'   => promotions_page_normalize_image_url((string) ($p['image_url'] ?? '')),
        'link_url'    => isset($p['link_url']) ? (string) $p['link_url'] : '',
        'category_id' => $slug,
        'sections'    => promotions_page_sections_from_api($p),
    ];
}

// API boşsa assets/images/bonuses içindeki görsellerden demo bonuslar
$bonusesDir = __DIR__ . '/../assets/images/bonuses';
$demoBonuses = [];
$allowedExt = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg'];
if (is_dir($bonusesDir)) {
    $files = scandir($bonusesDir);
    $id = 1;
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) continue;
        $imagePath = 'assets/images/bonuses/' . $file;
        $baseName = pathinfo($file, PATHINFO_FILENAME);
        $title = ucfirst(str_replace(['-', '_'], ' ', $baseName));
        $demoBonuses[] = [
            'id' => $id++,
            'title' => $title,
            'description' => 'Bu kampanya hakkında detaylı bilgi için görsele tıklayın veya bonusu talep edin. Şartlar ve koşullar geçerlidir.',
            'image_url' => $imagePath,
            'detail_image_url' => $imagePath,
            'max_withdrawal' => 0,
            'terms' => '18 yaşından büyük olmalısınız. Kampanya şartları uygulanır.',
            'category_id' => 'slots',
        ];
    }
}
// Hiç görsel yoksa eski placeholder'lı demo (yedek)
if (empty($demoBonuses)) {
    $demoBonuses = [
        [
            'id' => 1,
            'title' => 'Hoş Geldin Bonusu',
            'description' => 'Yeni üyelerimize özel bonus fırsatları.',
            'image_url' => 'https://via.placeholder.com/400x300/856A00/ffffff?text=Bonus',
            'detail_image_url' => 'https://via.placeholder.com/600x400/856A00/ffffff?text=Bonus',
            'max_withdrawal' => 500.00,
            'terms' => 'Şartlar ve koşullar geçerlidir.',
            'category_id' => 'slots',
        ],
    ];
}

$rows = !empty($apiRows) ? $apiRows : $demoBonuses;

// Bonus detay modalı için accordion bölümleri (demo / eski kaynak)
$defaultSections = [
    ['title' => 'BONUSTAN NASIL FAYDALANABİLİRİM', 'content' => '<p>İlgili bonusu almak için hesabınıza giriş yapın, kampanya sayfasından bu bonusu seçin ve "Katıl" butonuna tıklayın.</p>'],
    ['title' => 'BONUS ÇEVRİM ŞARTI', 'content' => '<p>Bonusun çevrim şartı bonus tutarı ve yatırım miktarına göre belirlenir. Çevrim tamamlanmadan çekim yapılamaz.</p>'],
    ['title' => 'BONUS GENEL KURALLARI', 'content' => '<p>Bonus tek hesapta kullanılır. Suistimal tespitinde bonus iptal edilir. Site kurallarına uyulması zorunludur.</p>'],
];
foreach ($rows as $i => $b) {
    if (empty($b['sections']) || !is_array($b['sections'])) {
        $rows[$i]['sections'] = $defaultSections;
    }
}

// Kategori filtresi — api.md GET category: sports | live_casino | slots | loss_bonus | vip
$kategoriler = [
    ['id' => 'tumu', 'label' => 'TÜMÜ', 'icon' => 'bc-i-default-icon bc-i-all', 'active' => true],
    ['id' => 'sports', 'label' => 'SPOR', 'icon' => 'bc-i-prematch'],
    ['id' => 'live_casino', 'label' => 'CANLI CASINO', 'icon' => 'bc-i-livecasino'],
    ['id' => 'slots', 'label' => 'SLOT', 'icon' => 'bc-i-slots-v1'],
    ['id' => 'loss_bonus', 'label' => 'KAYIP BONUSU', 'icon' => 'bc-i-circle-dollar'],
    ['id' => 'vip', 'label' => 'VIP', 'icon' => 'bc-i-star'],
];
?>
<div class="promo-categories-scroll-wrap" data-promo-cats-wrap>
    <nav class="promo-categories-bar bonus-page-cats" aria-label="Promosyon kategorileri">
        <div class="promo-categories-inner" data-promo-cats-scroll>
            <?php foreach ($kategoriler as $kat): ?>
            <button type="button" class="promo-cat-btn <?= !empty($kat['active']) ? 'active' : '' ?>" data-category="<?= htmlspecialchars($kat['id']) ?>" onclick="if(window.__promoInlineRunFilter){window.__promoInlineRunFilter(this,event);}" ontouchend="if(window.__promoInlineRunFilter){window.__promoInlineRunFilter(this,event);} return false;" onpointerup="if(window.__promoInlineRunFilter){window.__promoInlineRunFilter(this,event);}">
                <i class="promo-cat-icon <?= $kat['icon'] ?>" aria-hidden="true"></i>
                <span><?= htmlspecialchars($kat['label']) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </nav>
    <button type="button" class="promo-cats-scroll-hint promo-cats-scroll-hint--left" data-promo-scroll="left" aria-label="Sola kaydır" tabindex="-1">
        <i class="fas fa-chevron-left" aria-hidden="true"></i>
    </button>
    <button type="button" class="promo-cats-scroll-hint promo-cats-scroll-hint--right" data-promo-scroll="right" aria-label="Sağa kaydır" tabindex="-1">
        <i class="fas fa-chevron-right" aria-hidden="true"></i>
    </button>
</div>
<?php
// Eğer sonuç varsa (veritabanı veya demo), her bir bonusu listele
if (!empty($rows)) {
    echo '<div class="bonus-grid-container">';
    echo '<div class="bonus-grid">';
    
    foreach ($rows as $index => $bonus) {
        // Modern bonus kartı (data-category: ileride veritabanından gelebilir)
        $card_category = isset($bonus['category_id']) ? $bonus['category_id'] : 'slots';
        echo '<article class="bonus-card" data-category="' . htmlspecialchars($card_category) . '" data-promo-index="' . (int) $index . '" role="button" tabindex="0" onclick="if(window.__openPromoModalByIndex){window.__openPromoModalByIndex(' . (int) $index . ');}" onkeydown="if((event.key===\'Enter\'||event.key===\' \') && window.__openPromoModalByIndex){event.preventDefault();window.__openPromoModalByIndex(' . (int) $index . ');}">';
        echo '<div class="bonus-card-inner">';
        
        // Görsel URL kontrolü (modal için tam URL)
        $image_url = !empty($bonus['image_url']) ? promotions_page_normalize_image_url((string) $bonus['image_url']) : 'https://via.placeholder.com/400x300/856A00/ffffff?text=Bonus+Görseli';
        if ($image_url && strpos($image_url, 'http') !== 0) {
            $image_url = '/' . ltrim($image_url, '/');
        }
        echo '<div class="bonus-image">';
        echo '<img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($bonus['title']) . '" loading="lazy" decoding="async">';
        echo '</div>';
        echo '<h3 class="bonus-card-title">' . htmlspecialchars($bonus['title']) . '</h3>';
        echo '</div>';
        echo '</article>';
    }
    
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="no-bonuses">';
    echo '<div class="no-bonuses-content">';
    echo '<i class="fas fa-gift"></i>';
    echo '<h3>Henüz Bonus Bulunmuyor</h3>';
    echo '<p>Yakında harika bonus fırsatları sizlerle olacak. Lütfen takipte kalın!</p>';
    echo '</div>';
    echo '</div>';
}


// Modal için bonus listesi (image_url tam path)
$promoListForModal = [];
$promotionsFromApi = !empty($apiRows);
foreach ($rows as $i => $b) {
    $img = isset($b['image_url']) ? promotions_page_normalize_image_url((string) $b['image_url']) : '';
    if ($img && strpos($img, 'http') !== 0) {
        $img = '/' . ltrim($img, '/');
    }
    $pid = isset($b['id']) ? (int) $b['id'] : 0;
    $promoListForModal[] = [
        'title'       => isset($b['title']) ? $b['title'] : '',
        'image_url'   => $img,
        'link_url'    => isset($b['link_url']) ? (string) $b['link_url'] : '',
        'sections'    => isset($b['sections']) ? $b['sections'] : $defaultSections,
        'promotionId' => $pid,
        'canClaim'    => $promotionsFromApi && $pid > 0,
    ];
}
?>
<script>
window.__PROMO_LIST__ = <?= json_encode($promoListForModal, JSON_UNESCAPED_UNICODE) ?>;

if (typeof window.__openPromoModalByIndex !== 'function') {
    window.__openPromoModalByIndex = function (index) {
        var list = window.__PROMO_LIST__ || [];
        var promo = list[parseInt(index, 10)];
        if (!promo) {
            return;
        }

        var imageUrl = promo.image_url || '';
        if (imageUrl && imageUrl.indexOf('http') !== 0 && imageUrl.indexOf('/') !== 0) {
            imageUrl = '/' + imageUrl;
        }

        var payload = {
            title: promo.title || '',
            imageUrl: imageUrl,
            linkUrl: promo.link_url || '',
            sections: promo.sections || [],
            promotionId: typeof promo.promotionId === 'number' ? promo.promotionId : (parseInt(promo.promotionId, 10) || 0),
            canClaim: !!promo.canClaim
        };

        var tryOpen = function () {
            if (window.BonusDetailModal && typeof window.BonusDetailModal.open === 'function') {
                window.BonusDetailModal.open(payload);
                return true;
            }
            return false;
        };

        if (!tryOpen()) {
            setTimeout(tryOpen, 80);
            setTimeout(tryOpen, 220);
        }
    };
}

if (!window.__promoInlineDelegationBound) {
    window.__promoInlineDelegationBound = true;
    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target || !target.closest) {
            return;
        }
        var card = target.closest('.bonus-card[data-promo-index], .promo-card[data-promo-index]');
        if (!card) {
            return;
        }
        var idx = card.getAttribute('data-promo-index');
        if (idx === null || idx === '') {
            return;
        }
        window.__openPromoModalByIndex(idx);
    }, true);
}

if (!window.__promoInlineCategoryFilterBound) {
    window.__promoInlineCategoryFilterBound = true;

    (function bindInlineCategoryFilter() {
        function normalizeCategory(raw) {
            var value = (raw || '').toString().toLowerCase().trim();
            if (!value) return '';
            value = value.replace(/[\s-]+/g, '_');
            if (value === 'all' || value === 'tumu' || value === 'tum' || value === 'hepsi') return 'tumu';
            if (value === 'livecasino' || value === 'live_casino') return 'live_casino';
            if (value === 'sport' || value === 'sportsbook' || value === 'spor' || value === 'sports') return 'sports';
            if (value === 'lossbonus' || value === 'loss_bonus' || value === 'kayip_bonusu' || value === 'kayipbonusu') return 'loss_bonus';
            return value;
        }

        function runFilter(selectedButton) {
            if (!selectedButton) return;
            var bar = selectedButton.closest('.promo-categories-inner');
            if (!bar) return;
            var buttons = bar.querySelectorAll('.promo-cat-btn');
            var cards = document.querySelectorAll('.promo-card, .bonus-card');
            var category = normalizeCategory(selectedButton.getAttribute('data-category'));

            buttons.forEach(function (b) {
                b.classList.toggle('active', b === selectedButton);
            });

            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var cardCategory = normalizeCategory(card.getAttribute('data-category'));
                var show = !category || category === 'tumu' || cardCategory === category;
                card.classList.toggle('promo-card-hidden', !show);
                card.style.display = show ? '' : 'none';
                card.setAttribute('aria-hidden', show ? 'false' : 'true');
            }
        }

        window.__promoInlineRunFilter = function (btn, event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }
            runFilter(btn);
            return false;
        };

        document.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.closest) return;
            var btn = target.closest('.promo-categories-inner .promo-cat-btn');
            if (!btn) return;
            event.preventDefault();
            runFilter(btn);
        }, true);

        document.addEventListener('touchend', function (event) {
            var target = event.target;
            if (!target || !target.closest) return;
            var btn = target.closest('.promo-categories-inner .promo-cat-btn');
            if (!btn) return;
            event.preventDefault();
            runFilter(btn);
        }, { capture: true, passive: false });

        document.addEventListener('pointerup', function (event) {
            var target = event.target;
            if (!target || !target.closest) return;
            var btn = target.closest('.promo-categories-inner .promo-cat-btn');
            if (!btn) return;
            runFilter(btn);
        }, true);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                var initial = document.querySelector('.promo-categories-inner .promo-cat-btn.active') || document.querySelector('.promo-categories-inner .promo-cat-btn');
                if (initial) runFilter(initial);
            });
        } else {
            var initial = document.querySelector('.promo-categories-inner .promo-cat-btn.active') || document.querySelector('.promo-categories-inner .promo-cat-btn');
            if (initial) runFilter(initial);
        }
    })();
}
</script>
<script src="/assets/js/bonus-detail-modal.js?v=<?= (string) (file_exists(__DIR__ . '/../assets/js/bonus-detail-modal.js') ? filemtime(__DIR__ . '/../assets/js/bonus-detail-modal.js') : 1) ?>"></script>
<script src="/assets/js/promosyonlar.js?v=<?= (string) (file_exists(__DIR__ . '/../assets/js/promosyonlar.js') ? filemtime(__DIR__ . '/../assets/js/promosyonlar.js') : 1) ?>"></script>
<link rel="stylesheet" href="/assets/css/bonus-detail-modal.css?v=<?= (string) (file_exists(__DIR__ . '/../assets/css/bonus-detail-modal.css') ? filemtime(__DIR__ . '/../assets/css/bonus-detail-modal.css') : 1) ?>">

<?php include __DIR__ . '/../views/partials/bonus-detail-modal.php'; ?>

<style>
/* ========== Kategori filtresi (mor tema) ========== */
/* Üst boşluğu azalt: promosyon sayfası içeriği */
.bonusRow.ng-star-inserted {
    padding-top: 8px;
}
.bonusRow .promo-categories-scroll-wrap,
.bonusRow .promo-categories-bar.bonus-page-cats {
    margin-top: 6px;
}

.mainWrap.page-promotions .bonusRow {
    padding-top: 8px !important;
}

.mainWrap.page-promotions .promo-categories-scroll-wrap,
.mainWrap.page-promotions .promo-categories-bar.bonus-page-cats {
    margin-top: 6px !important;
}

.promo-categories-scroll-wrap {
    position: relative;
    width: 100%;
    margin-bottom: 0.75rem;
}

.promo-categories-bar.bonus-page-cats {
    background: transparent;
    border: none;
    border-radius: 0;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0;
    box-shadow: none;
}

.bonus-page-cats .promo-categories-inner {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-start;
    align-items: center;
    min-height: 44px;
}

.bonus-page-cats .promo-cat-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    width: 80px;
    min-width: 80px;
    padding: 5px;
    border: none;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.5);
    font-size: 10px;
    font-weight: 400;
    cursor: pointer;
    transform: translateY(0);
    transition: transform 0.24s cubic-bezier(0.22, 1, 0.36, 1),
                background 0.24s ease,
                color 0.24s ease,
                box-shadow 0.24s ease;
    white-space: nowrap;
}

.bonus-page-cats .promo-cat-btn .promo-cat-icon,
.bonus-page-cats .promo-cat-btn i {
    font-size: 16px !important;
    width: 16px !important;
    height: 16px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    transition: transform 0.24s cubic-bezier(0.22, 1, 0.36, 1), color 0.24s ease;
}

.bonus-page-cats .promo-cat-btn span {
    font-size: 10px;
    line-height: 1.2;
}

.bonus-page-cats .promo-cat-btn:hover,
.bonus-page-cats .promo-cat-btn:focus-visible {
    background: rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.9);
    transform: translateY(-2px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.22);
    outline: none;
}

.bonus-page-cats .promo-cat-btn:hover .promo-cat-icon,
.bonus-page-cats .promo-cat-btn:hover i,
.bonus-page-cats .promo-cat-btn:focus-visible .promo-cat-icon,
.bonus-page-cats .promo-cat-btn:focus-visible i {
    transform: scale(1.08);
}

.bonus-page-cats .promo-cat-btn.active {
    background: rgb(32, 32, 34);
    color: #fff;
    box-shadow: 0 2px 4px 0 rgba(0, 0, 0, 0.15);
    transform: translateY(0);
}

.bonus-page-cats .promo-cat-btn.active .promo-cat-icon,
.bonus-page-cats .promo-cat-btn.active i {
    color: #fff;
}

.promo-cats-scroll-hint {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 30px;
    height: fit-content;
    margin-top: auto;
    margin-bottom: auto;
    margin-left: 0;
    margin-right: 0;
    padding: 0;
    border: none;
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    line-height: 1;
    color: rgba(255, 255, 255, 0.92);
    cursor: pointer;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease;
    -webkit-tap-highlight-color: transparent;
}

.promo-cats-scroll-hint i {
    line-height: 1;
    display: block;
}

.promo-cats-scroll-hint--left {
    left: 0;
    justify-content: flex-start;
    padding-left: 2px;
    background: linear-gradient(to right, rgba(14, 1, 36, 0.97) 30%, rgba(14, 1, 36, 0));
}

.promo-cats-scroll-hint--right {
    right: 0;
    justify-content: flex-end;
    padding-right: 2px;
    background: linear-gradient(to left, rgba(14, 1, 36, 0.97) 30%, rgba(14, 1, 36, 0));
}

.promo-categories-scroll-wrap.promo-cats--overflow:not(.promo-cats--at-end) .promo-cats-scroll-hint--right {
    opacity: 1;
    pointer-events: auto;
}

.promo-categories-scroll-wrap.promo-cats--overflow:not(.promo-cats--at-start) .promo-cats-scroll-hint--left {
    opacity: 1;
    pointer-events: auto;
}

@media (max-width: 991px) {
    .promo-categories-bar.bonus-page-cats {
        padding: 0.4rem 0.2rem;
    }

    .bonus-page-cats .promo-categories-inner {
        justify-content: flex-start;
        overflow-x: auto;
        flex-wrap: nowrap;
        padding-bottom: 4px;
        gap: 0.35rem;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .bonus-page-cats .promo-categories-inner::-webkit-scrollbar {
        display: none;
    }

    .bonus-page-cats .promo-cat-btn {
        flex-shrink: 0;
        width: 72px;
        min-width: 72px;
        padding: 5px;
        gap: 5px;
    }

    .bonus-page-cats .promo-cat-btn .promo-cat-icon,
    .bonus-page-cats .promo-cat-btn i {
        font-size: 14px !important;
        width: 14px !important;
        height: 14px !important;
    }

    .bonus-page-cats .promo-cat-btn span {
        font-size: 9px;
    }

    .promo-cats-scroll-hint {
        width: 26px;
        font-size: 11px;
    }
}

/* Cache/cascade farklari icin sert override */
.mainWrap.page-promotions .bonus-page-cats .promo-cat-btn i,
.mainWrap.page-promotions .bonus-page-cats .promo-cat-btn .promo-cat-icon {
    font-size: 16px !important;
    width: 16px !important;
    height: 16px !important;
}

/* SLOT ikonunu global slots.css override'ından koru */
.mainWrap.page-promotions .bonus-page-cats .promo-cat-icon.bc-i-slots::before {
    font-family: BetConstruct-Icons !important;
    font-weight: 400 !important;
    content: "\e955" !important;
}

@media (max-width: 991px) {
    .mainWrap.page-promotions .bonus-page-cats .promo-cat-btn i,
    .mainWrap.page-promotions .bonus-page-cats .promo-cat-btn .promo-cat-icon {
        font-size: 14px !important;
        width: 14px !important;
        height: 14px !important;
    }
}

/* Bonus Grid Container */
.bonus-grid-container {
    width: 100%;
    padding: 8px 0;
}

/* Modern Bonus Grid - CasinoMilyon tasarımı: masaüstü 2 sütun, mobil tek sütun */
.bonus-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    width: 100%;
    padding: 0 7px;
}

/* Bonus Kartları — sade, köşeleri 4px, başlık çubuğu altta */
.bonus-card {
    position: relative;
    background: transparent;
    border-radius: 4px;
    border: none;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.32s cubic-bezier(0.22, 1, 0.36, 1),
                box-shadow 0.32s cubic-bezier(0.22, 1, 0.36, 1);
    box-shadow: none;
    display: block;
}

.bonus-card.promo-card-hidden,
.promo-card.promo-card-hidden {
    display: none !important;
    visibility: hidden !important;
}

.bonus-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 14px 28px rgba(0, 0, 0, 0.38);
}

.bonus-card::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 4px;
    pointer-events: none;
    background: linear-gradient(130deg, rgba(255, 255, 255, 0.14), rgba(255, 255, 255, 0));
    opacity: 0;
    transition: opacity 0.32s ease;
}

.bonus-card:hover::after {
    opacity: 1;
}

.bonus-card-inner {
    display: flex;
    flex-direction: column;
}

/* Bonus Görsel */
.bonus-image {
    position: relative;
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    flex-shrink: 0;
    border-radius: 4px 4px 0 0;
}

.bonus-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    border-radius: 4px 4px 0 0;
    transform: scale(1);
    transition: transform 0.42s cubic-bezier(0.22, 1, 0.36, 1), filter 0.32s ease;
}

.bonus-card:hover .bonus-image img {
    transform: scale(1.03);
    filter: brightness(1.05);
}

/* Mobil/touch cihazlarda hover state yapışabildiği için görseller bulanık görünür.
   Bu yüzden kart görsellerinde scale/filter efekti kapatılır. */
@media (hover: none), (pointer: coarse), (max-width: 991px) {
    .bonus-card:hover {
        transform: none;
        box-shadow: none;
    }

    .bonus-card:hover::after {
        opacity: 0;
    }

    .bonus-card .bonus-image img,
    .bonus-card:hover .bonus-image img {
        transform: none !important;
        filter: contrast(1.06) saturate(1.04) brightness(1.01) !important;
        transition: none !important;
        will-change: auto !important;
        image-rendering: auto !important;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        width: 100% !important;
        height: auto !important;
        object-fit: contain !important;
        object-position: center center !important;
    }

    .bonus-card,
    .bonus-card-inner,
    .bonus-image {
        transform: none !important;
        filter: none !important;
    }

    .bonus-image {
        aspect-ratio: auto !important;
        height: auto !important;
        min-height: 0 !important;
        background: #120424;
    }
}

/* Kart başlık çubuğu — orijinal CasinoMilyon: rgba(255,255,255,0.1) arka plan */
.bonus-card-title {
    margin: 1px 0 0;
    padding: 0 10px;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    line-height: 34px;
    text-align: left;
    text-transform: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: background 0.28s ease;
}

.bonus-card:hover .bonus-card-title {
    background: rgba(255, 255, 255, 0.18);
}

@media (prefers-reduced-motion: reduce) {
    .bonus-page-cats .promo-cat-btn,
    .bonus-page-cats .promo-cat-btn .promo-cat-icon,
    .bonus-page-cats .promo-cat-btn i,
    .bonus-card,
    .bonus-card::after,
    .bonus-image img,
    .bonus-card-title {
        transition: none !important;
    }

    .bonus-page-cats .promo-cat-btn:hover,
    .bonus-page-cats .promo-cat-btn:focus-visible,
    .bonus-card:hover {
        transform: none;
    }
}

/* Countdown banner (opsiyonel) — kart üzerinde sol üst */
.bonus-card .countdown-banner-content {
    position: absolute;
    top: 5px;
    left: 5px;
    display: flex;
    padding: 5px;
    background: rgba(32, 32, 34, 0.8);
    border-radius: 4px;
    z-index: 1;
}

.bonus-card .countdown-banner-counter {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 0 9px 0 0;
}

.bonus-card .countdown-banner-counter:last-child {
    padding-right: 0;
}

.bonus-card .countdown-banner-date {
    font-size: 12px;
    font-weight: 500;
    color: #fff;
}

.bonus-card .countdown-banner-names {
    font-size: 10px;
    color: #fff;
}


/* Bonus İçerik */
.bonus-content {
    padding: 20px;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}

.bonus-title {
    color: #ffffff;
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 10px;
    line-height: 1.3;
    min-height: 46px;
}

.bonus-description {
    color: #a0a0a0;
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 15px;
    flex-grow: 1;
}

.withdrawal-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(133, 106, 0, 0.2);
    color: #856A00;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid rgba(133, 106, 0, 0.3);
    margin-top: auto;
}

/* Bonus Yok Stili */
.no-bonuses {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 400px;
    text-align: center;
    width: 100%;
}

.no-bonuses-content {
    max-width: 400px;
}

.no-bonuses-content i {
    font-size: 64px;
    color: #856A00;
    margin-bottom: 20px;
}

.no-bonuses-content h3 {
    color: #ffffff;
    font-size: 24px;
    margin-bottom: 10px;
}

.no-bonuses-content p {
    color: #a0a0a0;
    font-size: 16px;
    line-height: 1.5;
}

/* Responsive Breakpoints — CasinoMilyon: masaüstü 2 sütun, mobil tek sütun */
/* Büyük masaüstü (1200px+) — 2 yan yana */
@media (min-width: 1200px) {
    .bonus-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .bonus-card {
        max-width: 100%;
    }
}

/* Orta masaüstü (992px - 1199px) — 2 yan yana */
@media (min-width: 992px) and (max-width: 1199px) {
    .bonus-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

/* Tablet (768px - 991px) — 2 yan yana */
@media (max-width: 991px) {
    .bonus-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
}

/* Mobil — alt alta tek sütun */
@media (max-width: 767px) {
    .bonus-grid {
        grid-template-columns: 1fr;
        gap: 10px;
        padding: 0 7px;
    }

    .bonus-grid-container {
        padding: 8px 0;
    }
}

/* Küçük mobil ekranlar (max-width: 480px) */
@media (max-width: 480px) {
    .bonus-grid {
        grid-template-columns: 1fr;
    }

    .bonus-card-title {
        font-size: 12px;
    }
}
</style>
                                                          
                </div>
            </div>
        </div>
    </div>
</app-g6-promotions-bonus>
</app-promotions-bonus>
</div>
</section>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>