<?php

declare(strict_types=1);


/**
 * Çekirdek üye ve içerik tabloları — bağımsız admin kurulumu.
 */
return static function (PDO $pdo): void {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL DEFAULT '',
            surname VARCHAR(100) NOT NULL DEFAULT '',
            username VARCHAR(60) NOT NULL,
            email VARCHAR(190) NOT NULL,
            identity_number VARCHAR(32) NULL,
            gender VARCHAR(16) NULL,
            dob DATE NULL,
            phone VARCHAR(32) NULL,
            city VARCHAR(100) NULL,
            country VARCHAR(8) NOT NULL DEFAULT 'TR',
            address VARCHAR(500) NULL,
            password VARCHAR(255) NOT NULL,
            bonus_code VARCHAR(60) NULL,
            referral_code VARCHAR(40) NULL,
            referred_by_affiliate_id INT UNSIGNED NULL,
            balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            bonus_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            banned TINYINT(1) NOT NULL DEFAULT 0,
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            verify_token VARCHAR(128) NULL,
            last_login_at DATETIME NULL,
            password_changed_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_users_username (username),
            UNIQUE KEY uniq_users_email (email),
            UNIQUE KEY uniq_users_referral_code (referral_code),
            KEY idx_users_phone (phone),
            KEY idx_users_verified (is_verified),
            KEY idx_users_banned (banned),
            KEY idx_users_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS kyc_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            username VARCHAR(60) NULL,
            document_type VARCHAR(60) NULL,
            document_path VARCHAR(700) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            note VARCHAR(500) NULL,
            reviewed_by VARCHAR(100) NULL,
            submitted_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_kyc_user (user_id),
            KEY idx_kyc_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_account_freeze (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            reason VARCHAR(255) NULL,
            frozen_by VARCHAR(100) NULL,
            frozen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_account_freeze_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_active_bonuses (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            promotion_id INT UNSIGNED NULL,
            name VARCHAR(190) NOT NULL DEFAULT '',
            category VARCHAR(60) NULL,
            initial_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            current_bonus_balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            wagering_requirement DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            wagering_target DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            total_bet_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            is_complete TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            granted_at DATETIME NULL,
            deadline DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_active_bonus_user (user_id),
            KEY idx_active_bonus_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bonus_claim_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            promotion_id INT UNSIGNED NULL,
            bonus_name VARCHAR(190) NOT NULL DEFAULT '',
            category VARCHAR(60) NULL,
            promotion_type VARCHAR(60) NULL,
            requested_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            wagering_multiplier DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            user_message VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            processed_by VARCHAR(100) NULL,
            processed_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_bonus_claim_user (user_id),
            KEY idx_bonus_claim_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promotions (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            long_description MEDIUMTEXT NULL,
            type VARCHAR(60) NULL,
            category VARCHAR(60) NULL,
            terms MEDIUMTEXT NULL,
            general_rules MEDIUMTEXT NULL,
            image_url VARCHAR(700) NULL,
            bonus_type VARCHAR(60) NULL,
            bonus_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            wagering_multiplier DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            sort_order INT NOT NULL DEFAULT 0,
            start_date DATETIME NULL,
            end_date DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_promotions_status (status),
            KEY idx_promotions_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promocodes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            kod VARCHAR(60) NOT NULL,
            miktar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            son_gecerlilik_tarihi DATE NULL,
            kullanim_limiti INT NOT NULL DEFAULT 0,
            mevcut_kullanim INT NOT NULL DEFAULT 0,
            aciklama VARCHAR(255) NULL,
            durum TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_promocodes_kod (kod)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS promocode_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            promocode_id INT UNSIGNED NULL,
            promocode_code VARCHAR(60) NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            user_message VARCHAR(500) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_promocode_requests_user (user_id),
            KEY idx_promocode_requests_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS announcements (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            description TEXT NULL,
            type VARCHAR(60) NULL,
            icon_type VARCHAR(60) NULL,
            priority INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            start_date DATETIME NULL,
            end_date DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_announcements_active (is_active, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS call_me_requests (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            full_name VARCHAR(190) NULL,
            username VARCHAR(60) NULL,
            phone VARCHAR(32) NULL,
            email VARCHAR(190) NULL,
            preferred_time VARCHAR(60) NULL,
            message VARCHAR(1000) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            ip_address VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_call_me_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS member_inbox_messages (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NULL,
            title VARCHAR(190) NOT NULL,
            body MEDIUMTEXT NULL,
            link_url VARCHAR(700) NULL,
            priority INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            starts_at DATETIME NULL,
            ends_at DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_inbox_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS auth_sliders (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NULL,
            screen ENUM('login','register') NOT NULL,
            surface ENUM('desktop','mobile') NOT NULL,
            media_path VARCHAR(700) NOT NULL,
            media_alt VARCHAR(255) NULL,
            link_url VARCHAR(700) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            start_date DATETIME NULL,
            end_date DATETIME NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_auth_sliders_lookup (screen, surface, is_active, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
};
