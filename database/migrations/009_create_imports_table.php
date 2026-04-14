<?php

return [
    'up' => "CREATE TABLE imports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        source_name VARCHAR(100) NULL,
        file_path VARCHAR(500) NULL,
        total_rows INT DEFAULT 0,
        imported_rows INT DEFAULT 0,
        duplicated_rows INT DEFAULT 0,
        invalid_rows INT DEFAULT 0,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        error_message TEXT NULL,
        mapping_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user (user_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS imports",
    
    'description' => 'Cria tabela de importacoes CSV'
];
