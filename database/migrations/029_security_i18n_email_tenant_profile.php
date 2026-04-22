<?php

/**
 * Segurança: tentativas de login; e-mail: fila; password reset; i18n: locale no user;
 * tenant: timezone, default_locale, currency.
 */
return [
    'up' => function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            email_hash CHAR(64) NULL,
            attempted_at DATETIME NOT NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            INDEX idx_ip_time (ip_address, attempted_at),
            INDEX idx_email_time (email_hash, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_token (token_hash),
            INDEX idx_user (user_id),
            CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS email_outbox (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NULL,
            to_email VARCHAR(255) NOT NULL,
            to_name VARCHAR(255) NULL,
            subject VARCHAR(500) NOT NULL,
            body_html MEDIUMTEXT NULL,
            body_text MEDIUMTEXT NULL,
            locale VARCHAR(5) NOT NULL DEFAULT 'es',
            status ENUM('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            INDEX idx_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        if (!$db->query("SHOW COLUMNS FROM users LIKE 'locale'")->fetch()) {
            $db->exec("ALTER TABLE users ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'es' AFTER status");
        }

        if (!$db->query("SHOW COLUMNS FROM tenants LIKE 'timezone'")->fetch()) {
            $db->exec("ALTER TABLE tenants ADD COLUMN timezone VARCHAR(64) NOT NULL DEFAULT 'Europe/Madrid' AFTER status");
        }
        if (!$db->query("SHOW COLUMNS FROM tenants LIKE 'default_locale'")->fetch()) {
            $db->exec("ALTER TABLE tenants ADD COLUMN default_locale VARCHAR(5) NOT NULL DEFAULT 'es' AFTER timezone");
        }
        if (!$db->query("SHOW COLUMNS FROM tenants LIKE 'currency'")->fetch()) {
            $db->exec("ALTER TABLE tenants ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'EUR' AFTER default_locale");
        }

        $db->exec("UPDATE users SET locale = 'es' WHERE locale IS NULL OR locale = ''");
        $db->exec("UPDATE tenants SET default_locale = 'es' WHERE default_locale IS NULL OR default_locale = ''");
    },

    'down' => function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS email_outbox');
        $db->exec('DROP TABLE IF EXISTS password_resets');
        $db->exec('DROP TABLE IF EXISTS login_attempts');
        if ($db->query("SHOW COLUMNS FROM users LIKE 'locale'")->fetch()) {
            $db->exec('ALTER TABLE users DROP COLUMN locale');
        }
        if ($db->query("SHOW COLUMNS FROM tenants LIKE 'currency'")->fetch()) {
            $db->exec('ALTER TABLE tenants DROP COLUMN currency');
        }
        if ($db->query("SHOW COLUMNS FROM tenants LIKE 'default_locale'")->fetch()) {
            $db->exec('ALTER TABLE tenants DROP COLUMN default_locale');
        }
        if ($db->query("SHOW COLUMNS FROM tenants LIKE 'timezone'")->fetch()) {
            $db->exec('ALTER TABLE tenants DROP COLUMN timezone');
        }
    },

    'description' => 'login_attempts, password_resets, email_outbox, users.locale, tenants timezone/locale/currency',
];
