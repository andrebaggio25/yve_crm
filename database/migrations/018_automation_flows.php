<?php

/**
 * Visual Automation Flow Builder - Suporte a flow_json, executions e scheduled actions.
 */
return [
    'up' => function (PDO $db): void {
        // Adicionar coluna flow_json na tabela automation_rules (se ainda nao existir)
        try {
            $db->exec("ALTER TABLE automation_rules ADD COLUMN flow_json JSON NULL AFTER conditions_json");
        } catch (PDOException $e) {
            // Coluna pode ja existir
            if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'already exists') === false) {
                throw $e;
            }
        }

        // Tabela para rastrear execucoes de fluxos em andamento/completados
        $db->exec("CREATE TABLE IF NOT EXISTS automation_executions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            automation_rule_id INT UNSIGNED NOT NULL,
            lead_id INT NULL,
            status ENUM('running','paused','completed','failed','cancelled') NOT NULL DEFAULT 'running',
            current_node_id VARCHAR(64) NULL,
            context_json JSON NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            INDEX idx_exec_tenant (tenant_id),
            INDEX idx_exec_rule (automation_rule_id),
            INDEX idx_exec_status (tenant_id, status),
            INDEX idx_exec_lead (lead_id),
            CONSTRAINT fk_exec_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
            CONSTRAINT fk_exec_rule FOREIGN KEY (automation_rule_id) REFERENCES automation_rules(id) ON DELETE CASCADE,
            CONSTRAINT fk_exec_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Tabela para acoes agendadas (delays) a serem processadas pelo worker
        $db->exec("CREATE TABLE IF NOT EXISTS automation_scheduled_actions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            execution_id INT UNSIGNED NOT NULL,
            node_id VARCHAR(64) NOT NULL,
            scheduled_for DATETIME NOT NULL,
            status ENUM('pending','processing','done','failed','cancelled') NOT NULL DEFAULT 'pending',
            attempts INT UNSIGNED NOT NULL DEFAULT 0,
            error_message TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL,
            INDEX idx_sched_exec (execution_id),
            INDEX idx_sched_time (status, scheduled_for),
            CONSTRAINT fk_sched_exec FOREIGN KEY (execution_id) REFERENCES automation_executions(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS automation_scheduled_actions');
        $db->exec('DROP TABLE IF EXISTS automation_executions');
        try {
            $db->exec('ALTER TABLE automation_rules DROP COLUMN flow_json');
        } catch (PDOException $e) {
            // Ignora se a coluna nao existir
        }
    },

    'description' => 'Visual Automation Flow Builder - flow_json, executions e scheduled actions',
];
