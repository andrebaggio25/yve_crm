<?php

return [
    'up' => "CREATE TABLE message_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(100) NOT NULL UNIQUE,
        channel ENUM('whatsapp', 'email', 'sms') DEFAULT 'whatsapp',
        stage_type ENUM('initial', 'hot', 'warm', 'cold', 'won', 'lost', 'any') DEFAULT 'any',
        content TEXT NOT NULL,
        variables JSON NULL,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_slug (slug),
        INDEX idx_channel (channel),
        INDEX idx_stage_type (stage_type),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS message_templates",
    
    'description' => 'Cria tabela de templates de mensagem'
];
