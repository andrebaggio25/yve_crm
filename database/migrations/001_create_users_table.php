<?php

return [
    'up' => "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('admin', 'gestor', 'vendedor') DEFAULT 'vendedor',
        status ENUM('active', 'inactive') DEFAULT 'active',
        avatar_url VARCHAR(500) NULL,
        phone VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        INDEX idx_email (email),
        INDEX idx_role (role),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'down' => "DROP TABLE IF EXISTS users",
    
    'description' => 'Cria tabela de usuarios com soft delete'
];
