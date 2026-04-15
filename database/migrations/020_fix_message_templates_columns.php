<?php
/**
 * Migration: Corrigir colunas da tabela message_templates
 * Data: 2026-04-14
 *
 * Adiciona colunas ausentes (position, is_active, etc) se nao existirem
 */

return [
    'description' => 'Adiciona colunas ausentes em message_templates',
    'up' => function ($db) {
        // Verificar e adicionar coluna position
        $checkCol = $db->query("SHOW COLUMNS FROM message_templates LIKE 'position'");
        if (!$checkCol->fetch()) {
            $db->exec("ALTER TABLE message_templates ADD COLUMN position INT NOT NULL DEFAULT 1 AFTER stage_type");
        }

        // Verificar e adicionar coluna is_active (pode ja existir como BOOLEAN)
        $checkActive = $db->query("SHOW COLUMNS FROM message_templates LIKE 'is_active'");
        if (!$checkActive->fetch()) {
            $db->exec("ALTER TABLE message_templates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER variables");
        }

        // Verificar e adicionar colunas pipeline_id e stage_id
        $checkPipeline = $db->query("SHOW COLUMNS FROM message_templates LIKE 'pipeline_id'");
        if (!$checkPipeline->fetch()) {
            $db->exec("ALTER TABLE message_templates ADD COLUMN pipeline_id INT NULL AFTER position");
        }

        $checkStage = $db->query("SHOW COLUMNS FROM message_templates LIKE 'stage_id'");
        if (!$checkStage->fetch()) {
            $db->exec("ALTER TABLE message_templates ADD COLUMN stage_id INT NULL AFTER pipeline_id");
        }

        // Verificar e adicionar tenant_id se nao existir
        $checkTenant = $db->query("SHOW COLUMNS FROM message_templates LIKE 'tenant_id'");
        if (!$checkTenant->fetch()) {
            $db->exec("ALTER TABLE message_templates ADD COLUMN tenant_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id");
        }
    },
    'down' => function ($db) {
        // Nao remove colunas em rollback para evitar perda de dados
    }
];
