<?php

return [
    'up' => "CREATE TABLE pipeline_stages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        stage_type ENUM('initial', 'intermediate', 'hot', 'warm', 'cold', 'won', 'lost') DEFAULT 'intermediate',
        color_token VARCHAR(50) DEFAULT '#6B7280',
        position INT NOT NULL DEFAULT 0,
        is_default BOOLEAN DEFAULT FALSE,
        is_final BOOLEAN DEFAULT FALSE,
        win_probability DECIMAL(5,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (pipeline_id) REFERENCES pipelines(id) ON DELETE CASCADE,
        INDEX idx_pipeline (pipeline_id),
        INDEX idx_position (position),
        INDEX idx_stage_type (stage_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS pipeline_stages",
    
    'description' => 'Cria tabela de etapas dos pipelines'
];
