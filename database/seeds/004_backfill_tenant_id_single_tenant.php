<?php

/**
 * Alinha dados de negócio ao tenant id=1 (instalação single-tenant / produção com um cliente).
 *
 * Percorre automaticamente todas as tabelas com coluna tenant_id (exc. tenants, migrations):
 * - Preenche tenant_id = 1 onde estiver NULL ou 0.
 * - Corrige referências órfãs (tenant_id que não existe em tenants).
 *
 * Não altera linhas com tenant_id válido e diferente de 1 (ex.: multi-tenant real).
 * Idempotente: migrate.php executa todas as seeds em cada corrida.
 */
return [
    'run' => function (PDO $db) {
        $db->exec('SET FOREIGN_KEY_CHECKS = 0');

        try {
            $hasTenants = (int) $db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants'")->fetchColumn() > 0;
            if (!$hasTenants) {
                return ['message' => 'Tabela tenants inexistente — seed ignorada', 'skipped' => true];
            }

            $t1 = $db->query('SELECT id FROM tenants WHERE id = 1 LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            if (!$t1) {
                return ['message' => 'Tenant id=1 inexistente — crie o tenant padrao antes (migration 014)', 'skipped' => true];
            }

            $stmt = $db->query("SELECT DISTINCT TABLE_NAME AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'tenant_id' AND TABLE_NAME NOT IN ('tenants','migrations') ORDER BY TABLE_NAME");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            $updated = [];

            foreach ($tables as $table) {
                $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
                if ($table === '') {
                    continue;
                }

                // 1) NULL e 0 → 1
                $sqlNull = "UPDATE `{$table}` SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0";
                $n1 = $db->exec($sqlNull);

                // 2) Órfão: tenant_id que não existe em tenants
                $sqlOrphan = "UPDATE `{$table}` t SET t.tenant_id = 1 WHERE t.tenant_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM tenants x WHERE x.id = t.tenant_id)";
                $n2 = $db->exec($sqlOrphan);

                $c1 = ($n1 === false) ? 0 : (int) $n1;
                $c2 = ($n2 === false) ? 0 : (int) $n2;
                if ($c1 + $c2 > 0) {
                    $updated[$table] = $c1 + $c2;
                }
            }

            $msg = empty($updated)
                ? 'Nenhuma linha alterada (dados ja alinhados ao tenant 1)'
                : 'tenant_id corrigido: ' . json_encode($updated, JSON_UNESCAPED_UNICODE);

            return [
                'message' => $msg,
                'tables_touched' => array_keys($updated),
            ];
        } finally {
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    },
];
