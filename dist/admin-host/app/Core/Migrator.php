<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migrator
{
    public function __construct(private PDO $pdo)
    {
    }

    public function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(191) NOT NULL UNIQUE,
                executed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function run(string $directory): void
    {
        $this->ensureTable();
        foreach (glob(rtrim($directory, '/\\') . '/*.php') ?: [] as $file) {
            $name = basename($file);
            if ($this->hasRun($name)) {
                continue;
            }
            $migration = require $file;
            if (is_callable($migration)) {
                $migration($this->pdo);
                $stmt = $this->pdo->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
                $stmt->execute(['migration' => $name]);
            }
        }
    }

    private function hasRun(string $name): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM migrations WHERE migration = :migration LIMIT 1');
        $stmt->execute(['migration' => $name]);

        return (bool) $stmt->fetchColumn();
    }
}

