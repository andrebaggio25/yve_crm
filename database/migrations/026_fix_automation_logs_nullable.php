<?php

/**
 * Permite automation_rule_id NULL em automation_logs.
 * O AutomationEngine ja loga acoes que podem nao ter rule associada
 * (ex.: logs de debug), mas o schema original exigia NOT NULL, o que
 * silenciava os inserts via try/catch. Tambem remove a FK para nao falhar
 * em caso de limpeza de regras.
 */
return [
    'up' => function (PDO $db): void {
        // Drop FK se existir. Nome segue o padrao do migration 015.
        try {
            $fk = $db->query(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'automation_logs'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                   AND CONSTRAINT_NAME = 'fk_auto_log_rule'"
            );
            if ((int) $fk->fetchColumn() > 0) {
                $db->exec('ALTER TABLE automation_logs DROP FOREIGN KEY fk_auto_log_rule');
            }
        } catch (\Throwable $e) {
        }

        $db->exec('ALTER TABLE automation_logs MODIFY automation_rule_id INT UNSIGNED NULL');

        // Re-adiciona FK permitindo NULL (ON DELETE SET NULL para preservar historico).
        try {
            $exists = $db->query(
                "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'automation_logs'
                   AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                   AND CONSTRAINT_NAME = 'fk_auto_log_rule_nullable'"
            );
            if ((int) $exists->fetchColumn() === 0) {
                $db->exec(
                    'ALTER TABLE automation_logs
                     ADD CONSTRAINT fk_auto_log_rule_nullable
                     FOREIGN KEY (automation_rule_id) REFERENCES automation_rules(id) ON DELETE SET NULL'
                );
            }
        } catch (\Throwable $e) {
        }
    },

    'down' => function (PDO $db): void {
        try {
            $db->exec('ALTER TABLE automation_logs DROP FOREIGN KEY fk_auto_log_rule_nullable');
        } catch (\Throwable $e) {
        }
        $db->exec('ALTER TABLE automation_logs MODIFY automation_rule_id INT UNSIGNED NOT NULL');
    },

    'description' => 'Permite automation_rule_id NULL em automation_logs e ajusta FK para ON DELETE SET NULL',
];
