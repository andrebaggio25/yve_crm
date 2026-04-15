<?php
/**
 * Migration: Adicionar UNIQUE index em conversations para evitar duplicatas
 * Data: 2026-04-14
 *
 * Adiciona um UNIQUE index em (tenant_id, whatsapp_instance_id, contact_phone)
 * para garantir que nao haja conversas duplicadas para o mesmo contato/instancia.
 */

use App\Core\Database;

return [
    'description' => 'Adiciona UNIQUE index em conversations e index em messages',
    'up' => function ($db) {
        // Verificar se o indice ja existe em conversations
        $checkStmt = $db->query(
            "SELECT 1 FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'conversations' 
             AND INDEX_NAME = 'idx_conv_unique_contact'"
        );
        $checkIndex = $checkStmt ? $checkStmt->fetch() : false;

        if (!$checkIndex) {
            // Adicionar UNIQUE index
            $db->exec("ALTER TABLE conversations 
                       ADD UNIQUE INDEX idx_conv_unique_contact (tenant_id, whatsapp_instance_id, contact_phone)");
        }

        // Verificar se o indice ja existe em messages
        $checkMsgStmt = $db->query(
            "SELECT 1 FROM information_schema.STATISTICS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = 'messages' 
             AND INDEX_NAME = 'idx_msg_wa_id'"
        );
        $checkMsgIndex = $checkMsgStmt ? $checkMsgStmt->fetch() : false;

        if (!$checkMsgIndex) {
            $db->exec("ALTER TABLE messages 
                       ADD INDEX idx_msg_wa_id (whatsapp_message_id)");
        }
    },
    'down' => function ($db) {
        // Remover indices
        try {
            $db->exec("ALTER TABLE conversations DROP INDEX idx_conv_unique_contact");
        } catch (\Exception $e) {
            // Index pode nao existir, ignorar erro
        }
        try {
            $db->exec("ALTER TABLE messages DROP INDEX idx_msg_wa_id");
        } catch (\Exception $e) {
            // Index pode nao existir, ignorar erro
        }
    }
];
