<?php

return [
    'up' => "CREATE TABLE lead_tag_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lead_id INT NOT NULL,
        tag_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES lead_tags(id) ON DELETE CASCADE,
        UNIQUE KEY unique_lead_tag (lead_id, tag_id),
        INDEX idx_lead (lead_id),
        INDEX idx_tag (tag_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS lead_tag_items",
    
    'description' => 'Cria tabela de relacionamento leads-tags'
];
