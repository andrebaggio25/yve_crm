<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantAwareDatabase;

class KanbanController
{
    public function index(Request $request, Response $response): void
    {
        $pipelineId = $request->get('pipeline_id');

        if (!$pipelineId) {
            $defaultPipeline = TenantAwareDatabase::fetch(
                'SELECT id FROM pipelines WHERE is_default = 1 AND tenant_id = :tenant_id LIMIT 1',
                TenantAwareDatabase::mergeTenantParams()
            );
            $pipelineId = $defaultPipeline ? $defaultPipeline['id'] : 1;
        }

        $response->view('kanban.index', [
            'title' => 'Kanban',
            'pageTitle' => 'Leads / Kanban',
            'pipelineId' => $pipelineId,
        ]);
    }

    public function show(Request $request, Response $response): void
    {
        $pipelineId = $request->getParam('pipeline_id');
        if ($pipelineId === null || $pipelineId === '' || !ctype_digit((string) $pipelineId)) {
            $response->redirect('/kanban');

            return;
        }

        $exists = TenantAwareDatabase::fetch(
            'SELECT id FROM pipelines WHERE id = :id AND tenant_id = :tenant_id LIMIT 1',
            TenantAwareDatabase::mergeTenantParams([':id' => (int) $pipelineId])
        );
        if (!$exists) {
            $response->redirect('/kanban');

            return;
        }

        $response->view('kanban.index', [
            'title' => 'Kanban',
            'pageTitle' => 'Leads / Kanban',
            'pipelineId' => (int) $pipelineId,
        ]);
    }

    public function apiGetKanban(Request $request, Response $response): void
    {
        $pipelineId = $request->getParam('id');

        if (!$pipelineId) {
            $response->jsonError('Pipeline ID nao fornecido', 400);

            return;
        }

        try {
            $stages = TenantAwareDatabase::fetchAll(
                "SELECT id, name, slug, stage_type, color_token, position, is_default, is_final, win_probability
                 FROM pipeline_stages 
                 WHERE pipeline_id = :pipeline_id AND tenant_id = :tenant_id
                 ORDER BY position",
                TenantAwareDatabase::mergeTenantParams([':pipeline_id' => $pipelineId])
            );

            $limit = min(max((int) $request->get('limit', 500), 1), 2500);
            $search = $request->get('search');
            $assignedUserId = $request->get('assigned_user_id');
            $tagId = $request->get('tag_id');

            $result = [];

            foreach ($stages as $stage) {
                $where = ['l.stage_id = :stage_id', 'l.deleted_at IS NULL', 'l.tenant_id = :tenant_id'];
                $params = TenantAwareDatabase::mergeTenantParams([':stage_id' => $stage['id']]);

                if ($search) {
                    $where[] = '(l.name LIKE :search OR l.phone LIKE :search OR l.email LIKE :search)';
                    $params[':search'] = '%' . $search . '%';
                }

                if ($assignedUserId) {
                    $where[] = 'l.assigned_user_id = :assigned_user_id';
                    $params[':assigned_user_id'] = $assignedUserId;
                }

                if ($tagId !== null && $tagId !== '') {
                    $where[] = 'EXISTS (SELECT 1 FROM lead_tag_items lti WHERE lti.lead_id = l.id AND lti.tag_id = :tag_id AND lti.tenant_id = l.tenant_id)';
                    $params[':tag_id'] = (int) $tagId;
                }

                $whereSql = implode(' AND ', $where);

                $countRow = TenantAwareDatabase::fetch(
                    "SELECT COUNT(*) as c FROM leads l WHERE {$whereSql}",
                    $params
                );
                $totalInStage = (int) ($countRow['c'] ?? 0);

                $leads = TenantAwareDatabase::fetchAll(
                    "SELECT l.id, l.name, l.phone, l.phone_normalized, l.email, l.value, l.score,
                            l.temperature, l.status, l.next_action_at, l.source, l.product_interest,
                            l.assigned_user_id,
                            u.name as assigned_user_name, u.avatar_url,
                            (SELECT COUNT(*) FROM lead_tag_items lti2 WHERE lti2.lead_id = l.id AND lti2.tenant_id = l.tenant_id) as tags_count,
                            (SELECT SUBSTRING(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '||'), 1, 200)
                             FROM lead_tag_items lti
                             INNER JOIN lead_tags t ON t.id = lti.tag_id AND t.tenant_id = l.tenant_id
                             WHERE lti.lead_id = l.id AND lti.tenant_id = l.tenant_id) as tag_labels
                     FROM leads l
                     LEFT JOIN users u ON l.assigned_user_id = u.id AND u.tenant_id = l.tenant_id
                     WHERE {$whereSql}
                     ORDER BY l.score DESC, l.created_at DESC
                     LIMIT {$limit}",
                    $params
                );

                $totalValue = array_sum(array_column($leads, 'value'));

                $result[] = [
                    'stage' => $stage,
                    'leads' => $leads,
                    'count' => count($leads),
                    'total_in_stage' => $totalInStage,
                    'total_value' => $totalValue,
                ];
            }

            $response->jsonSuccess([
                'columns' => $result,
                'pipeline_id' => $pipelineId,
            ]);
        } catch (\Exception $e) {
            App::logError('Erro ao carregar kanban', $e);
            $response->jsonError('Erro ao carregar kanban', 500);
        }
    }
}
