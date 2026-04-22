<?php

/**
 * Multi-tenancy: tabela tenants + tenant_id nas tabelas de negocio + ajustes de UNIQUE.
 */
return [
    'up' => function (PDO $db): void {
        $db->exec("CREATE TABLE IF NOT EXISTS tenants (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            owner_user_id INT UNSIGNED NULL,
            plan ENUM('free','starter','pro','enterprise') NOT NULL DEFAULT 'free',
            status ENUM('active','trial','suspended','cancelled') NOT NULL DEFAULT 'trial',
            settings_json JSON NULL,
            max_users INT NOT NULL DEFAULT 3,
            max_leads INT NOT NULL DEFAULT 500,
            trial_ends_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tenants_slug (slug),
            INDEX idx_tenants_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $cnt = (int) $db->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        if ($cnt === 0) {
            $db->exec("INSERT INTO tenants (id, name, slug, plan, status, settings_json)
                VALUES (1, 'Organizacao Padrao', 'default', 'enterprise', 'active', NULL)");
        }

        $tables = [
            'users',
            'pipelines',
            'pipeline_stages',
            'leads',
            'lead_tags',
            'lead_tag_items',
            'lead_events',
            'message_templates',
            'imports',
            'import_items',
            'scheduled_messages',
        ];

        foreach ($tables as $t) {
            $db->exec("ALTER TABLE {$t} ADD COLUMN tenant_id INT UNSIGNED NULL AFTER id");
        }

        $db->exec('UPDATE users SET tenant_id = 1 WHERE tenant_id IS NULL');
        $db->exec('UPDATE pipelines SET tenant_id = 1 WHERE tenant_id IS NULL');
        $db->exec('UPDATE pipeline_stages ps JOIN pipelines p ON p.id = ps.pipeline_id SET ps.tenant_id = p.tenant_id WHERE ps.tenant_id IS NULL');
        $db->exec('UPDATE leads l JOIN pipelines p ON p.id = l.pipeline_id SET l.tenant_id = p.tenant_id WHERE l.tenant_id IS NULL');
        $db->exec('UPDATE lead_tags SET tenant_id = 1 WHERE tenant_id IS NULL');
        $db->exec('UPDATE lead_tag_items ti JOIN leads l ON l.id = ti.lead_id SET ti.tenant_id = l.tenant_id WHERE ti.tenant_id IS NULL');
        $db->exec('UPDATE lead_events e JOIN leads l ON l.id = e.lead_id SET e.tenant_id = l.tenant_id WHERE e.tenant_id IS NULL');
        $db->exec('UPDATE message_templates mt LEFT JOIN pipelines p ON p.id = mt.pipeline_id SET mt.tenant_id = COALESCE(p.tenant_id, 1) WHERE mt.tenant_id IS NULL');
        $db->exec('UPDATE imports i JOIN users u ON u.id = i.user_id SET i.tenant_id = u.tenant_id WHERE i.tenant_id IS NULL');
        $db->exec('UPDATE import_items ii JOIN imports i ON i.id = ii.import_id SET ii.tenant_id = i.tenant_id WHERE ii.tenant_id IS NULL');
        $db->exec('UPDATE scheduled_messages sm JOIN leads l ON l.id = sm.lead_id SET sm.tenant_id = l.tenant_id WHERE sm.tenant_id IS NULL');

        foreach ($tables as $t) {
            $db->exec("ALTER TABLE {$t} MODIFY tenant_id INT UNSIGNED NOT NULL");
        }

        // users: role + email unico por tenant
        $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','gestor','vendedor') NOT NULL DEFAULT 'vendedor'");

        try {
            $db->exec('ALTER TABLE users DROP INDEX email');
        } catch (\Throwable $e) {
            try {
                $db->exec('ALTER TABLE users DROP INDEX users_email_unique');
            } catch (\Throwable $e2) {
                // ignora se nome do indice variar
            }
        }
        $db->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_tenant_email (tenant_id, email)');

        // lead_tags: nome unico por tenant
        try {
            $db->exec('ALTER TABLE lead_tags DROP INDEX name');
        } catch (\Throwable $e) {
            try {
                $db->exec('ALTER TABLE lead_tags DROP INDEX lead_tags_name_unique');
            } catch (\Throwable $e2) {
            }
        }
        $db->exec('ALTER TABLE lead_tags ADD UNIQUE KEY uq_lead_tags_tenant_name (tenant_id, name)');

        // message_templates slug unico por tenant
        try {
            $db->exec('ALTER TABLE message_templates DROP INDEX slug');
        } catch (\Throwable $e) {
            try {
                $db->exec('ALTER TABLE message_templates DROP INDEX message_templates_slug_unique');
            } catch (\Throwable $e2) {
            }
        }
        $db->exec('ALTER TABLE message_templates ADD UNIQUE KEY uq_templates_tenant_slug (tenant_id, slug)');

        $fks = [
            'ALTER TABLE users ADD CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT',
            'ALTER TABLE tenants ADD CONSTRAINT fk_tenants_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL',
            'ALTER TABLE pipelines ADD CONSTRAINT fk_pipelines_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE pipeline_stages ADD CONSTRAINT fk_pipeline_stages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE leads ADD CONSTRAINT fk_leads_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE lead_tags ADD CONSTRAINT fk_lead_tags_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE lead_tag_items ADD CONSTRAINT fk_lead_tag_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE lead_events ADD CONSTRAINT fk_lead_events_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE message_templates ADD CONSTRAINT fk_message_templates_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE imports ADD CONSTRAINT fk_imports_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE import_items ADD CONSTRAINT fk_import_items_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
            'ALTER TABLE scheduled_messages ADD CONSTRAINT fk_scheduled_messages_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE',
        ];
        foreach ($fks as $sql) {
            try {
                $db->exec($sql);
            } catch (\Throwable $e) {
                // FK ja existe
            }
        }

        $db->exec('CREATE INDEX idx_users_tenant ON users (tenant_id)');
        $db->exec('CREATE INDEX idx_pipelines_tenant ON pipelines (tenant_id)');
        $db->exec('CREATE INDEX idx_pipeline_stages_tenant ON pipeline_stages (tenant_id)');
        $db->exec('CREATE INDEX idx_leads_tenant ON leads (tenant_id)');
        $db->exec('CREATE INDEX idx_lead_tags_tenant ON lead_tags (tenant_id)');
        $db->exec('CREATE INDEX idx_lead_tag_items_tenant ON lead_tag_items (tenant_id)');
        $db->exec('CREATE INDEX idx_lead_events_tenant ON lead_events (tenant_id)');
        $db->exec('CREATE INDEX idx_message_templates_tenant ON message_templates (tenant_id)');
        $db->exec('CREATE INDEX idx_imports_tenant ON imports (tenant_id)');
        $db->exec('CREATE INDEX idx_import_items_tenant ON import_items (tenant_id)');
        $db->exec('CREATE INDEX idx_scheduled_messages_tenant ON scheduled_messages (tenant_id)');

        // owner do tenant padrao: primeiro admin
        $row = $db->query("SELECT id FROM users WHERE tenant_id = 1 AND role = 'admin' ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $stmt = $db->prepare('UPDATE tenants SET owner_user_id = :uid WHERE id = 1');
            $stmt->execute([':uid' => (int) $row['id']]);
        }
    },

    'down' => function (PDO $db): void {
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        $tables = [
            'scheduled_messages',
            'import_items',
            'imports',
            'message_templates',
            'lead_events',
            'lead_tag_items',
            'lead_tags',
            'leads',
            'pipeline_stages',
            'pipelines',
            'users',
        ];
        foreach ($tables as $t) {
            try {
                $db->exec("ALTER TABLE {$t} DROP FOREIGN KEY fk_{$t}_tenant");
            } catch (\Throwable $e) {
            }
            try {
                $db->exec("ALTER TABLE {$t} DROP COLUMN tenant_id");
            } catch (\Throwable $e) {
            }
        }
        try {
            $db->exec('ALTER TABLE tenants DROP FOREIGN KEY fk_tenants_owner');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE users MODIFY COLUMN role ENUM(\'admin\',\'gestor\',\'vendedor\') NOT NULL DEFAULT \'vendedor\'');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE users DROP INDEX uq_users_tenant_email');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE users ADD UNIQUE KEY email (email)');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE lead_tags DROP INDEX uq_lead_tags_tenant_name');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE lead_tags ADD UNIQUE KEY name (name)');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE message_templates DROP INDEX uq_templates_tenant_slug');
        } catch (\Throwable $e) {
        }
        try {
            $db->exec('ALTER TABLE message_templates ADD UNIQUE KEY slug (slug)');
        } catch (\Throwable $e) {
        }
        $db->exec('DROP TABLE IF EXISTS tenants');
        $db->exec('SET FOREIGN_KEY_CHECKS=1');
    },

    'description' => 'Multi-tenancy: tenants + tenant_id + FKs e uniques por tenant',
];
