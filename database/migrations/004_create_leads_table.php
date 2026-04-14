<?php

return [
    'up' => "CREATE TABLE leads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pipeline_id INT NOT NULL,
        stage_id INT NOT NULL,
        assigned_user_id INT NULL,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NULL,
        phone_normalized VARCHAR(20) NULL,
        email VARCHAR(255) NULL,
        source VARCHAR(100) NULL,
        product_interest VARCHAR(255) NULL,
        value DECIMAL(12,2) DEFAULT 0.00,
        score INT DEFAULT 0,
        temperature ENUM('hot', 'warm', 'cold') DEFAULT 'cold',
        status ENUM('active', 'won', 'lost', 'archived') DEFAULT 'active',
        last_contact_at TIMESTAMP NULL,
        next_action_at TIMESTAMP NULL,
        next_action_description TEXT NULL,
        won_at TIMESTAMP NULL,
        lost_at TIMESTAMP NULL,
        loss_reason VARCHAR(255) NULL,
        notes_summary TEXT NULL,
        metadata_json JSON NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (pipeline_id) REFERENCES pipelines(id),
        FOREIGN KEY (stage_id) REFERENCES pipeline_stages(id),
        FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_pipeline_stage (pipeline_id, stage_id),
        INDEX idx_assigned_user (assigned_user_id),
        INDEX idx_phone_normalized (phone_normalized),
        INDEX idx_status (status),
        INDEX idx_temperature (temperature),
        INDEX idx_next_action (next_action_at),
        INDEX idx_last_contact (last_contact_at),
        INDEX idx_created (created_at),
        INDEX idx_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS leads",
    
    'description' => 'Cria tabela de leads com soft delete'
];
