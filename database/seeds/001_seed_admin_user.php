<?php

return [
    'run' => function(PDO $db) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmt->execute([':email' => 'admin@yve.crm']);
        
        if ($stmt->fetchColumn() > 0) {
            return ['message' => 'Usuario admin ja existe', 'skipped' => true];
        }
        
        $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, role, status) 
                             VALUES (:name, :email, :password, :role, :status)");
        
        $stmt->execute([
            ':name' => 'Administrador',
            ':email' => 'admin@yve.crm',
            ':password' => password_hash('admin123', PASSWORD_BCRYPT),
            ':role' => 'admin',
            ':status' => 'active'
        ]);
        
        $userId = $db->lastInsertId();
        
        return [
            'message' => 'Usuario admin criado com sucesso',
            'user_id' => $userId,
            'email' => 'admin@yve.crm',
            'password' => 'admin123 (altere apos o primeiro login)'
        ];
    }
];
