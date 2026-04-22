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
            'contentFullBleed' => true,
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
            'contentFullBleed' => true,
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

            $entryStage = [
                'id' => 0,
                'name' => 'Leads de Entrada',
                'slug' => 'entry',
                'stage_type' => 'entry',
                'color_token' => '#f59e0b',
                'position' => 0,
                'is_default' => 0,
                'is_final' => 0,
                'win_probability' => null,
            ];

            $entryWhere = ['l.pipeline_id = :entry_pipeline', 'l.deleted_at IS NULL', 'l.tenant_id = :tenant_id', 'l.pending_identity_resolution = 1'];
            $entryParams = TenantAwareDatabase::mergeTenantParams([':entry_pipeline' => $pipelineId]);
            if ($search) {
                $entryWhere[] = '(l.name LIKE :esearch OR l.phone LIKE :esearch OR l.email LIKE :esearch OR l.phone_normalized LIKE :esearch)';
                $entryParams[':esearch'] = '%' . $search . '%';
            }
            if ($assignedUserId) {
                $entryWhere[] = 'l.assigned_user_id = :euid';
                $entryParams[':euid'] = $assignedUserId;
            }
            if ($tagId !== null && $tagId !== '') {
                $entryWhere[] = 'EXISTS (SELECT 1 FROM lead_tag_items lti WHERE lti.lead_id = l.id AND lti.tag_id = :etag AND lti.tenant_id = l.tenant_id)';
                $entryParams[':etag'] = (int) $tagId;
            }
            $entryWhereSql = implode(' AND ', $entryWhere);
            $entryCountRow = TenantAwareDatabase::fetch(
                "SELECT COUNT(*) as c FROM leads l WHERE {$entryWhereSql}",
                $entryParams
            );
            $entryLeads = TenantAwareDatabase::fetchAll(
                "SELECT l.id, l.name, l.phone, l.phone_normalized, l.email, l.value, l.score,
                        l.temperature, l.status, l.next_action_at, l.source, l.product_interest,
                        l.assigned_user_id, l.pending_identity_resolution, l.metadata_json,
                        u.name as assigned_user_name, u.avatar_url,
                        (SELECT COUNT(*) FROM lead_tag_items lti2 WHERE lti2.lead_id = l.id AND lti2.tenant_id = l.tenant_id) as tags_count,
                        (SELECT SUBSTRING(GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR '||'), 1, 200)
                         FROM lead_tag_items lti
                         INNER JOIN lead_tags t ON t.id = lti.tag_id AND t.tenant_id = l.tenant_id
                         WHERE lti.lead_id = l.id AND lti.tenant_id = l.tenant_id) as tag_labels,
                        (SELECT c2.last_message_preview FROM conversations c2
                         WHERE c2.lead_id = l.id AND c2.tenant_id = l.tenant_id
                         ORDER BY c2.last_message_at IS NULL, c2.last_message_at DESC, c2.id DESC LIMIT 1) as inbox_preview
                 FROM leads l
                 LEFT JOIN users u ON l.assigned_user_id = u.id AND u.tenant_id = l.tenant_id
                 WHERE {$entryWhereSql}
                 ORDER BY l.created_at DESC
                 LIMIT {$limit}",
                $entryParams
            );

            $result[] = [
                'stage' => $entryStage,
                'leads' => $entryLeads,
                'count' => count($entryLeads),
                'total_in_stage' => (int) ($entryCountRow['c'] ?? 0),
                'total_value' => array_sum(array_column($entryLeads, 'value')),
            ];

            foreach ($stages as $stage) {
                $where = ['l.stage_id = :stage_id', 'l.deleted_at IS NULL', 'l.tenant_id = :tenant_id', 'l.pending_identity_resolution = 0'];
                $params = TenantAwareDatabase::mergeTenantParams([':stage_id' => $stage['id']]);

                if ($search) {
                    $where[] = '(l.name LIKE :search OR l.phone LIKE :search OR l.email LIKE :search OR l.phone_normalized LIKE :search)';
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
                            l.assigned_user_id, l.pending_identity_resolution, l.metadata_json,
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
