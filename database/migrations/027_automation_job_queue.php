<?php

/**
 * Fila assincrona opcional para execucao de automacoes.
 * Ativada via AUTOMATION_ASYNC=1 em .env. Quando ativa, AutomationEngine::dispatch()
 * grava o payload aqui e retorna, e o scheduled_worker.php processa em batch.
 */
return [
    'up' => function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS automation_job_queue (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id INT UNSIGNED NOT NULL,
            trigger_event VARCHAR(64) NOT NULL,
            payload_json JSON NOT NULL,
            status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
            scheduled_for TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_aqj_pickup (status, scheduled_for),
            INDEX idx_aqj_tenant (tenant_id),
            CONSTRAINT fk_aqj_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    },

    'down' => function (PDO $db): void {
        $db->exec('DROP TABLE IF EXISTS automation_job_queue');
    },

    'description' => 'Fila assincrona opcional para automacoes (controlada por AUTOMATION_ASYNC env)',
];
