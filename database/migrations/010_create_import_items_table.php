<?php

return [
    'up' => "CREATE TABLE import_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        import_id INT NOT NULL,
        `row_number` INT NOT NULL,
        raw_data_json JSON NOT NULL,
        status ENUM('pending', 'imported', 'duplicated', 'invalid', 'error') DEFAULT 'pending',
        error_message TEXT NULL,
        lead_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (import_id) REFERENCES imports(id) ON DELETE CASCADE,
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE SET NULL,
        INDEX idx_import (import_id),
        INDEX idx_status (status),
        INDEX idx_lead (lead_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS import_items",
    
    'description' => 'Cria tabela de itens de importacao (linha a linha)'
];
