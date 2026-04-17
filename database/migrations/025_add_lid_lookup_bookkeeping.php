<?php

/**
 * Bookkeeping para self-healing de vinculo LID <-> telefone.
 * Permite que o worker tente N vezes, com backoff exponencial,
 * sem ficar consultando a Evolution repetidamente para o mesmo lead.
 */
return [
    'up' => function (PDO $db): void {
        $hasColumn = static function (PDO $db, string $table, string $column): bool {
            $stmt = $db->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = '" . $table . "'
                   AND COLUMN_NAME = '" . $column . "'"
            );

            return (int) $stmt->fetchColumn() > 0;
        };

        $hasIndex = static function (PDO $db, string $table, string $index): bool {
            $stmt = $db->query(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = '" . $table . "'
                   AND INDEX_NAME = '" . $index . "'"
            );

            return (int) $stmt->fetchColumn() > 0;
        };

        if (!$hasColumn($db, 'leads', 'lid_lookup_attempts')) {
            $db->exec(
                'ALTER TABLE leads
                 ADD COLUMN lid_lookup_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0
                 AFTER whatsapp_lid_jid'
            );
        }

        if (!$hasColumn($db, 'leads', 'lid_lookup_last_at')) {
            $db->exec(
                'ALTER TABLE leads
                 ADD COLUMN lid_lookup_last_at TIMESTAMP NULL
                 AFTER lid_lookup_attempts'
            );
        }

        if (!$hasIndex($db, 'leads', 'idx_leads_lid_lookup')) {
            $db->exec(
                'ALTER TABLE leads
                 ADD INDEX idx_leads_lid_lookup (tenant_id, whatsapp_lid_jid, lid_lookup_last_at)'
            );
        }
    },

    'down' => function (PDO $db): void {
        try {
            $db->exec('ALTER TABLE leads DROP INDEX idx_leads_lid_lookup');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE leads DROP COLUMN lid_lookup_last_at');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE leads DROP COLUMN lid_lookup_attempts');
        } catch (\Throwable $e) {
        }
    },

    'description' => 'Adiciona lid_lookup_attempts e lid_lookup_last_at em leads para o worker de self-healing LID',
];
