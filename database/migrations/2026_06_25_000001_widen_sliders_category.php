<?php

declare(strict_types=1);

/**
 * sliders.category: legacy ENUM('home','live_casino','slots') → VARCHAR(80) (bgaming vb.).
 */
return static function (PDO $pdo): void {
    $stmt = $pdo->query(
        "SELECT DATA_TYPE AS data_type, COLUMN_TYPE AS column_type
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'sliders'
           AND COLUMN_NAME = 'category'
         LIMIT 1"
    );
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if (!is_array($row)) {
        return;
    }

    $dataType = strtolower((string) ($row['data_type'] ?? ''));
    if ($dataType === 'enum') {
        try {
            $pdo->exec(
                "ALTER TABLE sliders
                 MODIFY COLUMN category VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home'"
            );
        } catch (Throwable) {
            $columnType = (string) ($row['column_type'] ?? '');
            if (preg_match("/^enum\\((.+)\\)$/i", $columnType, $matches) === 1) {
                $values = [];
                foreach (str_getcsv($matches[1], ',', "'") as $raw) {
                    $value = trim((string) $raw, " '\"");
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }
                if (!in_array('bgaming', $values, true)) {
                    $values[] = 'bgaming';
                    $enumSql = implode(',', array_map(
                        static fn (string $v): string => "'" . str_replace("'", "''", $v) . "'",
                        $values
                    ));
                    $pdo->exec(
                        "ALTER TABLE sliders
                         MODIFY COLUMN category ENUM({$enumSql}) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'home'"
                    );
                }
            }
        }
    }
};
