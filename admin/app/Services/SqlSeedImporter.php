<?php

declare(strict_types=1);

/**
 * phpMyAdmin SQL dump importer for first-time admin installation.
 */
final class SqlSeedImporter
{
    public const SEED_FILENAME = 'metropolcasino.sql';

    /** Minimum complete dump size (~20 MB); truncated uploads are typically ~8 MB. */
    private const MIN_COMPLETE_BYTES = 15_000_000;

    public static function seedPath(string $root): string
    {
        $resolved = self::resolveSeedPath($root);

        return $resolved ?? self::primarySeedPath($root);
    }

    public static function isAvailable(string $root): bool
    {
        return self::resolveSeedPath($root) !== null;
    }

    public static function humanSize(string $root): string
    {
        $path = self::resolveSeedPath($root) ?? self::primarySeedPath($root);
        if (!is_file($path)) {
            return '0 B';
        }
        $bytes = (int) filesize($path);
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * @return string|null Error message when invalid
     */
    public static function validateSeedFile(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return 'Seed SQL dosyası okunamıyor: ' . $path;
        }

        $bytes = (int) filesize($path);
        if ($bytes <= 0) {
            return 'Seed SQL dosyası boş: ' . $path;
        }
        if ($bytes < self::MIN_COMPLETE_BYTES) {
            return 'Seed SQL dosyası çok küçük (' . self::formatBytes($bytes) . '). Tam dump ~22 MB olmalı — zip yüklemesi yarım kalmış olabilir.';
        }

        try {
            self::assertStructureValid($path);
        } catch (RuntimeException $exception) {
            return $exception->getMessage();
        }

        return null;
    }

    /**
     * @throws RuntimeException
     */
    public static function assertDatabaseEmpty(PDO $pdo): void
    {
        $count = (int) $pdo->query(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchColumn();
        if ($count > 0) {
            throw new RuntimeException(
                'Seed SQL yüklemek için veritabanı boş olmalıdır (' . $count . ' tablo bulundu). '
                . 'Boş bir veritabanı oluşturun veya "Mevcut veritabanını kullan" seçeneğini işaretleyin.'
            );
        }
    }

    /**
     * @return array{statements: int, skipped: int, elapsed_ms: int}
     */
    public static function import(PDO $pdo, string $sqlFile): array
    {
        if (!is_readable($sqlFile)) {
            throw new RuntimeException('Seed SQL dosyası okunamıyor: ' . $sqlFile);
        }

        $validationError = self::validateSeedFile($sqlFile);
        if ($validationError !== null) {
            throw new RuntimeException($validationError);
        }

        self::assertStructureValid($sqlFile);

        @set_time_limit(0);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        $started = microtime(true);
        $statements = 0;
        $skipped = 0;

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"');
        $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

        $handle = fopen($sqlFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Seed SQL dosyası açılamadı.');
        }

        $buffer = '';
        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                if (str_starts_with($trimmed, '--')) {
                    continue;
                }
                if (str_starts_with($trimmed, '/*') && !str_contains($trimmed, '*/')) {
                    continue;
                }

                $buffer .= $line;
                if (!str_ends_with(rtrim($line), ';')) {
                    continue;
                }

                $statement = trim($buffer);
                $buffer = '';
                if ($statement === '' || $statement === ';') {
                    continue;
                }

                $upper = strtoupper(ltrim($statement));
                if (
                    str_starts_with($upper, 'START TRANSACTION')
                    || $upper === 'COMMIT'
                    || str_starts_with($upper, 'SET AUTOCOMMIT')
                ) {
                    $skipped++;
                    continue;
                }

                try {
                    $statement = self::normalizeStatement($statement);
                    $pdo->exec($statement);
                    $statements++;
                } catch (PDOException $exception) {
                    $message = $exception->getMessage();
                    // Idempotent re-run safety for indexes/constraints that already exist.
                    if (
                        str_contains($message, 'Duplicate key name')
                        || str_contains($message, 'already exists')
                        || str_contains($message, 'Duplicate entry')
                    ) {
                        $skipped++;
                        continue;
                    }
                    throw new RuntimeException(
                        'SQL import hatası (statement #' . ($statements + $skipped + 1) . '): ' . $message,
                        0,
                        $exception
                    );
                }
            }
        } finally {
            fclose($handle);
        }

        if (trim($buffer) !== '') {
            throw self::incompleteSeedException($sqlFile, trim($buffer));
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        return [
            'statements' => $statements,
            'skipped' => $skipped,
            'elapsed_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private static function primarySeedPath(string $root): string
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');

        return $root . '/database/seed/' . self::SEED_FILENAME;
    }

    /**
     * @return list<string>
     */
    private static function seedCandidates(string $root): array
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $candidates = [$root . '/database/seed/' . self::SEED_FILENAME];

        $parent = dirname($root);
        if ($parent !== $root) {
            $candidates[] = $parent . '/database/seed/' . self::SEED_FILENAME;
        }

        return array_values(array_unique($candidates));
    }

    private static function resolveSeedPath(string $root): ?string
    {
        foreach (self::seedCandidates($root) as $path) {
            if (!is_file($path) || !is_readable($path) || filesize($path) <= 0) {
                continue;
            }

            try {
                self::assertStructureValid($path);

                return $path;
            } catch (RuntimeException) {
                continue;
            }
        }

        return null;
    }

    /**
     * @throws RuntimeException
     */
    private static function assertStructureValid(string $sqlFile): void
    {
        $scan = self::scanStatements($sqlFile);
        if ($scan['leftover'] === '') {
            return;
        }

        throw self::incompleteSeedException($sqlFile, $scan['leftover']);
    }

    /**
     * @return array{leftover: string, statements: int}
     */
    private static function scanStatements(string $sqlFile): array
    {
        $handle = fopen($sqlFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Seed SQL dosyası açılamadı: ' . $sqlFile);
        }

        $buffer = '';
        $statements = 0;

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                if (str_starts_with($trimmed, '--')) {
                    continue;
                }
                if (str_starts_with($trimmed, '/*') && !str_contains($trimmed, '*/')) {
                    continue;
                }

                $buffer .= $line;
                if (!str_ends_with(rtrim($line), ';')) {
                    continue;
                }

                $statement = trim($buffer);
                $buffer = '';
                if ($statement === '' || $statement === ';') {
                    continue;
                }

                $statements++;
            }
        } finally {
            fclose($handle);
        }

        return [
            'leftover' => trim($buffer),
            'statements' => $statements,
        ];
    }

    private static function incompleteSeedException(string $sqlFile, string $leftover): RuntimeException
    {
        $bytes = is_file($sqlFile) ? (int) filesize($sqlFile) : 0;
        $sizeHint = self::formatBytes($bytes);
        $tail = strlen($leftover) > 180 ? '...' . substr($leftover, -180) : $leftover;
        $hint = $bytes > 0 && $bytes < self::MIN_COMPLETE_BYTES
            ? ' Dosya yüklemesi yarım kalmış olabilir (beklenen ~22 MB, mevcut ' . $sizeHint . ').'
            : ' phpMyAdmin dump dosyasını yeniden dışa aktarıp tam yükleyin.';

        return new RuntimeException(
            'Seed SQL dosyası eksik/bozuk görünüyor (son ifade tamamlanmadı): '
            . $sqlFile
            . ' (' . $sizeHint . ').'
            . $hint
            . ' Kalan parça: '
            . $tail
        );
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * MariaDB / MySQL 5.7 uyumluluğu: MySQL 8.0 dump collation'larını dönüştürür.
     */
    private static function normalizeStatement(string $statement): string
    {
        static $replacements = [
            'utf8mb4_0900_ai_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_as_ci' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_as_cs' => 'utf8mb4_unicode_ci',
            'utf8mb4_0900_bin' => 'utf8mb4_bin',
            'utf8mb3_unicode_ci' => 'utf8mb4_unicode_ci',
            'utf8mb3_general_ci' => 'utf8mb4_unicode_ci',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $statement);
    }
}
