<?php

return [
    'up' => "CREATE TABLE lead_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        user_id INT NULL,
        event_type ENUM('created', 'updated', 'stage_changed', 'note_added', 'whatsapp_trigger', 'import', 'email_sent', 'call_made', 'meeting_scheduled', 'assigned', 'converted', 'lost', 'deleted', 'restored') NOT NULL,
        description TEXT NULL,
        metadata_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_lead (lead_id),
        INDEX idx_event_type (event_type),
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS lead_events",
    
    'description' => 'Cria tabela de eventos do lead (historico)'
];
