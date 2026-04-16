<?php

namespace App\Services\Automation;

use App\Core\App;
use App\Core\Database;
use App\Helpers\PhoneHelper;
use App\Services\WhatsApp\ChatService;
use App\Core\TenantContext;

class AutomationEngine
{
    /**
     * Dispara regras ativas para o tenant.
     * Processa tanto fluxos visuais (flow_json) quanto regras legadas.
     *
     * @param array<string, mixed> $payload
     */
    public static function dispatch(int $tenantId, string $trigger, array $payload): void
    {
        $rules = Database::fetchAll(
            'SELECT * FROM automation_rules WHERE tenant_id = :tid AND is_active = 1 AND trigger_event = :tr ORDER BY priority DESC, id ASC',
            [':tid' => $tenantId, ':tr' => $trigger]
        );

        foreach ($rules as $rule) {
            // Se tem flow_json, usar engine novo de fluxo visual
            $flowJson = $rule['flow_json'] ?? null;
            if (!empty($flowJson)) {
                self::dispatchVisualFlow($tenantId, $rule, $trigger, $payload);
            } else {
                // Legado: usar acoes da tabela automation_actions
                if (!self::matchLegacyConditions((string) ($rule['conditions_json'] ?? ''), $payload)) {
                    continue;
                }
                self::runLegacyActions($tenantId, $rule, $payload);
            }
        }
    }

    /**
     * Dispara um fluxo visual (com flow_json).
     */
    private static function dispatchVisualFlow(int $tenantId, array $rule, string $trigger, array $payload): void
    {
        $flow = json_decode($rule['flow_json'] ?? '{}', true);
        if (!is_array($flow) || empty($flow['nodes'])) {
            return;
        }

        // Encontrar o trigger node
        $triggerNode = null;
        foreach ($flow['nodes'] as $node) {
            if ($node['type'] === 'trigger' && $node['subtype'] === $trigger) {
                $triggerNode = $node;
                break;
            }
        }

        if (!$triggerNode) {
            return;
        }

        // Verificar configuracao especifica do trigger
        if (!self::matchTriggerConfig($triggerNode['config'] ?? [], $payload)) {
            return;
        }

        // Criar registro de execucao
        $executionId = Database::insert('automation_executions', [
            'tenant_id' => $tenantId,
            'automation_rule_id' => (int) $rule['id'],
            'lead_id' => isset($payload['lead_id']) ? (int) $payload['lead_id'] : null,
            'status' => 'running',
            'current_node_id' => $triggerNode['id'],
            'context_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        // Executar proximo node
        $nextId = $triggerNode['next'] ?? null;
        if ($nextId) {
            self::executeNode($tenantId, $executionId, $nextId, $payload, $flow['nodes']);
        } else {
            // Flow so tem trigger, completar
            self::completeExecution($executionId);
        }
    }

    /**
     * Retoma execucao a partir de um node (usado pelo worker apos delay).
     */
    public static function resumeFromNode(int $tenantId, int $executionId, string $nodeId, array $payload, ?array $nodes = null): void
    {
        if ($nodes === null) {
            $exec = Database::fetch(
                'SELECT ae.*, ar.flow_json FROM automation_executions ae
                 JOIN automation_rules ar ON ar.id = ae.automation_rule_id
                 WHERE ae.id = :id AND ae.tenant_id = :tid',
                [':id' => $executionId, ':tid' => $tenantId]
            );
            if (!$exec) {
                App::logError('Execution not found: ' . $executionId);
                return;
            }
            $flow = json_decode($exec['flow_json'] ?? '{}', true);
            $nodes = $flow['nodes'] ?? [];
            $payload = array_merge(json_decode($exec['context_json'] ?? '{}', true) ?: [], $payload);
        }

        // Atualizar execucao para running
        Database::update(
            'automation_executions',
            ['status' => 'running', 'current_node_id' => $nodeId],
            'id = :id',
            [':id' => $executionId]
        );

        self::executeNode($tenantId, $executionId, $nodeId, $payload, $nodes);
    }

    /**
     * Executa um node especifico e continua o fluxo.
     * @param array<string> $visitedNodes Array de IDs de nodes ja visitados (para deteccao de ciclos)
     * @param int $depth Profundidade atual da execucao
     */
    private static function executeNode(int $tenantId, int $executionId, string $nodeId, array $payload, array $nodes, array $visitedNodes = [], int $depth = 0): void
    {
        // Limite de profundidade para prevenir recursao infinita (ciclos no grafo)
        $maxDepth = 100;
        if ($depth > $maxDepth) {
            App::logError('Automation cycle detected or max depth exceeded for execution: ' . $executionId);
            self::completeExecution($executionId);
            return;
        }

        // Deteccao de ciclos: se ja visitou este node, completar execucao
        if (in_array($nodeId, $visitedNodes, true)) {
            App::logError('Automation cycle detected at node: ' . $nodeId . ', execution: ' . $executionId);
            self::completeExecution($executionId);
            return;
        }

        // Encontrar o node
        $node = null;
        foreach ($nodes as $n) {
            if ($n['id'] === $nodeId) {
                $node = $n;
                break;
            }
        }

        if (!$node) {
            // Node nao encontrado, completar execucao
            self::completeExecution($executionId);
            return;
        }

        // Marcar node como visitado
        $visitedNodes[] = $nodeId;

        // Atualizar node atual
        Database::update(
            'automation_executions',
            ['current_node_id' => $nodeId],
            'id = :id',
            [':id' => $executionId]
        );

        $config = $node['config'] ?? [];
        $nextId = null;

        switch ($node['type']) {
            case 'condition':
                $result = self::evaluateCondition($node['subtype'], $config, $payload, $tenantId);
                $nextId = $result ? ($node['yes'] ?? null) : ($node['no'] ?? null);
                break;

            case 'action':
                $exec = Database::fetch('SELECT automation_rule_id FROM automation_executions WHERE id = :id', [':id' => $executionId]);
                $ruleId = $exec ? (int) $exec['automation_rule_id'] : 0;
                self::executeAction($node['subtype'], $config, $payload, $tenantId, $ruleId);
                $nextId = $node['next'] ?? null;
                break;

            case 'delay':
                // Agendar para execucao futura
                self::scheduleDelay($executionId, $nodeId, $config, $node['next'] ?? null);
                return; // Para aqui, worker vai retomar

            default:
                $nextId = $node['next'] ?? null;
        }

        // Continuar para o proximo node
        if ($nextId) {
            self::executeNode($tenantId, $executionId, $nextId, $payload, $nodes, $visitedNodes, $depth + 1);
        } else {
            // Fim do fluxo
            self::completeExecution($executionId);
        }
    }

    /**
     * Aguarda um delay agendando para execucao futura.
     */
    private static function scheduleDelay(int $executionId, string $nodeId, array $config, ?string $nextNodeId): void
    {
        $amount = (int) ($config['amount'] ?? 1);
        $unit = (string) ($config['unit'] ?? 'hours');

        // Calcular horario de execucao
        $interval = match ($unit) {
            'minutes' => "+{$amount} minutes",
            'hours' => "+{$amount} hours",
            'days' => "+{$amount} days",
            default => "+{$amount} hours",
        };

        $scheduledFor = date('Y-m-d H:i:s', strtotime($interval));

        // Inserir na fila
        Database::insert('automation_scheduled_actions', [
            'execution_id' => $executionId,
            'node_id' => $nodeId,
            'scheduled_for' => $scheduledFor,
            'status' => 'pending',
        ]);

        // Atualizar execucao para paused
        Database::update(
            'automation_executions',
            ['status' => 'paused'],
            'id = :id',
            [':id' => $executionId]
        );

        // Se tem proximo node, salvar no contexto para quando retomar
        if ($nextNodeId) {
            $exec = Database::fetch('SELECT context_json FROM automation_executions WHERE id = :id', [':id' => $executionId]);
            $context = json_decode($exec['context_json'] ?? '{}', true) ?: [];
            $context['_resume_after_delay'] = $nextNodeId;
            Database::update(
                'automation_executions',
                ['context_json' => json_encode($context, JSON_UNESCAPED_UNICODE)],
                'id = :id',
                [':id' => $executionId]
            );
        }
    }

    /**
     * Completa uma execucao.
     */
    private static function completeExecution(int $executionId): void
    {
        Database::update(
            'automation_executions',
            ['status' => 'completed', 'completed_at' => date('Y-m-d H:i:s'), 'current_node_id' => null],
            'id = :id',
            [':id' => $executionId]
        );
    }

    /**
     * Avalia uma condicao.
     */
    private static function evaluateCondition(string $subtype, array $config, array $payload, int $tenantId): bool
    {
        $leadId = (int) ($payload['lead_id'] ?? 0);

        switch ($subtype) {
            case 'stage_is':
                $expectedStage = (int) ($config['stage_id'] ?? 0);
                if ($leadId && $expectedStage) {
                    $lead = Database::fetch(
                        'SELECT stage_id FROM leads WHERE id = :id AND tenant_id = :tid',
                        [':id' => $leadId, ':tid' => $tenantId]
                    );
                    return $lead && (int) $lead['stage_id'] === $expectedStage;
                }
                return false;

            case 'has_tag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($leadId && $tagId) {
                    $hasTag = Database::fetch(
                        'SELECT 1 FROM lead_tag_items WHERE lead_id = :lid AND tag_id = :tid LIMIT 1',
                        [':lid' => $leadId, ':tid' => $tagId]
                    );
                    return (bool) $hasTag;
                }
                return false;

            case 'message_contains':
                $keyword = strtolower((string) ($config['keyword'] ?? ''));
                $message = strtolower((string) ($payload['message'] ?? ''));
                return $keyword !== '' && str_contains($message, $keyword);

            case 'field_equals':
                $field = (string) ($config['field'] ?? '');
                $expectedValue = (string) ($config['value'] ?? '');
                if ($leadId && $field) {
                    $lead = Database::fetch(
                        'SELECT * FROM leads WHERE id = :id AND tenant_id = :tid',
                        [':id' => $leadId, ':tid' => $tenantId]
                    );
                    if ($lead) {
                        $actualValue = (string) ($lead[$field] ?? '');
                        return strtolower($actualValue) === strtolower($expectedValue);
                    }
                }
                return false;

            default:
                return true;
        }
    }

    /**
     * Executa uma acao.
     */
    private static function executeAction(string $subtype, array $config, array $payload, int $tenantId, int $ruleId): void
    {
        $leadId = (int) ($payload['lead_id'] ?? 0);

        switch ($subtype) {
            case 'move_stage':
                if (!empty($payload['manual_move'])) {
                    break;
                }
                $stageId = (int) ($config['stage_id'] ?? 0);
                if ($leadId && $stageId) {
                    Database::query(
                        'UPDATE leads SET stage_id = :sid WHERE id = :lid AND tenant_id = :tid',
                        [':sid' => $stageId, ':lid' => $leadId, ':tid' => $tenantId]
                    );
                }
                break;

            case 'add_tag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($leadId && $tagId) {
                    // Verificar se ja existe
                    $exists = Database::fetch(
                        'SELECT 1 FROM lead_tag_items WHERE lead_id = :lid AND tag_id = :tid LIMIT 1',
                        [':lid' => $leadId, ':tid' => $tagId]
                    );
                    if (!$exists) {
                        Database::insert('lead_tag_items', [
                            'lead_id' => $leadId,
                            'tag_id' => $tagId,
                        ]);
                    }
                }
                break;

            case 'remove_tag':
                $tagId = (int) ($config['tag_id'] ?? 0);
                if ($leadId && $tagId) {
                    Database::query(
                        'DELETE FROM lead_tag_items WHERE lead_id = :lid AND tag_id = :tid',
                        [':lid' => $leadId, ':tid' => $tagId]
                    );
                }
                break;

            case 'assign_user':
                $userId = (int) ($config['user_id'] ?? 0);
                if ($leadId && $userId) {
                    Database::query(
                        'UPDATE leads SET assigned_user_id = :uid WHERE id = :lid AND tenant_id = :tid',
                        [':uid' => $userId, ':lid' => $leadId, ':tid' => $tenantId]
                    );
                }
                break;

            case 'send_whatsapp':
                if ($leadId) {
                    self::sendWhatsAppMessage($tenantId, $leadId, $config);
                }
                break;

            case 'send_webhook':
                $url = (string) ($config['url'] ?? '');
                if ($url !== '') {
                    $method = strtoupper((string) ($config['method'] ?? 'POST'));
                    self::sendWebhook($url, $payload, $tenantId, $method);
                }
                break;
        }

        // Log da acao
        try {
            Database::insert('automation_logs', [
                'tenant_id' => $tenantId,
                'automation_rule_id' => $ruleId > 0 ? $ruleId : null,
                'lead_id' => $leadId ?: null,
                'trigger_data_json' => json_encode(['action' => $subtype, 'config' => $config], JSON_UNESCAPED_UNICODE),
                'actions_executed_json' => json_encode([['action' => $subtype, 'ok' => true]], JSON_UNESCAPED_UNICODE),
                'status' => 'ok',
            ]);
        } catch (\Throwable $e) {
            App::logError('Automation action log', $e);
        }
    }

    /**
     * Envia mensagem WhatsApp via Evolution API.
     */
    private static function sendWhatsAppMessage(int $tenantId, int $leadId, array $config): void
    {
        // Buscar instancia ativa do tenant
        $instance = Database::fetch(
            "SELECT * FROM whatsapp_instances WHERE tenant_id = :tid AND status = 'connected' ORDER BY id ASC LIMIT 1",
            [':tid' => $tenantId]
        );

        if (!$instance) {
            App::logError('No WhatsApp instance for tenant: ' . $tenantId);
            return;
        }

        // Buscar lead
        $lead = Database::fetch(
            'SELECT phone, phone_normalized, name FROM leads WHERE id = :id AND tenant_id = :tid',
            [':id' => $leadId, ':tid' => $tenantId]
        );

        if (!$lead) {
            return;
        }

        // Obter telefone
        $phone = $lead['phone_normalized'] ?: $lead['phone'];
        if (!$phone) {
            return;
        }

        $phone = preg_replace('/\D/', '', (string) $phone);
        if (empty($phone)) {
            return;
        }

        // Preparar mensagem
        $message = '';
        if (!empty($config['template_id'])) {
            $template = Database::fetch(
                'SELECT content FROM message_templates WHERE id = :id AND tenant_id = :tid',
                [':id' => (int) $config['template_id'], ':tid' => $tenantId]
            );
            if ($template) {
                $message = $template['content'];
            }
        }
        if (empty($message) && !empty($config['message'])) {
            $message = (string) $config['message'];
        }

        if (empty($message)) {
            return;
        }

        // Substituir variaveis
        $message = str_replace('{{nome}}', $lead['name'] ?? 'Cliente', $message);
        $message = str_replace('{{name}}', $lead['name'] ?? 'Cliente', $message);

        try {
            $hadCtx = TenantContext::hasTenant();
            if (!$hadCtx) {
                TenantContext::setTenant($tenantId, ['id' => $tenantId]);
            }
            try {
                $chat = new ChatService();
                $out = $chat->sendToLead($leadId, $message, 'bot', null);
                if (!$out['ok']) {
                    App::logError('WhatsApp automation send: ' . ($out['message'] ?? 'fail'));
                }
            } finally {
                if (!$hadCtx) {
                    TenantContext::clear();
                }
            }
        } catch (\Throwable $e) {
            App::logError('WhatsApp automation send', $e);
        }
    }

    /**
     * Envia webhook.
     */
    private static function sendWebhook(string $url, array $payload, int $tenantId, string $method = 'POST'): void
    {
        $data = array_merge($payload, [
            'tenant_id' => $tenantId,
            'timestamp' => time(),
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            return;
        }

        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_POST, false);
            $urlWithParams = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $urlWithParams);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Verifica configuracao especifica do trigger.
     */
    private static function matchTriggerConfig(array $config, array $payload): bool
    {
        // Stage especifico
        if (!empty($config['stage_id']) && !empty($payload['stage_id'])) {
            if ((int) $config['stage_id'] !== (int) $payload['stage_id']) {
                return false;
            }
        }

        // Tag especifica
        if (!empty($config['tag_id']) && !empty($payload['tag_id'])) {
            if ((int) $config['tag_id'] !== (int) $payload['tag_id']) {
                return false;
            }
        }

        // Palavra-chave
        if (!empty($config['keyword']) && !empty($payload['message'])) {
            $keyword = strtolower((string) $config['keyword']);
            $message = strtolower((string) $payload['message']);
            if (!str_contains($message, $keyword)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Match de condicoes legadas (para compatibilidade).
     */
    private static function matchLegacyConditions(string $conditionsJson, array $payload): bool
    {
        if ($conditionsJson === '' || $conditionsJson === 'null') {
            return true;
        }
        $c = json_decode($conditionsJson, true);
        if (!is_array($c)) {
            return true;
        }

        if (isset($c['pipeline_id']) && (int) ($payload['pipeline_id'] ?? 0) !== (int) $c['pipeline_id']) {
            return false;
        }
        if (!empty($c['keyword'])) {
            $msg = strtolower((string) ($payload['message'] ?? ''));
            if (!str_contains($msg, strtolower((string) $c['keyword']))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Executa acoes legadas.
     */
    private static function runLegacyActions(int $tenantId, array $rule, array $payload): void
    {
        $actions = Database::fetchAll(
            'SELECT * FROM automation_actions WHERE automation_rule_id = :id ORDER BY position ASC, id ASC',
            [':id' => $rule['id']]
        );
        $log = [];

        foreach ($actions as $act) {
            $cfg = json_decode((string) ($act['action_config_json'] ?? '{}'), true) ?: [];
            $type = (string) $act['action_type'];

            if ($type === 'webhook_n8n' || $type === 'send_webhook') {
                $url = (string) ($cfg['url'] ?? '');
                if ($url !== '') {
                    self::sendWebhook($url, $payload, $tenantId);
                    $log[] = ['action' => $type, 'ok' => true];
                }
            } elseif ($type === 'move_stage' && !empty($payload['lead_id'])) {
                if (!empty($payload['manual_move'])) {
                    continue;
                }
                $sid = (int) ($cfg['stage_id'] ?? 0);
                if ($sid > 0) {
                    Database::query(
                        'UPDATE leads SET stage_id = :sid WHERE id = :lid AND tenant_id = :tid',
                        [':sid' => $sid, ':lid' => (int) $payload['lead_id'], ':tid' => $tenantId]
                    );
                    $log[] = ['action' => 'move_stage', 'stage_id' => $sid, 'ok' => true];
                }
            }
        }

        if ($log !== []) {
            try {
                Database::insert('automation_logs', [
                    'tenant_id' => $tenantId,
                    'automation_rule_id' => (int) $rule['id'],
                    'lead_id' => isset($payload['lead_id']) ? (int) $payload['lead_id'] : null,
                    'trigger_data_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'actions_executed_json' => json_encode($log, JSON_UNESCAPED_UNICODE),
                    'status' => 'ok',
                ]);
            } catch (\Throwable $e) {
                App::logError('automation_logs', $e);
            }
        }
    }
}
