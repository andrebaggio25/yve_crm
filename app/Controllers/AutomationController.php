<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\TenantAwareDatabase;
use App\Core\TenantContext;

class AutomationController
{
    /**
     * Pagina de lista de automacoes (cards visuais).
     */
    public function page(Request $request, Response $response): void
    {
        $response->view('settings.automations', [
            'title' => 'Automacoes',
            'pageTitle' => 'Automacoes',
            'scripts' => ['automations'],
        ]);
    }

    /**
     * Pagina do builder visual (editor de fluxo).
     */
    public function builderPage(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);

        $response->view('settings.automation-builder', [
            'title' => $id > 0 ? 'Editar Automacao' : 'Nova Automacao',
            'pageTitle' => $id > 0 ? 'Editar Fluxo' : 'Novo Fluxo',
            'automationId' => $id,
            'scripts' => ['automation-builder'],
        ]);
    }

    /**
     * Lista de automacoes simplificada (para cards).
     */
    public function apiList(Request $request, Response $response): void
    {
        try {
            $rows = TenantAwareDatabase::fetchAll(
                'SELECT r.* FROM automation_rules r WHERE r.tenant_id = :tenant_id ORDER BY r.priority DESC, r.id DESC',
                TenantAwareDatabase::mergeTenantParams()
            );

            // Contar execucoes recentes para cada regra
            $stats = [];
            if (!empty($rows)) {
                $ids = array_column($rows, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $counts = TenantAwareDatabase::fetchAll(
                    "SELECT automation_rule_id, COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                     FROM automation_executions
                     WHERE automation_rule_id IN ({$placeholders}) AND started_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                     GROUP BY automation_rule_id",
                    $ids
                );
                foreach ($counts as $c) {
                    $stats[$c['automation_rule_id']] = $c;
                }
            }

            foreach ($rows as &$row) {
                $row['exec_total'] = $stats[$row['id']]['total'] ?? 0;
                $row['exec_completed'] = $stats[$row['id']]['completed'] ?? 0;
                $row['has_flow'] = !empty($row['flow_json']);
            }

            $response->jsonSuccess(['rules' => $rows]);
        } catch (\Throwable $e) {
            App::logError('Automation list', $e);
            $response->jsonError('Erro', 500);
        }
    }

    /**
     * Obtem uma automacao especifica com seu flow_json.
     */
    public function apiGet(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            $rule = TenantAwareDatabase::fetch(
                'SELECT * FROM automation_rules WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$rule) {
                $response->jsonError('Nao encontrado', 404);
                return;
            }

            // Decodificar JSONs
            $rule['trigger_config'] = json_decode($rule['trigger_config_json'] ?? '{}', true) ?: [];
            $rule['conditions'] = json_decode($rule['conditions_json'] ?? '[]', true) ?: [];
            $rule['flow'] = json_decode($rule['flow_json'] ?? '{}', true) ?: null;

            // Se nao tem flow ainda, retornar null para o frontend criar padrao
            if (empty($rule['flow_json'])) {
                $rule['flow'] = null;
            }

            unset($rule['trigger_config_json'], $rule['conditions_json'], $rule['flow_json']);

            $response->jsonSuccess(['rule' => $rule]);
        } catch (\Throwable $e) {
            App::logError('Automation get', $e);
            $response->jsonError('Erro', 500);
        }
    }

    /**
     * Salva uma automacao (incluindo flow_json).
     */
    public function apiSave(Request $request, Response $response): void
    {
        $data = $request->getJsonInput() ?? [];
        $id = isset($data['id']) ? (int) $data['id'] : 0;
        $name = trim((string) ($data['name'] ?? ''));
        $trigger = trim((string) ($data['trigger_event'] ?? ''));
        $flow = $data['flow'] ?? null;

        if ($name === '' || $trigger === '') {
            $response->jsonError('Nome e trigger obrigatorios', 422);
            return;
        }

        // Validar flow_json basico
        if ($flow !== null && (!is_array($flow) || !isset($flow['nodes']))) {
            $response->jsonError('Flow invalido', 422);
            return;
        }

        $user = Session::user();

        try {
            // Extrair dados legados do flow (para compatibilidade)
            $conditions = [];
            $triggerConfig = [];
            if ($flow !== null && isset($flow['nodes'])) {
                foreach ($flow['nodes'] as $node) {
                    if ($node['type'] === 'trigger') {
                        $triggerConfig = $node['config'] ?? [];
                    }
                    if ($node['type'] === 'condition') {
                        // Adicionar a conditions_json para motor legado
                        $conditions[] = [
                            'node_id' => $node['id'],
                            'type' => $node['subtype'],
                            'config' => $node['config'],
                        ];
                    }
                }
            }

            if ($id > 0) {
                // Verificar existencia
                $exists = TenantAwareDatabase::fetch(
                    'SELECT id FROM automation_rules WHERE id = :id AND tenant_id = :tenant_id',
                    TenantAwareDatabase::mergeTenantParams([':id' => $id])
                );
                if (!$exists) {
                    $response->jsonError('Nao encontrado', 404);
                    return;
                }

                TenantAwareDatabase::update(
                    'automation_rules',
                    [
                        'name' => $name,
                        'description' => (string) ($data['description'] ?? ''),
                        'trigger_event' => $trigger,
                        'trigger_config_json' => json_encode($triggerConfig),
                        'conditions_json' => json_encode($conditions),
                        'flow_json' => $flow !== null ? json_encode($flow, JSON_UNESCAPED_UNICODE) : null,
                        'is_active' => !empty($data['is_active']) ? 1 : 0,
                        'priority' => (int) ($data['priority'] ?? 0),
                    ],
                    'id = :id',
                    [':id' => $id]
                );
                $ruleId = $id;

                // Limpar acoes legadas se estamos usando flow_json
                if ($flow !== null) {
                    TenantAwareDatabase::query(
                        'DELETE FROM automation_actions WHERE automation_rule_id = :id',
                        [':id' => $id]
                    );
                }
            } else {
                $ruleId = TenantAwareDatabase::insert('automation_rules', [
                    'name' => $name,
                    'description' => (string) ($data['description'] ?? ''),
                    'trigger_event' => $trigger,
                    'trigger_config_json' => json_encode($triggerConfig),
                    'conditions_json' => json_encode($conditions),
                    'flow_json' => $flow !== null ? json_encode($flow, JSON_UNESCAPED_UNICODE) : null,
                    'is_active' => !empty($data['is_active']) ? 1 : 0,
                    'priority' => (int) ($data['priority'] ?? 0),
                    'created_by' => (int) ($user['id'] ?? 0),
                ]);
            }

            // Se flow eh null, manter acoes legadas (compatibilidade)
            if ($flow === null && isset($data['actions']) && is_array($data['actions'])) {
                // Limpar e recriar acoes legadas
                TenantAwareDatabase::query(
                    'DELETE FROM automation_actions WHERE automation_rule_id = :id',
                    [':id' => $ruleId]
                );
                $pos = 0;
                foreach ($data['actions'] as $a) {
                    if (!is_array($a)) {
                        continue;
                    }
                    $type = (string) ($a['action_type'] ?? '');
                    if ($type === '') {
                        continue;
                    }
                    TenantAwareDatabase::insert('automation_actions', [
                        'automation_rule_id' => $ruleId,
                        'action_type' => $type,
                        'action_config_json' => json_encode($a['action_config'] ?? new \stdClass(), JSON_UNESCAPED_UNICODE),
                        'position' => $pos++,
                    ]);
                }
            }

            $response->jsonSuccess(['id' => $ruleId], 'Salvo');
        } catch (\Throwable $e) {
            App::logError('Automation save', $e);
            $response->jsonError('Erro ao salvar', 500);
        }
    }

    /**
     * Ativa/desativa uma automacao.
     */
    public function apiToggle(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            $rule = TenantAwareDatabase::fetch(
                'SELECT id, is_active FROM automation_rules WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$rule) {
                $response->jsonError('Nao encontrado', 404);
                return;
            }

            $newStatus = empty($rule['is_active']) ? 1 : 0;

            TenantAwareDatabase::update(
                'automation_rules',
                ['is_active' => $newStatus],
                'id = :id',
                [':id' => $id]
            );

            $response->jsonSuccess(['is_active' => $newStatus], $newStatus ? 'Ativado' : 'Desativado');
        } catch (\Throwable $e) {
            App::logError('Automation toggle', $e);
            $response->jsonError('Erro', 500);
        }
    }

    /**
     * Retorna logs de execucao de uma automacao.
     */
    public function apiLogs(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        $limit = min(100, max(1, (int) ($request->getQuery('limit') ?? 20)));
        $offset = max(0, (int) ($request->getQuery('offset') ?? 0));

        try {
            // Verificar se a automacao existe e pertence ao tenant
            $exists = TenantAwareDatabase::fetch(
                'SELECT id FROM automation_rules WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );
            if (!$exists) {
                $response->jsonError('Nao encontrado', 404);
                return;
            }

            // Buscar logs legados
            $logsLegacy = TenantAwareDatabase::fetchAll(
                'SELECT * FROM automation_logs WHERE automation_rule_id = :id ORDER BY executed_at DESC LIMIT :limit OFFSET :offset',
                [':id' => $id, ':limit' => $limit, ':offset' => $offset]
            );

            // Buscar execucoes do novo sistema (se existirem)
            $executions = TenantAwareDatabase::fetchAll(
                'SELECT * FROM automation_executions WHERE automation_rule_id = :id ORDER BY started_at DESC LIMIT :limit OFFSET :offset',
                [':id' => $id, ':limit' => $limit, ':offset' => $offset]
            );

            // Contagens
            $countLegacy = TenantAwareDatabase::fetch(
                'SELECT COUNT(*) as cnt FROM automation_logs WHERE automation_rule_id = :id',
                [':id' => $id]
            );
            $countExec = TenantAwareDatabase::fetch(
                'SELECT COUNT(*) as cnt FROM automation_executions WHERE automation_rule_id = :id',
                [':id' => $id]
            );

            $response->jsonSuccess([
                'logs_legacy' => $logsLegacy,
                'executions' => $executions,
                'total' => ($countLegacy['cnt'] ?? 0) + ($countExec['cnt'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            App::logError('Automation logs', $e);
            $response->jsonError('Erro', 500);
        }
    }

    /**
     * Lista de acoes pendentes (para debug/admin).
     */
    public function apiScheduledActions(Request $request, Response $response): void
    {
        $limit = min(100, max(1, (int) ($request->getQuery('limit') ?? 20)));

        try {
            $actions = TenantAwareDatabase::fetchAll(
                'SELECT sa.*, ae.automation_rule_id, ae.lead_id
                 FROM automation_scheduled_actions sa
                 JOIN automation_executions ae ON ae.id = sa.execution_id
                 JOIN automation_rules ar ON ar.id = ae.automation_rule_id
                 WHERE ar.tenant_id = :tenant_id
                 ORDER BY sa.scheduled_for ASC
                 LIMIT :limit',
                TenantAwareDatabase::mergeTenantParams([':limit' => $limit])
            );

            $response->jsonSuccess(['actions' => $actions]);
        } catch (\Throwable $e) {
            App::logError('Scheduled actions list', $e);
            $response->jsonError('Erro', 500);
        }
    }

    public function apiDelete(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }
        try {
            $exists = TenantAwareDatabase::fetch(
                'SELECT id FROM automation_rules WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );
            if (!$exists) {
                $response->jsonError('Nao encontrado', 404);
                return;
            }

            // Limpar dados relacionados
            TenantAwareDatabase::query(
                'DELETE FROM automation_actions WHERE automation_rule_id = :id',
                [':id' => $id]
            );

            // Limpar execucoes e acoes agendadas (cascade deve lidar, mas por seguranca)
            $execIds = TenantAwareDatabase::fetchAll(
                'SELECT id FROM automation_executions WHERE automation_rule_id = :id',
                [':id' => $id]
            );
            if (!empty($execIds)) {
                $ids = array_column($execIds, 'id');
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                TenantAwareDatabase::query(
                    "DELETE FROM automation_scheduled_actions WHERE execution_id IN ({$placeholders})",
                    $ids
                );
                TenantAwareDatabase::query(
                    'DELETE FROM automation_executions WHERE automation_rule_id = :id',
                    [':id' => $id]
                );
            }

            TenantAwareDatabase::query(
                'DELETE FROM automation_logs WHERE automation_rule_id = :id',
                [':id' => $id]
            );
            TenantAwareDatabase::query(
                'DELETE FROM automation_rules WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );
            $response->jsonSuccess([], 'Removido');
        } catch (\Throwable $e) {
            App::logError('Automation delete', $e);
            $response->jsonError('Erro', 500);
        }
    }
}
