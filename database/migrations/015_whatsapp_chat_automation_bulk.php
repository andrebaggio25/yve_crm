<?php

/**
 * WhatsApp (Evolution), conversas, mensagens, automacoes e fila de disparo em lote.
 */
return [
    'up' => function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS whatsapp_instances (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            name VARCHAR(120) NOT NULL DEFAULT 'Principal',
            instance_name VARCHAR(120) NOT NULL,
            api_url VARCHAR(512) NOT NULL DEFAULT '',
            api_key VARCHAR(512) NOT NULL DEFAULT '',
            status ENUM('connected','disconnected','pending') NOT NULL DEFAULT 'pending',
            phone_number VARCHAR(40) NULL,
            webhook_token VARCHAR(64) NOT NULL,
            settings_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_wa_webhook_token (webhook_token),
            INDEX idx_wa_tenant (tenant_id),
            CONSTRAINT fk_wa_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS conversations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            lead_id INT NULL,
            whatsapp_instance_id INT UNSIGNED NOT NULL,
            contact_phone VARCHAR(40) NOT NULL,
            contact_name VARCHAR(255) NULL,
            contact_push_name VARCHAR(255) NULL,
            status ENUM('open','closed','pending') NOT NULL DEFAULT 'open',
            assigned_user_id INT NULL,
            last_message_at DATETIME NULL,
            last_message_preview VARCHAR(512) NULL,
            unread_count INT UNSIGNED NOT NULL DEFAULT 0,
            is_bot_active TINYINT(1) NOT NULL DEFAULT 0,
            metadata_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_conv_tenant_last (tenant_id, last_message_at),
            INDEX idx_conv_lead (lead_id),
            INDEX idx_conv_phone (tenant_id, contact_phone),
            CONSTRAINT fk_conv_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_conv_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
            CONSTRAINT fk_conv_wa FOREIGN KEY (whatsapp_instance_id) REFERENCES whatsapp_instances(id) ON DELETE CASCADE,
            CONSTRAINT fk_conv_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            conversation_id INT UNSIGNED NOT NULL,
            whatsapp_message_id VARCHAR(120) NULL,
            direction ENUM('inbound','outbound') NOT NULL,
            sender_type ENUM('contact','user','bot','system') NOT NULL DEFAULT 'contact',
            sender_id INT NULL,
            type ENUM('text','image','audio','video','document','sticker','location','contact','unknown') NOT NULL DEFAULT 'text',
            content TEXT NULL,
            media_url VARCHAR(1024) NULL,
            media_mime_type VARCHAR(120) NULL,
            media_filename VARCHAR(255) NULL,
            status ENUM('pending','sent','delivered','read','failed') NOT NULL DEFAULT 'sent',
            error_message VARCHAR(512) NULL,
            metadata_json JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_msg_conv (conversation_id, id),
            INDEX idx_msg_tenant (tenant_id),
            CONSTRAINT fk_msg_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS automation_rules (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            pipeline_id INT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            trigger_event VARCHAR(80) NOT NULL,
            trigger_config_json JSON NULL,
            conditions_json JSON NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            priority INT NOT NULL DEFAULT 0,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_auto_tenant (tenant_id),
            INDEX idx_auto_trigger (tenant_id, trigger_event),
            CONSTRAINT fk_auto_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_auto_pipeline FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE SET NULL,
            CONSTRAINT fk_auto_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS automation_actions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            automation_rule_id INT UNSIGNED NOT NULL,
            action_type VARCHAR(80) NOT NULL,
            action_config_json JSON NULL,
            position INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_auto_act_rule (automation_rule_id),
            CONSTRAINT fk_auto_act_rule FOREIGN KEY (automation_rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS automation_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            automation_rule_id INT UNSIGNED NOT NULL,
            lead_id INT NULL,
            trigger_data_json JSON NULL,
            actions_executed_json JSON NULL,
            status ENUM('ok','partial','error') NOT NULL DEFAULT 'ok',
            error_message TEXT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_alog_tenant (tenant_id),
            CONSTRAINT fk_alog_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_alog_rule FOREIGN KEY (automation_rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
            CONSTRAINT fk_alog_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS bulk_send_jobs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            user_id INT NOT NULL,
            template_id INT NULL,
            interval_seconds INT UNSIGNED NOT NULL DEFAULT 5,
            total INT UNSIGNED NOT NULL DEFAULT 0,
            processed INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('pending','running','done','failed','cancelled') NOT NULL DEFAULT 'pending',
            payload_json JSON NULL,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bulk_tenant (tenant_id),
            CONSTRAINT fk_bulk_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_bulk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_bulk_tpl FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS bulk_send_jobs');
        $db->exec('DROP TABLE IF EXISTS automation_logs');
        $db->exec('DROP TABLE IF EXISTS automation_actions');
        $db->exec('DROP TABLE IF EXISTS automation_rules');
        $db->exec('DROP TABLE IF EXISTS messages');
        $db->exec('DROP TABLE IF EXISTS conversations');
        $db->exec('DROP TABLE IF EXISTS whatsapp_instances');
    },

    'description' => 'WhatsApp Evolution, inbox, mensagens, automacoes e jobs de disparo em lote',
];
