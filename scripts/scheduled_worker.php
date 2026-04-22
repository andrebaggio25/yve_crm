#!/usr/bin/env php
<?php

/**
 * Worker CLI: processa scheduled_messages pendentes e automacoes agendadas.
 * Cron: * * * * * php /path/to/scripts/scheduled_worker.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/app/Core/Env.php';
\App\Core\Env::load($root);

require_once $root . '/app/Core/Database.php';
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) use ($root) {
    $prefix = 'App\\';
    $base_dir = $root . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Helpers\PhoneHelper;
use App\Services\WhatsApp\EvolutionApiService;
use App\Services\WhatsApp\LidResolverService;
use App\Services\Automation\AutomationEngine;
use App\Services\Mail\SmtpProcessor;

$totalProcessed = 0;
$totalErrors = 0;

echo "=== Worker iniciado: " . date('Y-m-d H:i:s') . " ===\n";

/**
 * PARTE 1: Processar mensagens agendadas (scheduled_messages)
 */
echo "Processando mensagens agendadas...\n";

/**
 * Seleciona e "trava" ate 40 mensagens pendentes usando SKIP LOCKED,
 * evitando que dois workers peguem a mesma linha.
 */
$rows = [];
$pdo = Database::getInstance();
try {
    $pdo->beginTransaction();
    $rows = Database::fetchAll(
        "SELECT sm.*, l.tenant_id, l.phone, l.phone_normalized
         FROM scheduled_messages sm
         INNER JOIN leads l ON l.id = sm.lead_id
         WHERE sm.status = 'pending' AND sm.scheduled_at <= NOW()
         ORDER BY sm.scheduled_at ASC
         LIMIT 40
         FOR UPDATE SKIP LOCKED"
    );
    if ($rows) {
        $ids = array_map(static fn ($r) => (int) $r['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::query(
            "UPDATE scheduled_messages SET status = 'processing' WHERE id IN ({$placeholders})",
            $ids
        );
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Fallback para MySQL sem suporte a SKIP LOCKED (MariaDB < 10.6 / MySQL < 8).
    $rows = Database::fetchAll(
        "SELECT sm.*, l.tenant_id, l.phone, l.phone_normalized
         FROM scheduled_messages sm
         INNER JOIN leads l ON l.id = sm.lead_id
         WHERE sm.status = 'pending' AND sm.scheduled_at <= NOW()
         ORDER BY sm.scheduled_at ASC
         LIMIT 40"
    );
}

$evo = new EvolutionApiService();

foreach ($rows as $sm) {
    $tid = (int) $sm['tenant_id'];
    $inst = Database::fetch(
        "SELECT * FROM whatsapp_instances WHERE tenant_id = :tid AND status = 'connected' ORDER BY id ASC LIMIT 1",
        [':tid' => $tid]
    );

    if (!$inst) {
        $inst = Database::fetch(
            'SELECT * FROM whatsapp_instances WHERE tenant_id = :tid ORDER BY id ASC LIMIT 1',
            [':tid' => $tid]
        );
    }

    if (!$inst) {
        Database::update(
            'scheduled_messages',
            ['status' => 'failed', 'error_message' => 'Sem instancia WhatsApp'],
            'id = :id AND tenant_id = :tid',
            [':id' => $sm['id'], ':tid' => $tid]
        );
        $totalErrors++;
        continue;
    }

    $digits = '';
    if (!empty($sm['phone']) && trim((string) $sm['phone']) !== '') {
        $digits = preg_replace('/\D/', '', (string) $sm['phone']);
    } elseif (!empty($sm['phone_normalized'])) {
        $digits = preg_replace('/\D/', '', (string) $sm['phone_normalized']);
    }

    if ($digits === '') {
        Database::update(
            'scheduled_messages',
            ['status' => 'failed', 'error_message' => 'Lead sem telefone'],
            'id = :id AND tenant_id = :tid',
            [':id' => $sm['id'], ':tid' => $tid]
        );
        $totalErrors++;
        continue;
    }

    $res = $evo->sendText(
        (string) $inst['api_url'],
        (string) $inst['api_key'],
        (string) $inst['instance_name'],
        PhoneHelper::normalize($digits) ?: $digits,
        (string) $sm['content']
    );

    if ($res['ok']) {
        Database::update(
            'scheduled_messages',
            ['status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'), 'error_message' => null],
            'id = :id AND tenant_id = :tid',
            [':id' => $sm['id'], ':tid' => $tid]
        );
        $totalProcessed++;
    } else {
        Database::update(
            'scheduled_messages',
            ['status' => 'failed', 'error_message' => mb_substr($res['raw'], 0, 500)],
            'id = :id AND tenant_id = :tid',
            [':id' => $sm['id'], ':tid' => $tid]
        );
        $totalErrors++;
    }
}

echo "Mensagens: " . count($rows) . " processadas, {$totalProcessed} sucesso, {$totalErrors} erro\n";

/**
 * PARTE 2: Processar acoes agendadas de automacao (delays)
 */
echo "Processando automacoes agendadas...\n";

$automationProcessed = 0;
$automationErrors = 0;

/**
 * Seleciona e trava acoes agendadas com SKIP LOCKED.
 */
$scheduledActions = [];
try {
    $pdo->beginTransaction();
    $scheduledActions = Database::fetchAll(
        "SELECT sa.*, ae.tenant_id, ae.automation_rule_id, ae.lead_id, ae.context_json, ar.flow_json
         FROM automation_scheduled_actions sa
         JOIN automation_executions ae ON ae.id = sa.execution_id
         JOIN automation_rules ar ON ar.id = ae.automation_rule_id
         WHERE sa.status = 'pending' AND sa.scheduled_for <= NOW()
         ORDER BY sa.scheduled_for ASC
         LIMIT 20
         FOR UPDATE SKIP LOCKED"
    );
    if ($scheduledActions) {
        $ids = array_map(static fn ($r) => (int) $r['id'], $scheduledActions);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        Database::query(
            "UPDATE automation_scheduled_actions SET status = 'processing' WHERE id IN ({$placeholders})",
            $ids
        );
    }
    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $scheduledActions = Database::fetchAll(
        "SELECT sa.*, ae.tenant_id, ae.automation_rule_id, ae.lead_id, ae.context_json, ar.flow_json
         FROM automation_scheduled_actions sa
         JOIN automation_executions ae ON ae.id = sa.execution_id
         JOIN automation_rules ar ON ar.id = ae.automation_rule_id
         WHERE sa.status = 'pending' AND sa.scheduled_for <= NOW()
         ORDER BY sa.scheduled_for ASC
         LIMIT 20"
    );
}

foreach ($scheduledActions as $action) {
    $actionId = (int) $action['id'];
    $executionId = (int) $action['execution_id'];
    $tenantId = (int) $action['tenant_id'];
    $nodeId = (string) $action['node_id'];

    try {
        // Marcar como processando
        Database::update(
            'automation_scheduled_actions',
            ['status' => 'processing', 'attempts' => (int) $action['attempts'] + 1],
            'id = :id',
            [':id' => $actionId]
        );

        // Decodificar flow
        $flow = json_decode($action['flow_json'] ?? '{}', true);
        $nodes = $flow['nodes'] ?? [];

        // Contexto base
        $payload = json_decode($action['context_json'] ?? '{}', true) ?: [];
        if ($action['lead_id']) {
            $payload['lead_id'] = (int) $action['lead_id'];
        }

        // Encontrar o proximo node apos o delay
        $nextNodeId = null;
        foreach ($nodes as $node) {
            if ($node['id'] === $nodeId) {
                $nextNodeId = $node['next'] ?? null;
                break;
            }
        }

        // Se nao achou next, tenta pegar do contexto (salvo quando agendou)
        if (!$nextNodeId && !empty($payload['_resume_after_delay'])) {
            $nextNodeId = $payload['_resume_after_delay'];
            unset($payload['_resume_after_delay']);
        }

        if ($nextNodeId) {
            // Continuar o fluxo
            AutomationEngine::resumeFromNode($tenantId, $executionId, $nextNodeId, $payload, $nodes);
        } else {
            // Nao tem proximo, completar
            Database::update(
                'automation_executions',
                ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'), 'current_node_id' => null],
                'id = :id',
                [':id' => $executionId]
            );
        }

        // Marcar acao como concluida
        Database::update(
            'automation_scheduled_actions',
            ['status' => 'done', 'processed_at' => date('Y-m-d H:i:s')],
            'id = :id',
            [':id' => $actionId]
        );

        $automationProcessed++;
    } catch (\Throwable $e) {
        error_log("Erro ao processar acao agendada {$actionId}: " . $e->getMessage());

        // Marcar como falha se tentou muitas vezes
        $attempts = (int) $action['attempts'] + 1;
        $status = $attempts >= 3 ? 'failed' : 'pending';

        Database::update(
            'automation_scheduled_actions',
            ['status' => $status, 'attempts' => $attempts, 'error_message' => mb_substr($e->getMessage(), 0, 500)],
            'id = :id',
            [':id' => $actionId]
        );

        $automationErrors++;
    }
}

echo "Automacoes: " . count($scheduledActions) . " processadas, {$automationProcessed} sucesso, {$automationErrors} erro\n";

/**
 * PARTE 3: Self-healing de vinculo LID <-> telefone.
 * Busca leads criados nos ultimos 30 dias sem LID e tenta resolver via
 * Evolution (whatsappNumbers) com backoff exponencial por lead.
 * Depende da migration 025. Se as colunas nao existirem, ignora silenciosamente.
 */
echo "Processando self-healing LID...\n";

$lidHealed = 0;
$lidErrors = 0;

try {
    $colCheck = Database::fetch(
        "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'leads'
           AND COLUMN_NAME = 'lid_lookup_attempts'"
    );
    $hasLookupColumns = $colCheck && (int) ($colCheck['c'] ?? 0) > 0;

    if ($hasLookupColumns) {
        // Backoff exponencial: (2^attempts) minutos. Maximo 5 tentativas.
        $candidates = Database::fetchAll(
            "SELECT id, tenant_id, phone_normalized
             FROM leads
             WHERE tenant_id IS NOT NULL
               AND phone_normalized IS NOT NULL
               AND phone_normalized NOT LIKE 'lid:%'
               AND whatsapp_lid_jid IS NULL
               AND deleted_at IS NULL
               AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               AND COALESCE(lid_lookup_attempts, 0) < 5
               AND (
                    lid_lookup_last_at IS NULL
                    OR lid_lookup_last_at <= DATE_SUB(NOW(), INTERVAL POW(2, COALESCE(lid_lookup_attempts, 0)) MINUTE)
               )
             ORDER BY id DESC
             LIMIT 30"
        );

        // Agrupar por tenant para reaproveitar instancia e reduzir chamadas Evolution.
        $byTenant = [];
        foreach ($candidates as $cand) {
            $tid = (int) $cand['tenant_id'];
            $byTenant[$tid][] = $cand;
        }

        foreach ($byTenant as $tid => $leads) {
            $phones = array_values(array_unique(array_map(
                static fn ($l) => (string) $l['phone_normalized'],
                $leads
            )));

            try {
                $results = LidResolverService::batchResolveLidsForPhones($tid, $phones);
            } catch (\Throwable $e) {
                error_log('[Worker] batchResolveLidsForPhones tenant=' . $tid . ': ' . $e->getMessage());
                $lidErrors += count($leads);
                continue;
            }

            foreach ($leads as $lead) {
                $leadId = (int) $lead['id'];
                $phone = (string) $lead['phone_normalized'];
                $norm = PhoneHelper::normalize($phone) ?: $phone;
                $info = $results[$norm] ?? ($results[$phone] ?? null);
                $lidJid = $info['lid_jid'] ?? null;

                if ($lidJid && is_string($lidJid) && $lidJid !== '') {
                    try {
                        Database::update(
                            'leads',
                            [
                                'whatsapp_lid_jid' => $lidJid,
                                'lid_lookup_attempts' => 0,
                                'lid_lookup_last_at' => date('Y-m-d H:i:s'),
                            ],
                            'id = :id AND tenant_id = :tid',
                            [':id' => $leadId, ':tid' => $tid]
                        );
                        LidResolverService::reconcileLeadsOnMapping($tid, $lidJid, $norm);
                        $lidHealed++;
                    } catch (\Throwable $e) {
                        error_log('[Worker] lid healing lead=' . $leadId . ': ' . $e->getMessage());
                        $lidErrors++;
                    }
                } else {
                    try {
                        LidResolverService::markLidLookupAttempt($tid, $leadId);
                    } catch (\Throwable $e) {
                        // silencioso
                    }
                }
            }
        }

        echo "LID healing: " . count($candidates) . " candidatos, {$lidHealed} vinculados, {$lidErrors} erros\n";
    } else {
        echo "LID healing: migration 025 nao aplicada, ignorando.\n";
    }
} catch (\Throwable $e) {
    error_log('[Worker] self-healing LID: ' . $e->getMessage());
    echo "LID healing: erro fatal, veja log.\n";
}

/**
 * PARTE 4: fila async de automacoes (opcional, AUTOMATION_ASYNC=1).
 * Processa automation_job_queue em batches de 50 com SKIP LOCKED.
 * Se a tabela nao existir (migration 027 nao aplicada), ignora.
 */
$jobProcessed = 0;
$jobErrors = 0;

try {
    $hasJobQueue = (bool) Database::fetch(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'automation_job_queue'"
    );
    // Database::fetch retorna array, nao bool. Corrige:
    $hasJobRow = Database::fetch(
        "SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'automation_job_queue'"
    );
    $hasJobQueue = $hasJobRow && (int) ($hasJobRow['c'] ?? 0) > 0;

    if ($hasJobQueue) {
        $jobs = [];
        try {
            $pdo->beginTransaction();
            $jobs = Database::fetchAll(
                "SELECT * FROM automation_job_queue
                 WHERE status = 'pending' AND scheduled_for <= NOW() AND attempts < 5
                 ORDER BY scheduled_for ASC
                 LIMIT 50
                 FOR UPDATE SKIP LOCKED"
            );
            if ($jobs) {
                $ids = array_map(static fn ($j) => (int) $j['id'], $jobs);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                Database::query(
                    "UPDATE automation_job_queue
                     SET status = 'processing', attempts = attempts + 1
                     WHERE id IN ({$placeholders})",
                    $ids
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $jobs = [];
            error_log('[Worker] automation_job_queue pickup: ' . $e->getMessage());
        }

        foreach ($jobs as $job) {
            $payload = json_decode((string) $job['payload_json'], true) ?: [];
            try {
                AutomationEngine::dispatchSync(
                    (int) $job['tenant_id'],
                    (string) $job['trigger_event'],
                    $payload
                );
                Database::update(
                    'automation_job_queue',
                    ['status' => 'completed', 'last_error' => null],
                    'id = :id',
                    [':id' => (int) $job['id']]
                );
                $jobProcessed++;
            } catch (\Throwable $e) {
                $attempts = (int) ($job['attempts'] ?? 0) + 1;
                $nextStatus = $attempts >= 5 ? 'failed' : 'pending';
                Database::update(
                    'automation_job_queue',
                    [
                        'status' => $nextStatus,
                        'last_error' => mb_substr($e->getMessage(), 0, 2000),
                        // Backoff 1,2,4,8,16 minutos
                        'scheduled_for' => date('Y-m-d H:i:s', time() + (int) pow(2, min($attempts, 5)) * 60),
                    ],
                    'id = :id',
                    [':id' => (int) $job['id']]
                );
                $jobErrors++;
                error_log('[Worker] automation_job_queue job=' . $job['id'] . ': ' . $e->getMessage());
            }
        }
        echo "Jobs automation async: " . count($jobs) . " processados, {$jobProcessed} sucesso, {$jobErrors} erro\n";
    }
} catch (\Throwable $e) {
    error_log('[Worker] automation async queue: ' . $e->getMessage());
}

/**
 * PARTE 5: Fila de e-mail (SMTP)
 */
echo "Processando fila de e-mail...\n";
try {
    $mailRes = SmtpProcessor::runBatch(25);
    echo "Email outbox: enviados={$mailRes['sent']} falha={$mailRes['failed']} skipped=" . ($mailRes['skipped'] ?? 0) . "\n";
} catch (\Throwable $e) {
    error_log('[Worker] email outbox: ' . $e->getMessage());
}

echo "=== Worker finalizado: " . date('Y-m-d H:i:s') . " ===\n";
echo "Total: " . ($totalProcessed + $automationProcessed + $lidHealed + $jobProcessed) . " sucesso, " . ($totalErrors + $automationErrors + $lidErrors + $jobErrors) . " erro\n";
