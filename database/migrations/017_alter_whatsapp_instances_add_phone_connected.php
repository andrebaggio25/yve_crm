<?php

/**
 * Adiciona campo phone_connected na tabela whatsapp_instances
 * para facilitar verificacao de status da conexao.
 */
return [
    'up' => function (PDO $db): void {
        // Verifica se coluna ja existe
        $stmt = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'whatsapp_instances' AND COLUMN_NAME = 'phone_connected'");
        $exists = (int) $stmt->fetchColumn() > 0;

        if (!$exists) {
            $db->exec("ALTER TABLE whatsapp_instances ADD COLUMN phone_connected TINYINT(1) NOT NULL DEFAULT 0 AFTER phone_number");
        }

        // Atualiza registros existentes que tem phone_number mas phone_connected = 0
        $db->exec("UPDATE whatsapp_instances SET phone_connected = 1 WHERE phone_number IS NOT NULL AND phone_number != '' AND phone_connected = 0");
    },

    'down' => function (PDO $db): void {
        $db->exec("ALTER TABLE whatsapp_instances DROP COLUMN IF EXISTS phone_connected");
    },

    'description' => 'Adiciona coluna phone_connected na tabela whatsapp_instances',
];
