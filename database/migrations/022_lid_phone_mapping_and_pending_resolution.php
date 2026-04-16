<?php

/**
 * Mapeamento LID <-> telefone e flag de triagem no Kanban (Leads de Entrada).
 */
return [
    'up' => function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS lid_phone_mapping (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            lid VARCHAR(80) NOT NULL,
            phone_normalized VARCHAR(50) NOT NULL,
            phone_jid VARCHAR(80) NULL,
            source VARCHAR(40) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_lid_tenant (tenant_id, lid),
            INDEX idx_phone (tenant_id, phone_normalized),
            CONSTRAINT fk_lidmap_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND COLUMN_NAME = 'pending_identity_resolution'");
        $exists = (int) $stmt->fetchColumn() > 0;
        if (!$exists) {
            $db->exec('ALTER TABLE leads ADD COLUMN pending_identity_resolution TINYINT(1) NOT NULL DEFAULT 0 AFTER metadata_json');
        }
    },

    'down' => function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS lid_phone_mapping');
        try {
            $db->exec('ALTER TABLE leads DROP COLUMN pending_identity_resolution');
        } catch (\Throwable $e) {
            // ignora
        }
    },

    'description' => 'Tabela lid_phone_mapping e coluna pending_identity_resolution em leads',
];
