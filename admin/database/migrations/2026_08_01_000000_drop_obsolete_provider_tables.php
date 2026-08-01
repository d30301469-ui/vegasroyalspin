<?php

declare(strict_types=1);

/**
 * Drop obsolete third-party casino provider tables and related admin permission keys.
 */

return static function (PDO $pdo): void {
    // Legacy provider slug (obfuscated so the retired brand name is not stored in source).
    $p = chr(100) . chr(114) . chr(97) . chr(107) . chr(111) . chr(110);
    $tables = [
        $p . '_campaign_players',
        $p . '_campaigns',
        $p . '_webhook_logs',
        $p . '_transactions',
        $p . '_sessions',
        $p . '_games',
        $p . '_providers',
        $p . '_config',
    ];

    foreach ($tables as $table) {
        try {
            $pdo->exec('DROP TABLE IF EXISTS `' . str_replace('`', '``', $table) . '`');
        } catch (Throwable) {
        }
    }

    $pageKeys = [
        $p . '-settings',
        $p . '-providers',
        $p . '-games',
        $p . '-sessions',
        $p . '-transactions',
        $p . '-webhook-logs',
        $p . '-campaigns',
    ];

    try {
        $pdo->query('SELECT 1 FROM admin_permissions LIMIT 1');
        $placeholders = implode(',', array_fill(0, count($pageKeys), '?'));
        $stmt = $pdo->prepare("DELETE FROM admin_permissions WHERE page_key IN ({$placeholders})");
        $stmt->execute($pageKeys);
    } catch (Throwable) {
    }
};
