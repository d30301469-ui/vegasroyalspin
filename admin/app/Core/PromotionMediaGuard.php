<?php

declare(strict_types=1);

/**
 * Promosyon (bonus) görselleri ve tablo şeması için kendi kendini onaran yardımcı.
 *
 * - ensureSchema(): promotions tablosunda eksik olabilecek kolonları (link_url,
 *   category) otomatik ekler / image_url ve link_url kolonlarını yeterli
 *   genişliğe (VARCHAR(700)) genişletir. Hem local hem canlıda, herhangi bir
 *   admin/API isteğinde otomatik çalışır — manuel migration'a gerek kalmaz.
 * - syncUploadLibrary(): admin/upload/bonuses altındaki hazır promosyon
 *   görsellerini, web'den erişilebilen admin/storage/uploads/promotions
 *   dizinine (htaccess ile /uploads/promotions/* olarak servis edilir) senkronize eder.
 * - repairMissingImages(): veritabanında image_url disk üzerinde bulunmayan
 *   bir dosyayı işaret ediyorsa, başlığa göre en yakın eşleşen görseli bulup
 *   kaydı otomatik düzeltir (best-effort, sadece yerel/backend yolları için).
 */
final class PromotionMediaGuard
{
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private static bool $bootstrapped = false;

    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        try {
            $pdo = AdminDatabase::pdo();
        } catch (Throwable) {
            return;
        }

        self::ensureSchema($pdo);

        if (self::shouldRunMaintenance()) {
            try {
                self::syncUploadLibrary();
                self::repairMissingImages($pdo);
            } catch (Throwable) {
                // Bakım işlemleri sayfayı asla kırmamalı.
            }
            self::markMaintenanceRun();
        }
    }

    public static function ensureSchema(PDO $pdo): void
    {
        try {
            self::ensureColumn($pdo, 'link_url', 'VARCHAR(700) NULL AFTER image_url');
            self::ensureColumn($pdo, 'category', 'VARCHAR(60) NULL AFTER type');
            self::widenVarcharColumn($pdo, 'image_url', 700);
            self::widenVarcharColumn($pdo, 'link_url', 700);
        } catch (Throwable) {
            // Kısmi migrate edilmiş ortamlarda sayfa render edilmeye devam etmeli.
        }
    }

    /**
     * @return list<array{filename: string, url: string}>
     */
    public static function listLibraryImages(): array
    {
        $dir = self::sourceDir();
        if (!is_dir($dir)) {
            return [];
        }

        $out = [];
        foreach (scandir($dir) ?: [] as $file) {
            if ($file === '.' || $file === '..' || !is_file($dir . '/' . $file)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                continue;
            }
            $out[] = ['filename' => $file, 'url' => '/upload/bonuses/' . $file];
        }

        usort($out, static fn (array $a, array $b): int => strcmp($a['filename'], $b['filename']));

        return $out;
    }

    /**
     * admin/upload/bonuses içindeki hazır görselleri /uploads/promotions/ olarak
     * servis edilebilen dizine kopyalar (idempotent — mevcut dosyaların üzerine yazmaz).
     * Bu adım en iyi çaba (best-effort) niteliğindedir: hedef dizin yazılabilir
     * değilse (ör. üretimde izin sorunu) sessizce hiçbir şey yapmaz — görsellerin
     * çalışması buna bağlı DEĞİLDİR, çünkü repairMissingImages() doğrudan git ile
     * deploy edilen /upload/bonuses/ kaynağını referans alır.
     */
    public static function syncUploadLibrary(): int
    {
        $source = self::sourceDir();
        $target = self::libraryDir();

        if (!is_dir($source)) {
            return 0;
        }
        if (!is_dir($target) && !@mkdir($target, 0755, true) && !is_dir($target)) {
            return 0;
        }
        if (!is_writable($target)) {
            return 0;
        }

        $copied = 0;
        foreach (scandir($source) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $source . '/' . $file;
            if (!is_file($srcPath)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                continue;
            }
            $destPath = $target . '/' . $file;
            if (is_file($destPath)) {
                continue;
            }
            if (@copy($srcPath, $destPath)) {
                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Diskte bulunmayan yerel image_url kayıtlarını başlığa en yakın kütüphane
     * görseliyle eşleştirip düzeltir. Yalnızca /uploads/, /storage/uploads/,
     * /admin/uploads/ veya /upload/bonuses/ ile başlayan (yani backend'de
     * barındırılan) yollar için çalışır; harici CDN URL'lerine
     * (icons.casinomilyon*.com vb.) dokunmaz. Onarılan kayıtlar, izin sorunu
     * olsa bile her zaman çalışan git-deploy edilmiş /upload/bonuses/ kaynağına
     * yönlendirilir.
     */
    public static function repairMissingImages(PDO $pdo): int
    {
        $libraryFiles = [];
        foreach (self::listLibraryImages() as $item) {
            $libraryFiles[] = $item['filename'];
        }
        if ($libraryFiles === []) {
            return 0;
        }

        try {
            $stmt = $pdo->query("SELECT id, title, image_url FROM promotions WHERE image_url IS NOT NULL AND image_url <> ''");
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return 0;
        }
        if ($rows === []) {
            return 0;
        }

        $update = $pdo->prepare('UPDATE promotions SET image_url = :image_url WHERE id = :id');
        $fixed = 0;

        foreach ($rows as $row) {
            $raw = trim((string) ($row['image_url'] ?? ''));
            if ($raw === '' || preg_match('#^https?://#i', $raw) === 1) {
                continue;
            }

            $relative = self::normalizeToUploadsRelative($raw);
            if (!str_starts_with($relative, '/uploads/') && !str_starts_with($relative, '/upload/bonuses/')) {
                continue;
            }

            // Kayıt zaten doğrudan çalışan /upload/bonuses/ kaynağını mı gösteriyor?
            $filename = basename($relative);

            if ($filename !== '' && is_file(self::sourceDir() . '/' . $filename)) {
                // Dosya adı kütüphanede birebir mevcut — kaydı her zaman çalışan
                // /upload/bonuses/ yoluna sabitle (eski /uploads/promotions/ önekini
                // senkron kopyanın var olup olmamasına bağlı kalmadan düzelt).
                $canonical = '/upload/bonuses/' . $filename;
                if ($raw !== $canonical) {
                    $update->execute(['image_url' => $canonical, 'id' => $row['id']]);
                    $fixed++;
                }
                continue;
            }

            $titleSlug = self::slugify((string) ($row['title'] ?? ''));
            if ($titleSlug === '') {
                continue;
            }

            $best = null;
            $bestPct = 0.0;
            $secondPct = 0.0;
            foreach ($libraryFiles as $file) {
                $fileSlug = self::slugify(pathinfo($file, PATHINFO_FILENAME));
                if ($fileSlug === '') {
                    continue;
                }
                $pct = 0.0;
                similar_text($titleSlug, $fileSlug, $pct);
                if ($pct > $bestPct) {
                    $secondPct = $bestPct;
                    $bestPct = $pct;
                    $best = $file;
                } elseif ($pct > $secondPct) {
                    $secondPct = $pct;
                }
            }

            if ($best !== null && $bestPct >= 55.0 && ($bestPct - $secondPct) >= 8.0) {
                $update->execute(['image_url' => '/upload/bonuses/' . $best, 'id' => $row['id']]);
                $fixed++;
            }
        }

        return $fixed;
    }

    private static function ensureColumn(PDO $pdo, string $column, string $definitionSql): void
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotions' AND COLUMN_NAME = :column"
        );
        $stmt->execute(['column' => $column]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $pdo->exec("ALTER TABLE promotions ADD COLUMN {$column} {$definitionSql}");
    }

    private static function widenVarcharColumn(PDO $pdo, string $column, int $minLength): void
    {
        $stmt = $pdo->prepare(
            "SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'promotions' AND COLUMN_NAME = :column
             LIMIT 1"
        );
        $stmt->execute(['column' => $column]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }

        $dataType = strtolower((string) ($row['data_type'] ?? ''));
        $maxLength = (int) ($row['max_length'] ?? 0);
        if ($dataType === 'varchar' && $maxLength >= $minLength) {
            return;
        }
        if ($dataType !== 'varchar' && $dataType !== '') {
            return;
        }

        $pdo->exec("ALTER TABLE promotions MODIFY COLUMN {$column} VARCHAR({$minLength}) NULL");
    }

    private static function normalizeToUploadsRelative(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $lower = strtolower($path);

        if (str_starts_with($lower, '/storage/uploads/')) {
            return '/uploads/' . ltrim(substr($path, strlen('/storage/uploads/')), '/');
        }
        if (str_starts_with($lower, '/admin/uploads/')) {
            return '/uploads/' . ltrim(substr($path, strlen('/admin/uploads/')), '/');
        }

        return $path;
    }

    private static function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $map = [
            'ı' => 'i', 'i̇' => 'i', 'ş' => 's', 'ğ' => 'g',
            'ü' => 'u', 'ö' => 'o', 'ç' => 'c', 'â' => 'a', 'î' => 'i', 'û' => 'u',
        ];
        $value = strtr($value, $map);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';

        return $value;
    }

    private static function libraryDir(): string
    {
        return self::rootPath() . '/storage/uploads/promotions';
    }

    private static function sourceDir(): string
    {
        return self::rootPath() . '/upload/bonuses';
    }

    private static function rootPath(): string
    {
        return defined('BASE_PATH') ? (string) BASE_PATH : str_replace('\\', '/', dirname(__DIR__, 2));
    }

    private static function shouldRunMaintenance(): bool
    {
        $marker = self::markerFile();
        if (!is_file($marker)) {
            return true;
        }

        return (time() - (int) @filemtime($marker)) > 600;
    }

    private static function markMaintenanceRun(): void
    {
        $marker = self::markerFile();
        $dir = dirname($marker);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($marker, (string) time());
    }

    private static function markerFile(): string
    {
        $storage = defined('STORAGE_PATH') ? (string) STORAGE_PATH : self::rootPath() . '/storage';

        return $storage . '/cache/promotion_media_guard.marker';
    }
}
