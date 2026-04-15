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

spl_autoload_register(function ($class) {
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
use App\Services\Automation\AutomationEngine;

$totalProcessed = 0;
$totalErrors = 0;

echo "=== Worker iniciado: " . date('Y-m-d H:i:s') . " ===\n";

/**
 * PARTE 1: Processar mensagens agendadas (scheduled_messages)
 */
echo "Processando mensagens agendadas...\n";

$rows = Database::fetchAll(
    "SELECT sm.*, l.tenant_id, l.phone, l.phone_normalized 
     FROM scheduled_messages sm 
     INNER JOIN leads l ON l.id = sm.lead_id 
     WHERE sm.status = 'pending' AND sm.scheduled_at <= NOW() 
     ORDER BY sm.scheduled_at ASC 
     LIMIT 40"
);

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

$scheduledActions = Database::fetchAll(
    "SELECT sa.*, ae.tenant_id, ae.automation_rule_id, ae.lead_id, ae.context_json, ar.flow_json
     FROM automation_scheduled_actions sa
     JOIN automation_executions ae ON ae.id = sa.execution_id
     JOIN automation_rules ar ON ar.id = ae.automation_rule_id
     WHERE sa.status = 'pending' AND sa.scheduled_for <= NOW()
     ORDER BY sa.scheduled_for ASC
     LIMIT 20"
);

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

echo "=== Worker finalizado: " . date('Y-m-d H:i:s') . " ===\n";
echo "Total: " . ($totalProcessed + $automationProcessed) . " sucesso, " . ($totalErrors + $automationErrors) . " erro\n";
