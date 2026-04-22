<?php

/**
 * LID sintetico pode ultrapassar VARCHAR(20); mantém deduplicacao por phone com maior folga.
 */
return [
    'up' => function (PDO $db): void {
        $db->exec('ALTER TABLE leads MODIFY COLUMN phone_normalized VARCHAR(50) NULL');
    },

    'down' => function (PDO $db): void {
        $db->exec('ALTER TABLE leads MODIFY COLUMN phone_normalized VARCHAR(20) NULL');
    },

    'description' => 'Expande phone_normalized para acomodar chaves LID e numeros longos',
];
