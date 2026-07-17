<?php
require_once __DIR__ . '/../core/bootstrap.php';

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
    ['id' => 'tumu', 'label' => 'TÜMÜ', 'icon' => 'fa-th-large', 'active' => true],
    ['id' => 'sports', 'label' => 'SPOR', 'icon' => 'fa-futbol'],
    ['id' => 'live_casino', 'label' => 'CANLI CASINO', 'icon' => 'fa-dice'],
    ['id' => 'slots', 'label' => 'SLOT', 'icon' => 'fa-coins'],
    ['id' => 'loss_bonus', 'label' => 'KAYIP BONUSU', 'icon' => 'fa-layer-group'],
    ['id' => 'vip', 'label' => 'VIP', 'icon' => 'fa-crown'],
];
?>
<div class="promo-categories-scroll-wrap" data-promo-cats-wrap>
    <nav class="promo-categories-bar bonus-page-cats" aria-label="Promosyon kategorileri">
        <div class="promo-categories-inner" data-promo-cats-scroll>
            <?php foreach ($kategoriler as $kat): ?>
            <button type="button" class="promo-cat-btn <?= !empty($kat['active']) ? 'active' : '' ?>" data-category="<?= htmlspecialchars($kat['id']) ?>">
                <i class="fas <?= $kat['icon'] ?>" aria-hidden="true"></i>
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
        echo '<div class="bonus-card" data-category="' . htmlspecialchars($card_category) . '" data-promo-index="' . (int) $index . '">';
        echo '<div class="bonus-card-inner">';
        
        // Görsel URL kontrolü (modal için tam URL)
        $image_url = !empty($bonus['image_url']) ? promotions_page_normalize_image_url((string) $bonus['image_url']) : 'https://via.placeholder.com/400x300/856A00/ffffff?text=Bonus+Görseli';
        if ($image_url && strpos($image_url, 'http') !== 0) {
            $image_url = '/' . ltrim($image_url, '/');
        }
        echo '<div class="bonus-image">';
        echo '<img src="' . htmlspecialchars($image_url) . '" alt="' . htmlspecialchars($bonus['title']) . '">';
        echo '</div>';
        echo '</div>';
        echo '</div>';
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
</script>
<script src="/assets/js/bonus-detail-modal.js?v=<?= (string) (file_exists(__DIR__ . '/../assets/js/bonus-detail-modal.js') ? filemtime(__DIR__ . '/../assets/js/bonus-detail-modal.js') : 1) ?>"></script>
<script src="/assets/js/promosyonlar.js?v=<?= (string) (file_exists(__DIR__ . '/../assets/js/promosyonlar.js') ? filemtime(__DIR__ . '/../assets/js/promosyonlar.js') : 1) ?>"></script>
<link rel="stylesheet" href="/assets/css/bonus-detail-modal.css?v=<?= (string) (file_exists(__DIR__ . '/../assets/css/bonus-detail-modal.css') ? filemtime(__DIR__ . '/../assets/css/bonus-detail-modal.css') : 1) ?>">

<?php include __DIR__ . '/../views/partials/bonus-detail-modal.php'; ?>

<style>
/* ========== Kategori filtresi (mor tema) ========== */
/* Üst boşluğu azalt: promosyon sayfası içeriği */
.bonusRow.ng-star-inserted {
    padding-top: 0;
}
.bonusRow .promo-categories-scroll-wrap,
.bonusRow .promo-categories-bar.bonus-page-cats {
    margin-top: 0;
}

.promo-categories-scroll-wrap {
    position: relative;
    width: 100%;
    margin-bottom: 1rem;
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
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    border: 1px solid rgba(81, 33, 223, 0.3);
    border-radius: 4px;
    background: rgba(40, 25, 70, 0.8);
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, color 0.2s, border-color 0.2s, box-shadow 0.2s;
    white-space: nowrap;
}

.bonus-page-cats .promo-cat-btn i {
    font-size: 0.95rem;
    opacity: 0.95;
}

.bonus-page-cats .promo-cat-btn:hover,
.bonus-page-cats .promo-cat-btn:focus-visible {
    background: rgba(81, 33, 223, 0.35);
    border-color: rgba(129, 80, 235, 0.5);
    color: #fff;
    outline: none;
}

.bonus-page-cats .promo-cat-btn.active {
    background: linear-gradient(135deg, #6d28d9 0%, #8b5cf6 100%);
    border-color: #7c3aed;
    color: #fff;
    box-shadow: 0 4px 16px rgba(139, 92, 246, 0.4);
}

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
        font-size: 0.62rem;
        padding: 0.35rem 0.45rem;
        gap: 0.28rem;
        letter-spacing: 0.02em;
    }

    .bonus-page-cats .promo-cat-btn i {
        font-size: 0.72rem;
    }

    .promo-cats-scroll-hint {
        width: 26px;
        font-size: 11px;
    }
}

/* Bonus Grid Container */
.bonus-grid-container {
    width: 100%;
    padding: 20px 0;
}

/* Modern Bonus Grid - Masaüstü 4 sütun, mobil alt alta */
.bonus-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 25px;
    width: 100%;
}

/* Bonus Kartları */
.bonus-card {
    position: relative;
    background: linear-gradient(145deg, #0a0a0a, #111111);
    border-radius: 16px;
    border: 1px solid rgba(133, 106, 0, 0.2);
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* Soldan sağa mor neon parlama (shine) hover efekti */
.bonus-card::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 70%;
    height: 100%;
    background: linear-gradient(
        105deg,
        transparent 0%,
        transparent 30%,
        rgba(139, 92, 246, 0.5) 45%,
        rgba(167, 139, 250, 0.85) 50%,
        rgba(139, 92, 246, 0.5) 55%,
        transparent 70%,
        transparent 100%
    );
    box-shadow: 0 0 30px rgba(139, 92, 246, 0.6);
    transition: left 0.5s ease;
    pointer-events: none;
}

.bonus-card:hover::after {
    left: 100%;
}

.bonus-card-inner {
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* Bonus Görsel - 57:40 en-boy oranı */
.bonus-image {
    position: relative;
    width: 100%;
    aspect-ratio: 57 / 40;
    overflow: hidden;
    flex-shrink: 0;
}

.bonus-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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

/* Responsive Breakpoints */
/* Büyük masaüstü (1200px+) — 4 yan yana */
@media (min-width: 1200px) {
    .bonus-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 30px;
    }
    
    .bonus-card {
        max-width: 100%;
    }
}

/* Orta masaüstü (992px - 1199px) — 4 yan yana */
@media (min-width: 992px) and (max-width: 1199px) {
    .bonus-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
    }
    
    .bonus-title {
        font-size: 16px;
    }
}

/* Tablet (768px - 991px) — 2 yan yana */
@media (max-width: 991px) {
    .bonus-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }
    
    .bonus-content {
        padding: 15px;
    }
}

/* Mobil — alt alta tek sütun */
@media (max-width: 767px) {
    .bonus-grid {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 15px 0;
    }
    
    .bonus-grid-container {
        padding: 10px 0;
    }
}

/* Küçük mobil ekranlar (max-width: 480px) */
@media (max-width: 480px) {
    .bonus-grid {
        grid-template-columns: 1fr;
    }
    
    .bonus-title {
        font-size: 16px;
        min-height: auto;
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