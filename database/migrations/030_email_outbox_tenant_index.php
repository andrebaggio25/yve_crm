<?php

/**
 * Indice para listar fila de e-mail por tenant e status.
 */
return [
    'up' => function (PDO $db): void {
        if (!$db->query("SHOW TABLES LIKE 'email_outbox'")->fetch()) {
            return;
        }
        $r = $db->query("SHOW INDEX FROM email_outbox WHERE Key_name = 'idx_email_outbox_tenant_status'")->fetch();
        if ($r) {
            return;
        }
        $db->exec('ALTER TABLE email_outbox ADD INDEX idx_email_outbox_tenant_status (tenant_id, status, created_at)');
    },
    'down' => function (PDO $db): void {
        if (!$db->query("SHOW TABLES LIKE 'email_outbox'")->fetch()) {
            return;
        }
        try {
            $db->exec('ALTER TABLE email_outbox DROP INDEX idx_email_outbox_tenant_status');
        } catch (\Throwable) {
        }
    },
    'description' => 'indice email_outbox(tenant_id, status, created_at)',
];
