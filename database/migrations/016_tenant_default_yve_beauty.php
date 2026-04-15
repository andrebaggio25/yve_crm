<?php

/**
 * Produção single-tenant: empresa padrão como "Yve Beauty" (id=1).
 * Não altera dados de leads/templates — apenas metadados do tenant.
 * O backfill de tenant_id=1 já foi feito na 014 para registros existentes.
 */
return [
    'up' => function (PDO $db): void {
        $row = $db->query('SELECT id, slug FROM tenants WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        // Evita conflito de slug se já existir outro tenant com yve-beauty
        $other = $db->prepare('SELECT id FROM tenants WHERE slug = :s AND id != 1');
        $other->execute([':s' => 'yve-beauty']);
        if ($other->fetch()) {
            $db->exec("UPDATE tenants SET name = 'Yve Beauty' WHERE id = 1");

            return;
        }
        $db->exec("UPDATE tenants SET name = 'Yve Beauty', slug = 'yve-beauty' WHERE id = 1");
    },

    'down' => function (PDO $db): void {
        $db->exec("UPDATE tenants SET name = 'Organizacao Padrao', slug = 'default' WHERE id = 1 AND slug = 'yve-beauty'");
    },

    'description' => 'Tenant id=1: nome Yve Beauty e slug yve-beauty (cliente unico em prod)',
];
