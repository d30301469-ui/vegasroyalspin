<?php

declare(strict_types=1);

/**
 * BGaming kampanya/freespin tabloları daha önce yalnızca runtime CREATE TABLE ile
 * oluşuyordu. Bu migration şemayı resmileştirir ve kullanıcıya yapılan her freespin
 * atamasını tek satırda (remote_issue_id) takip edilebilir hale getirir.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bgaming_campaigns (
            id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_code        VARCHAR(190) NOT NULL,
            title                VARCHAR(190) NOT NULL,
            campaign_type        VARCHAR(40) NOT NULL DEFAULT 'freespin',
            game_identifier      VARCHAR(120) NULL,
            vendor               VARCHAR(100) NOT NULL DEFAULT 'bgaming',
            source               VARCHAR(20) NOT NULL DEFAULT 'admin',
            currency_code        VARCHAR(8) NULL,
            freespins_per_player INT NOT NULL DEFAULT 0,
            bet_level            INT NOT NULL DEFAULT 0,
            promo_amount         DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            wagering_multiplier  DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            begins_at            BIGINT NULL,
            expires_at           BIGINT NULL,
            active               TINYINT(1) NOT NULL DEFAULT 1,
            status               VARCHAR(40) NOT NULL DEFAULT 'active',
            payload              JSON NULL,
            created_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at           TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bgaming_campaign_code (campaign_code),
            KEY idx_bgaming_campaign_type (campaign_type),
            KEY idx_bgaming_campaign_active (active),
            KEY idx_bgaming_campaign_source (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bgaming_campaign_players (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_code   VARCHAR(190) NOT NULL,
            user_id         INT NOT NULL,
            bonus_id        INT NULL,
            remote_issue_id VARCHAR(190) NULL,
            status          VARCHAR(40) NOT NULL DEFAULT 'pending',
            remote_status   VARCHAR(40) NOT NULL DEFAULT '',
            freespins_total INT NOT NULL DEFAULT 0,
            freespins_done  INT NOT NULL DEFAULT 0,
            win_amount      DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            valid_since     BIGINT NULL,
            valid_until     BIGINT NULL,
            last_error      VARCHAR(500) NULL,
            issued_at       DATETIME NULL,
            synced_at       DATETIME NULL,
            payload         JSON NULL,
            created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_bgaming_campaign_player (campaign_code, user_id),
            UNIQUE KEY uniq_bgaming_campaign_player_issue (remote_issue_id),
            KEY idx_bgaming_campaign_player_user (user_id),
            KEY idx_bgaming_campaign_player_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);

        return (int) $stmt->fetchColumn() > 0;
    };

    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND INDEX_NAME = :index'
        );
        $stmt->execute(['table' => $table, 'index' => $index]);

        return (int) $stmt->fetchColumn() > 0;
    };

    $columns = [
        'bgaming_campaigns.source' => "ALTER TABLE bgaming_campaigns ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER vendor",
        'bgaming_campaigns.bet_level' => 'ALTER TABLE bgaming_campaigns ADD COLUMN bet_level INT NOT NULL DEFAULT 0 AFTER freespins_per_player',
        'bgaming_campaign_players.remote_issue_id' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN remote_issue_id VARCHAR(190) NULL AFTER bonus_id',
        'bgaming_campaign_players.remote_status' => "ALTER TABLE bgaming_campaign_players ADD COLUMN remote_status VARCHAR(40) NOT NULL DEFAULT '' AFTER status",
        'bgaming_campaign_players.freespins_total' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN freespins_total INT NOT NULL DEFAULT 0 AFTER remote_status',
        'bgaming_campaign_players.freespins_done' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN freespins_done INT NOT NULL DEFAULT 0 AFTER freespins_total',
        'bgaming_campaign_players.win_amount' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN win_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER freespins_done',
        'bgaming_campaign_players.valid_since' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN valid_since BIGINT NULL AFTER win_amount',
        'bgaming_campaign_players.valid_until' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN valid_until BIGINT NULL AFTER valid_since',
        'bgaming_campaign_players.last_error' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN last_error VARCHAR(500) NULL AFTER valid_until',
        'bgaming_campaign_players.issued_at' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN issued_at DATETIME NULL AFTER last_error',
        'bgaming_campaign_players.synced_at' => 'ALTER TABLE bgaming_campaign_players ADD COLUMN synced_at DATETIME NULL AFTER issued_at',
    ];

    foreach ($columns as $key => $sql) {
        [$table, $column] = explode('.', $key, 2);
        if (!$columnExists($table, $column)) {
            $pdo->exec($sql);
        }
    }

    // Panelde oluşturulmamış (wallet/issue callback ile gelen) kampanyaları ayır.
    $pdo->exec(
        "UPDATE bgaming_campaigns
         SET source = 'provider'
         WHERE source = 'admin'
           AND payload IS NOT NULL
           AND JSON_EXTRACT(payload, '$.wallet_issue') IS NOT NULL"
    );

    // Atama satırlarındaki remote issue kimliğini payload'dan kolona taşı.
    $pdo->exec(
        "UPDATE bgaming_campaign_players
         SET remote_issue_id = JSON_UNQUOTE(JSON_EXTRACT(payload, '$.remote_issue_id'))
         WHERE remote_issue_id IS NULL
           AND payload IS NOT NULL
           AND JSON_UNQUOTE(JSON_EXTRACT(payload, '$.remote_issue_id')) IS NOT NULL"
    );

    // Eski kurulumda her issue ayrıca kampanya kodu olarak yazılıyordu. Bu satırların
    // durumunu asıl atama satırına taşı, sonra kopyayı listelerden düşür.
    $pdo->exec(
        "UPDATE bgaming_campaign_players main
         JOIN bgaming_campaign_players shadow
           ON shadow.user_id = main.user_id
          AND shadow.campaign_code = main.remote_issue_id
          AND shadow.id <> main.id
         SET main.status = shadow.status,
             main.remote_status = shadow.status
         WHERE main.remote_issue_id IS NOT NULL
           AND main.status IN ('issued_remote', 'assigned', 'pending')"
    );

    $pdo->exec(
        "UPDATE bgaming_campaign_players shadow
         JOIN bgaming_campaign_players main
           ON main.user_id = shadow.user_id
          AND main.remote_issue_id = shadow.campaign_code
          AND main.id <> shadow.id
         SET shadow.status = 'superseded'"
    );

    // Kalan issue kodlu satırlar (panel dışı issue) kendi kodlarını issue kimliği olarak kullanır.
    $pdo->exec(
        "UPDATE bgaming_campaign_players cp
         JOIN bgaming_campaigns c ON c.campaign_code = cp.campaign_code
         SET cp.remote_issue_id = cp.campaign_code
         WHERE cp.remote_issue_id IS NULL
           AND cp.status <> 'superseded'
           AND c.source = 'provider'"
    );

    $pdo->exec("UPDATE bgaming_campaign_players SET status = 'active' WHERE status = 'issued_remote'");
    $pdo->exec("UPDATE bgaming_campaign_players SET status = 'pending' WHERE status = 'assigned'");
    $pdo->exec(
        "UPDATE bgaming_campaign_players cp
         JOIN bgaming_campaigns c ON c.campaign_code = cp.campaign_code
         SET cp.freespins_total = c.freespins_per_player
         WHERE cp.freespins_total = 0 AND c.campaign_type = 'freespin'"
    );

    if (!$indexExists('bgaming_campaign_players', 'uniq_bgaming_campaign_player_issue')
        && !$indexExists('bgaming_campaign_players', 'idx_bgaming_campaign_player_issue')) {
        try {
            $pdo->exec(
                'ALTER TABLE bgaming_campaign_players
                 ADD UNIQUE KEY uniq_bgaming_campaign_player_issue (remote_issue_id)'
            );
        } catch (Throwable) {
            // Eski veride tekrarlayan issue kimliği varsa tekillik zorlanamaz; arama indeksi yeterli.
            $pdo->exec(
                'ALTER TABLE bgaming_campaign_players
                 ADD KEY idx_bgaming_campaign_player_issue (remote_issue_id)'
            );
        }
    }
};
