<?php

/**
 * Armazenamento local de midia WhatsApp (decrypt via Evolution) + metadados.
 */
return [
    'up' => function (PDO $db): void {
        $db->exec("ALTER TABLE messages
            ADD COLUMN media_local_path VARCHAR(500) NULL AFTER media_filename,
            ADD COLUMN media_size_bytes INT UNSIGNED NULL AFTER media_local_path,
            ADD COLUMN media_duration_seconds INT UNSIGNED NULL AFTER media_size_bytes,
            ADD COLUMN media_ptt TINYINT(1) NOT NULL DEFAULT 0 AFTER media_duration_seconds");
    },

    'down' => function (PDO $db): void {
        $db->exec('ALTER TABLE messages
            DROP COLUMN media_ptt,
            DROP COLUMN media_duration_seconds,
            DROP COLUMN media_size_bytes,
            DROP COLUMN media_local_path');
    },

    'description' => 'Colunas para midia local (path, tamanho, duracao, PTT)',
];
