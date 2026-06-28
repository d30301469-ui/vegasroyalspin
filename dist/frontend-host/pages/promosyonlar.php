<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../views/layouts/head_full.php';
include __DIR__ . '/../views/partials/header.php';

// Kategoriler (görseldeki gibi)
$kategoriler = [
    ['id' => 'bonuslar', 'label' => 'BONUSLAR', 'icon' => 'fa-th-large', 'active' => true],
    ['id' => 'spor', 'label' => 'SPOR', 'icon' => 'fa-futbol'],
    ['id' => 'slot-casino', 'label' => 'SLOT & CASINO', 'icon' => 'fa-dice'],
    ['id' => 'yeni-uyeler', 'label' => 'YENİ ÜYELERE ÖZEL', 'icon' => 'fa-gift'],
    ['id' => 'kayip-bonuslari', 'label' => 'KAYIP BONUSLARI', 'icon' => 'fa-coins'],
    ['id' => 'yatirim-bonuslari', 'label' => 'YATIRIM BONUSLARI', 'icon' => 'fa-chart-line'],
    ['id' => 'telegram', 'label' => 'TELEGRAM BONUSU', 'icon' => 'fa-paper-plane'],
    ['id' => 'turnuvalar', 'label' => 'TURNUVALAR', 'icon' => 'fa-trophy'],
];

// Veritabanından bonusları çek (bonuses tablosu hazır olduğunda tekrar aktif edilebilir)
$promosyonlar = [];
// try {
//     $stmt = $mainDb->query("SELECT id, title, description, image_url, detail_image_url, max_withdrawal FROM bonuses ORDER BY created_at DESC");
//     if ($stmt) {
//         $promosyonlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
//     }
// } catch (PDOException $e) {
//     // Tablo yoksa veya hata varsa örnek veri kullan
// }

// Accordion bölümleri — tüm bonuslarda kullanılacak örnek yapı (API/CMS'den gelecek)
$defaultSections = [
    [
        'title' => 'BONUSTAN NASIL FAYDALANABİLİRİM',
        'content' => '<p>İlgili bonusu almak için hesabınıza giriş yapın, kampanya sayfasından bu bonusu seçin ve "Katıl" butonuna tıklayın. Yatırım veya kayıp bonusları için belirtilen minimum tutarı yatırmanız veya kayıp yaşamanız gerekir. Kurallar kampanya detayında belirtilmiştir.</p>',
    ],
    [
        'title' => 'BONUS ÇEVRİM ŞARTI',
        'content' => '<p>Bonusun çevrim şartı bonus tutarı ve yatırım miktarına göre belirlenir. Çevrim tamamlanmadan çekim yapılamaz. Çevrim oranı kampanya sayfasında ilan edilir.</p>',
    ],
    [
        'title' => 'BONUS GENEL KURALLARI',
        'content' => '<p>Genel kurallar: Bonus tek hesapta kullanılır. Suistimal tespitinde bonus iptal edilir. Site kurallarına uyulması zorunludur.</p>',
    ],
];

// Örnek promosyonlar — demo görseller 1:2 oranında (en:boy)
if (empty($promosyonlar)) {
    $promosyonlar = [
        [
            'id' => 1,
            'title' => 'GELENEKSEL ROYAL FREESPIN 400 ADET FREESPIN HEDİYE!',
            'description' => 'Royal Free Spin kampanyası ile 400 adet ücretsiz dönüş kazanın.',
            'image_url' => '/assets/images/promosyonlar/demo-1.svg',
            'theme' => 'genie',
            'badge' => null,
            'sections' => $defaultSections,
        ],
        [
            'id' => 2,
            'title' => '%10 ÇEVRİMSİZ YATIRIM+%30 KAYIP BONUSU',
            'description' => 'Minimum 2.500TL, maksimum 100.000TL arasındaki yatırımlarınıza %10 Yatırım + %30 Kayıp Bonusu.',
            'image_url' => '/assets/images/promosyonlar/demo-2.svg',
            'theme' => 'ramadan',
            'badge' => 'POP',
            'sections' => $defaultSections,
        ],
        [
            'id' => 3,
            'title' => 'ROYAL ULTRA DOUBLE - 1.000TL Yatır 2.000TL, 2.000TL Yatır 4.000TL',
            'description' => 'Yatırımınızı ikiye katlayan özel kampanya.',
            'image_url' => '/assets/images/promosyonlar/demo-3.svg',
            'theme' => 'double',
            'badge' => null,
            'sections' => $defaultSections,
        ],
        [
            'id' => 4,
            'title' => '250 DENEME BONUSU',
            'description' => 'Yeni üyelere özel 250 deneme bonusu.',
            'image_url' => '/assets/images/promosyonlar/demo-4.svg',
            'theme' => 'trial',
            'badge' => null,
            'sections' => $defaultSections,
        ],
        [
            'id' => 5,
            'title' => 'YATIRIM BONUSU %100 – 5.000 TL\'YE KADAR',
            'description' => 'İlk yatırımınızda %100 bonus, 5.000 TL\'ye kadar.',
            'image_url' => '/assets/images/promosyonlar/demo-5.png',
            'theme' => 'deposit',
            'badge' => 'YENİ',
            'sections' => $defaultSections,
        ],
        [
            'id' => 6,
            'title' => 'TELEGRAM BONUSU – ÖZEL FREE SPIN',
            'description' => 'Telegram kanalımıza katılın, özel free spin hediyesi kazanın.',
            'image_url' => '/assets/images/promosyonlar/demo-6.svg',
            'theme' => 'telegram',
            'badge' => null,
            'sections' => $defaultSections,
        ],
    ];
}
?>
<section class="mainWrap promosyonlar-page">
    <div class="centerWrap">
    <div class="promosyonlar-wrap">
        <!-- Üst kategori navigasyonu -->
        <nav class="promo-categories-bar">
            <div class="promo-categories-inner">
                <?php foreach ($kategoriler as $kat): ?>
                <button type="button" class="promo-cat-btn <?= !empty($kat['active']) ? 'active' : '' ?>" data-category="<?= htmlspecialchars($kat['id']) ?>">
                    <i class="fa-solid <?= $kat['icon'] ?>"></i>
                    <span><?= htmlspecialchars($kat['label']) ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </nav>

        <!-- Promosyon kartları grid -->
        <div class="promo-grid-container">
            <div class="promo-grid">
                <?php foreach ($promosyonlar as $index => $promo):
                    $img = $promo['image_url'] ?? '/assets/images/promosyonlar/demo-1.svg';
                    $title = $promo['title'] ?? 'Promosyon';
                    $badge = $promo['badge'] ?? null;
                ?>
                <article class="promo-card" data-category="bonuslar" data-promo-index="<?= (int) $index ?>">
                    <div class="promo-card-inner">
                        <div class="promo-card-image" style="background-image: url('<?= htmlspecialchars($img, ENT_QUOTES, 'UTF-8') ?>');">
                            <div class="promo-card-logo">
                                <img src="/assets/images/MaltaBetLogo.png" alt="Logo" width="80" height="26">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <?php if ($badge): ?>
                            <span class="promo-card-badge"><?= htmlspecialchars($badge) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="promo-card-content">
                            <h3 class="promo-card-title"><?= htmlspecialchars($title) ?></h3>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
</section>

<script>
window.__PROMO_LIST__ = <?= json_encode(array_values($promosyonlar), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/bonus-detail-modal.js?v=<?= (string) (file_exists(__DIR__ . '/../assets/js/bonus-detail-modal.js') ? filemtime(__DIR__ . '/../assets/js/bonus-detail-modal.js') : 1) ?>"></script>
<script src="/assets/js/promosyonlar.js?v=<?= (string) (file_exists(__DIR__ . '/../assets/js/promosyonlar.js') ? filemtime(__DIR__ . '/../assets/js/promosyonlar.js') : 1) ?>"></script>

<?php include __DIR__ . '/../views/partials/bonus-detail-modal.php'; ?>
<?php include __DIR__ . '/../views/partials/footer.php'; ?>
