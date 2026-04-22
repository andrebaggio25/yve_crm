<?php

/**
 * Cache de avatar do contato (foto do perfil WhatsApp) em conversations
 * + timestamp para revalidacao periodica sem bater sempre na Evolution.
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

        if (!$hasColumn($db, 'conversations', 'contact_avatar_url')) {
            $db->exec(
                'ALTER TABLE conversations
                 ADD COLUMN contact_avatar_url VARCHAR(500) NULL AFTER contact_push_name'
            );
        }
        if (!$hasColumn($db, 'conversations', 'contact_avatar_updated_at')) {
            $db->exec(
                'ALTER TABLE conversations
                 ADD COLUMN contact_avatar_updated_at TIMESTAMP NULL AFTER contact_avatar_url'
            );
        }
    },

    'down' => function (PDO $db): void {
        try {
            $db->exec('ALTER TABLE conversations DROP COLUMN contact_avatar_updated_at');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE conversations DROP COLUMN contact_avatar_url');
        } catch (\Throwable $e) {
        }
    },

    'description' => 'Adiciona contact_avatar_url e contact_avatar_updated_at em conversations (foto de perfil WhatsApp)',
];
