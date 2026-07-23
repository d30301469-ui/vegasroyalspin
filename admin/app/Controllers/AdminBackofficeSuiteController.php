<?php

declare(strict_types=1);

final class AdminBackofficeSuiteController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('dashboard');

        $modules = $this->suiteModules();
        $readyCount = count(array_filter($modules, static fn (array $module): bool => ($module['status'] ?? '') === 'ready'));
        $partialCount = count(array_filter($modules, static fn (array $module): bool => ($module['status'] ?? '') === 'partial'));
        $plannedCount = count(array_filter($modules, static fn (array $module): bool => ($module['status'] ?? '') === 'planned'));

        $this->view('backoffice-suite/index', [
            'title' => 'Backoffice Suite',
            'active' => 'backoffice-suite',
            'crumbs' => 'Admin | Backoffice Suite',
            'modules' => $modules,
            'referenceScreens' => $this->referenceScreens(),
            'summary' => [
                ['label' => 'Referans ekran', 'value' => count($this->referenceScreens()), 'class' => 'primary'],
                ['label' => 'Hazır modül', 'value' => $readyCount, 'class' => 'success'],
                ['label' => 'Kısmi modül', 'value' => $partialCount, 'class' => 'warning'],
                ['label' => 'Planlanan modül', 'value' => $plannedCount, 'class' => 'purple'],
            ],
            'liveMetrics' => [
                ['label' => 'Toplam üye', 'value' => $this->scalar('SELECT COUNT(*) FROM users')],
                ['label' => 'Bekleyen yatırım', 'value' => $this->scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'deposit' AND status = 'pending'")],
                ['label' => 'Bekleyen çekim', 'value' => $this->scalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'withdraw' AND status = 'pending'")],
                ['label' => 'Bekleyen KYC', 'value' => $this->scalar("SELECT COUNT(*) FROM kyc_requests WHERE status = 'pending'")],
                ['label' => 'Açık destek', 'value' => $this->scalar("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','answered')")],
                ['label' => 'AML uyarısı', 'value' => $this->scalar("SELECT COUNT(*) FROM aml_alerts WHERE status = 'open'")],
                ['label' => 'Bonus talebi', 'value' => $this->scalar("SELECT COUNT(*) FROM bonus_claim_requests WHERE status IN ('pending','requested','waiting')")],
                ['label' => 'Aktif içerik', 'value' => $this->scalar("SELECT COUNT(*) FROM promotions WHERE status IN ('active', 'published', '1')") + $this->scalar("SELECT COUNT(*) FROM sliders WHERE status IN ('active', 'published', '1')")],
                ['label' => 'Toplam bakiye', 'value' => (float) $this->scalar('SELECT COALESCE(SUM(balance), 0) FROM users'), 'format' => 'money'],
            ],
        ]);
    }

    private function suiteModules(): array
    {
        return [
            [
                'title' => 'Real-Time Dashboard',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Canlı trafik, finans, üye ve operasyon kuyrukları tek ana ekranda özetlenir.',
                'reference' => 'dashboard-full.webp, hero-dashboard.webp',
                'localRoute' => '/dashboard',
                'localLabel' => 'Dashboard',
                'nextStep' => 'Chart widgetlarını provider GGR ve bonus metrikleriyle genişlet.',
            ],
            [
                'title' => 'Dashboard Charts',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Finans, ziyaret ve oyun özetleri mevcut; grafikler ve finansal rapor controller route ve sidebar tamamlandı.',
                'reference' => 'finance-report.webp',
                'localRoute' => '/reports/charts',
                'localLabel' => 'Grafikler',
                'nextStep' => 'GGR ve provider bazlı metrikler eklenebilir.',
            ],
            [
                'title' => 'Player Management',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Üye listesi, detay, bakiye düzenleme, KYC, bonus talepleri ve dondurulan hesaplar mevcut.',
                'reference' => 'players.webp',
                'localRoute' => '/module?key=users',
                'localLabel' => 'Üyeler',
                'nextStep' => 'Üye detayına risk/segment notları eklenebilir.',
            ],
            [
                'title' => 'Player GeoMap',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Visitor log lokasyon verisi ülke/şehir bazlı dağılım ve son ziyaretçi listesiyle harita panelinde gösteriliyor.',
                'reference' => 'players-geomap.webp',
                'localRoute' => '/reports/geomap',
                'localLabel' => 'Oyuncu Haritası',
                'nextStep' => 'IP/GeoIP kayıtlarıyla oyuncu segmentasyonu eklenebilir.',
            ],
            [
                'title' => 'Advanced Filtering',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Tüm modüllerde arama, tarih filtreleme ve sayfalama aktif. AdminTableRepository üzerinden generic tablo yönetimi mevcut.',
                'reference' => 'players-filter.webp',
                'localRoute' => '/module?key=users',
                'localLabel' => 'Üye tablosu',
                'nextStep' => 'Kolon bazlı çoklu filtre pipeline\'ı eklenebilir.',
            ],
            [
                'title' => 'Risk Analysis',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Çoklu bekleyen çekim, yüksek hacimli yatırım, dondurulmuş hesap ve KYC bekleyen yüksek bakiyeli oyuncu sinyalleri aktif.',
                'reference' => 'player-risk-high.webp',
                'localRoute' => '/compliance/risk-analysis',
                'localLabel' => 'Risk Analizi',
                'nextStep' => 'Provider bazlı oyuncu P/L, RTP sapması ve alarm kuralları eklenebilir.',
            ],
            [
                'title' => 'Bonus Engine',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Aktif bonus, bonus talebi, promocode kayıtları, onay/red işlemleri ve toplu sıfırlama mevcut.',
                'reference' => 'bonus-wizard1.webp',
                'localRoute' => '/module?key=bonus-claims',
                'localLabel' => 'Bonus talepleri',
                'nextStep' => 'Deposit/loss/freespin/cash wizard akışı tek formda birleştirilebilir.',
            ],
            [
                'title' => 'Reports & Analytics',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Finansal rapor, grafikler, takvim ve dashboard özetleri aktif; tarih bazlı filtreleme ve gruplama mevcut.',
                'reference' => 'finance-report.webp',
                'localRoute' => '/reports/financial',
                'localLabel' => 'Finansal rapor',
                'nextStep' => 'Provider maliyet ve ödeme dönüşüm raporları eklenebilir.',
            ],
            [
                'title' => 'CMS & Content',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Promosyon, slider, auth slider, duyuru, homepage section, footer ve mobil menü yönetimi mevcut.',
                'reference' => 'cms-banners.webp',
                'localRoute' => '/module?key=promotions',
                'localLabel' => 'Promosyonlar',
                'nextStep' => 'Media library ve popup/story içerikleri için ek modül aç.',
            ],
            [
                'title' => 'Settings & Security',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Adminler, yetkiler, oturumlar, loglar, site ayarları ve provider ayarları yönetiliyor.',
                'reference' => 'settings-components.webp',
                'localRoute' => '/permissions',
                'localLabel' => 'Admin yetkileri',
                'nextStep' => '2FA zorunluluğu ve IP whitelist ekranı ayrı güvenlik kartına taşınabilir.',
            ],
            [
                'title' => 'Domain Management',
                'status' => 'ready',
                'statusText' => 'Hazır',
                'description' => 'Frontend/backend URL, allowed hosts ve domain yapılandırması site ayarları ve deploy_domains.php üzerinden yönetiliyor.',
                'reference' => 'domains-management.webp',
                'localRoute' => '/site-settings',
                'localLabel' => 'Site ayarları',
                'nextStep' => 'SSL sertifika durumu ve Cloudflare entegrasyon kontrolü eklenebilir.',
            ],
        ];
    }

    private function referenceScreens(): array
    {
        return [
            ['name' => 'Hero dashboard', 'file' => 'hero-dashboard.webp', 'area' => 'Dashboard vitrin'],
            ['name' => 'Dashboard full', 'file' => 'dashboard-full.webp', 'area' => 'Canlı KPI ve grafikler'],
            ['name' => 'Players', 'file' => 'players.webp', 'area' => 'Üye yönetimi'],
            ['name' => 'Players filter', 'file' => 'players-filter.webp', 'area' => 'Gelişmiş filtre'],
            ['name' => 'Players geomap', 'file' => 'players-geomap.webp', 'area' => 'GeoIP harita'],
            ['name' => 'Player risk high', 'file' => 'player-risk-high.webp', 'area' => 'Risk analizi'],
            ['name' => 'Bonus wizard', 'file' => 'bonus-wizard1.webp', 'area' => 'Bonus motoru'],
            ['name' => 'Finance report', 'file' => 'finance-report.webp', 'area' => 'Raporlama'],
            ['name' => 'CMS banners', 'file' => 'cms-banners.webp', 'area' => 'CMS ve medya'],
            ['name' => 'Settings components', 'file' => 'settings-components.webp', 'area' => 'Ayarlar ve güvenlik'],
            ['name' => 'Domains management', 'file' => 'domains-management.webp', 'area' => 'Domain yönetimi'],
        ];
    }

    private function scalar(string $sql): int
    {
        try {
            return (int) AdminDatabase::pdo()->query($sql)->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}
