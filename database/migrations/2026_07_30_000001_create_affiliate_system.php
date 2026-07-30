<?php

declare(strict_types=1);

/**
 * Profesyonel Ortaklık (Affiliate) Sistemi tabloları.
 *
 * affiliates          — Ortak hesap ve profil bilgileri
 * affiliate_commission_plans — Komisyon plan tanımları (CPA / RevShare / Hybrid)
 * affiliate_commissions      — Kazanılan komisyon kayıtları
 * affiliate_payouts          — Ödeme talepleri ve geçmişi
 * affiliate_clicks           — Tıklanma/ziyaret takibi
 * affiliate_materials        — Pazarlama banner/link materyalleri
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliate_commission_plans (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name            VARCHAR(190) NOT NULL,
            plan_type       VARCHAR(20) NOT NULL DEFAULT 'revshare' COMMENT 'revshare | cpa | hybrid',
            revshare_rate   DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT '% olarak (örn 25.00 = %25)',
            cpa_amount      DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Sabit CPA ödemesi',
            min_deposit     DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'CPA için minimum yatırım',
            wagering_requirement DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Çevrim şartı katı (örn 1.00 = 1x)',
            is_default      TINYINT(1) NOT NULL DEFAULT 0,
            is_active       TINYINT(1) NOT NULL DEFAULT 1,
            description     TEXT NULL,
            created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aff_commission_plans_active (is_active),
            KEY idx_aff_commission_plans_default (is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliates (
            id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id             INT UNSIGNED NULL COMMENT 'Varsa mevcut kullanıcı bağlantısı',
            company_name        VARCHAR(190) NOT NULL DEFAULT '',
            full_name           VARCHAR(190) NOT NULL DEFAULT '',
            email               VARCHAR(190) NOT NULL,
            phone               VARCHAR(40) NOT NULL DEFAULT '',
            country             VARCHAR(100) NOT NULL DEFAULT '',
            city                VARCHAR(100) NOT NULL DEFAULT '',
            website             VARCHAR(500) NOT NULL DEFAULT '',
            referral_code       VARCHAR(50) NOT NULL,
            password_hash       VARCHAR(255) NOT NULL,
            commission_plan_id  INT UNSIGNED NULL,
            status              VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | active | suspended | rejected',
            balance             DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Güncel kazanç bakiyesi',
            total_earned        DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_paid          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            payment_method      VARCHAR(40) NOT NULL DEFAULT 'bank' COMMENT 'bank | crypto | paypal',
            payment_details     TEXT NULL COMMENT '{iban, banka_adı, crypto_adres, ...}',
            notes               TEXT NULL COMMENT 'Admin notları',
            approved_at         TIMESTAMP NULL,
            last_login_at       TIMESTAMP NULL,
            created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_aff_referral_code (referral_code),
            UNIQUE KEY uniq_aff_email (email),
            KEY idx_aff_status (status),
            KEY idx_aff_user_id (user_id),
            KEY idx_aff_commission_plan (commission_plan_id),
            CONSTRAINT fk_aff_commission_plan FOREIGN KEY (commission_plan_id)
                REFERENCES affiliate_commission_plans(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliate_commissions (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id    INT UNSIGNED NOT NULL,
            user_id         INT NOT NULL COMMENT 'Yönlendirilen oyuncu',
            commission_type VARCHAR(20) NOT NULL COMMENT 'revshare | cpa | hybrid',
            amount          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            currency        VARCHAR(8) NOT NULL DEFAULT 'TRY',
            status          VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | paid | cancelled',
            description     VARCHAR(500) NOT NULL DEFAULT '',
            source          VARCHAR(40) NOT NULL DEFAULT 'deposit' COMMENT 'deposit | game_bet | registration | manual',
            source_id       BIGINT UNSIGNED NULL COMMENT 'İlgili işlem ID (deposit_id, bet_id vs)',
            period_start    DATE NULL,
            period_end      DATE NULL,
            approved_at     TIMESTAMP NULL,
            paid_at         TIMESTAMP NULL,
            created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aff_comm_affiliate (affiliate_id),
            KEY idx_aff_comm_user (user_id),
            KEY idx_aff_comm_status (status),
            KEY idx_aff_comm_type (commission_type),
            KEY idx_aff_comm_created (created_at),
            CONSTRAINT fk_aff_comm_affiliate FOREIGN KEY (affiliate_id)
                REFERENCES affiliates(id) ON DELETE CASCADE,
            CONSTRAINT fk_aff_comm_user FOREIGN KEY (user_id)
                REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliate_payouts (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id    INT UNSIGNED NOT NULL,
            amount          DECIMAL(15,2) NOT NULL,
            currency        VARCHAR(8) NOT NULL DEFAULT 'TRY',
            method          VARCHAR(40) NOT NULL DEFAULT 'bank' COMMENT 'bank | crypto | paypal',
            method_details  TEXT NULL,
            status          VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | processing | completed | rejected | cancelled',
            admin_notes     VARCHAR(500) NOT NULL DEFAULT '',
            requested_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at    TIMESTAMP NULL,
            processed_by    INT UNSIGNED NULL COMMENT 'Admin kullanıcı ID',
            created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aff_payout_affiliate (affiliate_id),
            KEY idx_aff_payout_status (status),
            KEY idx_aff_payout_requested (requested_at),
            CONSTRAINT fk_aff_payout_affiliate FOREIGN KEY (affiliate_id)
                REFERENCES affiliates(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliate_clicks (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            affiliate_id    INT UNSIGNED NOT NULL,
            referral_code   VARCHAR(50) NOT NULL,
            landing_url     VARCHAR(500) NOT NULL DEFAULT '',
            ip_address      VARCHAR(45) NOT NULL DEFAULT '',
            user_agent      VARCHAR(500) NOT NULL DEFAULT '',
            referrer_url    VARCHAR(500) NOT NULL DEFAULT '',
            country         VARCHAR(8) NOT NULL DEFAULT '',
            device_type     VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'desktop | mobile | tablet',
            converted       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Kayıt yapıldı mı',
            converted_user_id INT NULL,
            created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aff_click_affiliate (affiliate_id),
            KEY idx_aff_click_code (referral_code),
            KEY idx_aff_click_created (created_at),
            KEY idx_aff_click_converted (converted),
            CONSTRAINT fk_aff_click_affiliate FOREIGN KEY (affiliate_id)
                REFERENCES affiliates(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS affiliate_materials (
            id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title           VARCHAR(190) NOT NULL,
            material_type   VARCHAR(20) NOT NULL DEFAULT 'banner' COMMENT 'banner | text_link | landing_page | promo_code',
            file_url        VARCHAR(500) NOT NULL DEFAULT '',
            file_path       VARCHAR(500) NOT NULL DEFAULT '',
            width           INT UNSIGNED NOT NULL DEFAULT 0,
            height          INT UNSIGNED NOT NULL DEFAULT 0,
            target_url      VARCHAR(500) NOT NULL DEFAULT '',
            language        VARCHAR(8) NOT NULL DEFAULT 'tr',
            is_active       TINYINT(1) NOT NULL DEFAULT 1,
            sort_order      INT NOT NULL DEFAULT 0,
            created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_aff_mat_type (material_type),
            KEY idx_aff_mat_active (is_active),
            KEY idx_aff_mat_lang (language)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
