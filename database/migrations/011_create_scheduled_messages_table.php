<?php

return [
    'up' => "CREATE TABLE scheduled_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        template_id INT NULL,
        channel ENUM('whatsapp', 'email', 'sms') DEFAULT 'whatsapp',
        content TEXT NOT NULL,
        scheduled_at TIMESTAMP NOT NULL,
        sent_at TIMESTAMP NULL,
        status ENUM('pending', 'sent', 'failed', 'cancelled') DEFAULT 'pending',
        error_message TEXT NULL,
        payload_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
        FOREIGN KEY (template_id) REFERENCES message_templates(id) ON DELETE SET NULL,
        INDEX idx_lead (lead_id),
        INDEX idx_status (status),
        INDEX idx_scheduled (scheduled_at),
        INDEX idx_template (template_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS scheduled_messages",
    
    'description' => 'Cria tabela de mensagens agendadas (futuro)'
];
