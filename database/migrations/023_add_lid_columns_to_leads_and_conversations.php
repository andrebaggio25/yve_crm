<?php

/**
 * Colunas explicitas de LID (WhatsApp) em leads e conversations, com indices
 * que garantem deduplicacao de conversas por tenant+instancia+LID.
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

        $hasIndex = static function (PDO $db, string $table, string $indexName): bool {
            $stmt = $db->query(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = '" . $table . "'
                   AND INDEX_NAME = '" . $indexName . "'"
            );

            return (int) $stmt->fetchColumn() > 0;
        };

        if (!$hasColumn($db, 'leads', 'whatsapp_lid_jid')) {
            $db->exec(
                "ALTER TABLE leads
                 ADD COLUMN whatsapp_lid_jid VARCHAR(80)
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                 NULL AFTER phone_normalized"
            );
        }
        if (!$hasIndex($db, 'leads', 'idx_leads_tenant_lid')) {
            $db->exec('CREATE INDEX idx_leads_tenant_lid ON leads (tenant_id, whatsapp_lid_jid)');
        }

        if (!$hasColumn($db, 'conversations', 'whatsapp_lid_jid')) {
            $db->exec(
                "ALTER TABLE conversations
                 ADD COLUMN whatsapp_lid_jid VARCHAR(80)
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
                 NULL AFTER contact_phone"
            );
        }
        if (!$hasIndex($db, 'conversations', 'uq_conv_tenant_instance_lid')) {
            // NULLs multiplos sao permitidos em UNIQUE do InnoDB, o que preserva
            // conversas sem LID associado.
            $db->exec(
                'CREATE UNIQUE INDEX uq_conv_tenant_instance_lid
                 ON conversations (tenant_id, whatsapp_instance_id, whatsapp_lid_jid)'
            );
        }

        // Backfill a partir do metadata_json (best effort) para nao reprocessar
        // eventos antigos manualmente.
        try {
            $db->exec(
                "UPDATE leads
                 SET whatsapp_lid_jid = JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.whatsapp_jid'))
                 WHERE whatsapp_lid_jid IS NULL
                   AND metadata_json IS NOT NULL
                   AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.whatsapp_jid')) LIKE '%@lid'"
            );
        } catch (\Throwable $e) {
            // ignora dialects que nao suportem JSON_EXTRACT com esse padrao
        }

        try {
            $db->exec(
                "UPDATE conversations
                 SET whatsapp_lid_jid = JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.wa_remote_jid'))
                 WHERE whatsapp_lid_jid IS NULL
                   AND metadata_json IS NOT NULL
                   AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.wa_remote_jid')) LIKE '%@lid'"
            );
        } catch (\Throwable $e) {
            // ignora
        }
    },

    'down' => function (PDO $db): void {
        try {
            $db->exec('ALTER TABLE conversations DROP INDEX uq_conv_tenant_instance_lid');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE conversations DROP COLUMN whatsapp_lid_jid');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE leads DROP INDEX idx_leads_tenant_lid');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE leads DROP COLUMN whatsapp_lid_jid');
        } catch (\Throwable $e) {
        }
    },

    'description' => 'Adiciona whatsapp_lid_jid em leads e conversations + unique de conversa por tenant+instancia+LID',
];
