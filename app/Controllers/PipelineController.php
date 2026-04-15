<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantAwareDatabase;
use App\Core\TenantContext;

class PipelineController
{
    public function index(Request $request, Response $response): void
    {
        $tid = TenantContext::getEffectiveTenantId();

        $pipelines = TenantAwareDatabase::fetchAll(
            "SELECT p.*, COUNT(l.id) as leads_count
             FROM pipelines p
             LEFT JOIN leads l ON p.id = l.pipeline_id AND l.deleted_at IS NULL AND l.tenant_id = :tid1
             WHERE p.tenant_id = :tid2
             GROUP BY p.id
             ORDER BY p.created_at DESC",
            [':tid1' => $tid, ':tid2' => $tid]
        );

        $response->view('pipelines.index', [
            'title' => 'Pipelines',
            'pageTitle' => 'Gerenciamento de Pipelines',
            'pipelines' => $pipelines,
        ]);
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $tid = TenantContext::getEffectiveTenantId();

            $pipelines = TenantAwareDatabase::fetchAll(
                "SELECT p.*, COUNT(l.id) as leads_count
                 FROM pipelines p
                 LEFT JOIN leads l ON p.id = l.pipeline_id AND l.deleted_at IS NULL AND l.tenant_id = :tid1
                 WHERE p.tenant_id = :tid2
                 GROUP BY p.id
                 ORDER BY p.is_default DESC, p.created_at DESC",
                [':tid1' => $tid, ':tid2' => $tid]
            );

            foreach ($pipelines as &$pipeline) {
                $pipeline['stages'] = TenantAwareDatabase::fetchAll(
                    'SELECT * FROM pipeline_stages
                     WHERE pipeline_id = :pipeline_id AND tenant_id = :tid
                     ORDER BY position',
                    [':pipeline_id' => $pipeline['id'], ':tid' => $tid]
                );
            }

            $response->jsonSuccess(['pipelines' => $pipelines]);
        } catch (\Exception $e) {
            App::logError('Erro ao listar pipelines', $e);
            $response->jsonError('Erro ao carregar pipelines', 500);
        }
    }

    public function apiShow(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        try {
            $pipeline = TenantAwareDatabase::fetch(
                'SELECT * FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$pipeline) {
                $response->jsonError('Pipeline nao encontrado', 404);

                return;
            }

            $pipeline['stages'] = TenantAwareDatabase::fetchAll(
                'SELECT * FROM pipeline_stages 
                 WHERE pipeline_id = :pipeline_id AND tenant_id = :tenant_id
                 ORDER BY position',
                TenantAwareDatabase::mergeTenantParams([':pipeline_id' => $id])
            );

            $response->jsonSuccess(['pipeline' => $pipeline]);
        } catch (\Exception $e) {
            App::logError('Erro ao buscar pipeline', $e);
            $response->jsonError('Erro ao carregar pipeline', 500);
        }
    }

    public function apiCreate(Request $request, Response $response): void
    {
        try {
            $data = $request->validate([
                'name' => 'required|min:3',
                'description' => '',
            ]);
        } catch (\InvalidArgumentException $e) {
            $errors = json_decode($e->getMessage(), true);
            $response->jsonError('Dados invalidos', 422, $errors);

            return;
        }

        try {
            $pipelineId = TenantAwareDatabase::insert('pipelines', [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => 1,
                'is_default' => 0,
            ]);

            $defaultStages = [
                ['name' => 'Pendentes', 'slug' => 'pendentes', 'type' => 'initial', 'color' => '#6B7280'],
                ['name' => 'Aguardando Resposta', 'slug' => 'aguardando-resposta', 'type' => 'intermediate', 'color' => '#F59E0B'],
                ['name' => 'HOT', 'slug' => 'hot', 'type' => 'hot', 'color' => '#EF4444'],
                ['name' => 'WARM', 'slug' => 'warm', 'type' => 'warm', 'color' => '#F97316'],
                ['name' => 'COLD', 'slug' => 'cold', 'type' => 'cold', 'color' => '#3B82F6'],
                ['name' => 'Venda Fechada', 'slug' => 'venda-fechada', 'type' => 'won', 'color' => '#10B981'],
                ['name' => 'Perdido / Win-back', 'slug' => 'perdido-winback', 'type' => 'lost', 'color' => '#8B5CF6'],
            ];

            $tid = TenantContext::getEffectiveTenantId();
            $stmt = TenantAwareDatabase::getInstance()->prepare(
                'INSERT INTO pipeline_stages 
                (tenant_id, pipeline_id, name, slug, stage_type, color_token, position, is_default, is_final, win_probability) 
                VALUES (:tenant_id, :pipeline_id, :name, :slug, :type, :color, :position, :is_default, :is_final, :probability)'
            );

            foreach ($defaultStages as $index => $stage) {
                $stmt->execute([
                    ':tenant_id' => $tid,
                    ':pipeline_id' => $pipelineId,
                    ':name' => $stage['name'],
                    ':slug' => $stage['slug'],
                    ':type' => $stage['type'],
                    ':color' => $stage['color'],
                    ':position' => $index + 1,
                    ':is_default' => $index === 0 ? 1 : 0,
                    ':is_final' => in_array($stage['type'], ['won', 'lost'], true) ? 1 : 0,
                    ':probability' => match ($stage['type']) {
                        'initial' => 0,
                        'intermediate' => 20,
                        'hot' => 60,
                        'warm' => 40,
                        'cold' => 10,
                        'won' => 100,
                        'lost' => 0,
                        default => 0,
                    },
                ]);
            }

            $pipeline = TenantAwareDatabase::fetch(
                'SELECT * FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $pipelineId])
            );

            App::log("Pipeline criado: {$pipeline['name']}");

            $response->jsonSuccess(['pipeline' => $pipeline], 'Pipeline criado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao criar pipeline', $e);
            $response->jsonError('Erro ao criar pipeline', 500);
        }
    }

    public function apiUpdate(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        $data = $request->getJsonInput();

        if (empty($data)) {
            $response->jsonError('Dados nao fornecidos', 400);

            return;
        }

        $pipeline = TenantAwareDatabase::fetch(
            'SELECT id FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$pipeline) {
            $response->jsonError('Pipeline nao encontrado', 404);

            return;
        }

        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }

        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);

            return;
        }

        try {
            TenantAwareDatabase::update('pipelines', $updateData, 'id = :id', [':id' => $id]);

            $pipeline = TenantAwareDatabase::fetch(
                'SELECT * FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            App::log("Pipeline atualizado: {$pipeline['name']}");

            $response->jsonSuccess(['pipeline' => $pipeline], 'Pipeline atualizado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao atualizar pipeline', $e);
            $response->jsonError('Erro ao atualizar pipeline', 500);
        }
    }

    public function apiDelete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        $pipeline = TenantAwareDatabase::fetch(
            'SELECT id, name, is_default FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if (!$pipeline) {
            $response->jsonError('Pipeline nao encontrado', 404);

            return;
        }

        if ($pipeline['is_default']) {
            $response->jsonError('Nao e possivel excluir o pipeline padrao', 422);

            return;
        }

        $hasLeads = TenantAwareDatabase::fetch(
            'SELECT COUNT(*) as count FROM leads WHERE pipeline_id = :id AND deleted_at IS NULL AND tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams([':id' => $id])
        );

        if ((int) ($hasLeads['count'] ?? 0) > 0) {
            $response->jsonError('Nao e possivel excluir um pipeline que possui leads', 422);

            return;
        }

        try {
            TenantAwareDatabase::query(
                'DELETE FROM pipeline_stages WHERE pipeline_id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );
            TenantAwareDatabase::query(
                'DELETE FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            App::log("Pipeline excluido: {$pipeline['name']}");

            $response->jsonSuccess([], 'Pipeline excluido com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao excluir pipeline', $e);
            $response->jsonError('Erro ao excluir pipeline', 500);
        }
    }

    public function apiUpdateStages(Request $request, Response $response): void
    {
        $pipelineId = $request->getParam('id');

        if (!$pipelineId) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        $data = $request->getJsonInput();
        $stages = $data['stages'] ?? [];

        if (empty($stages)) {
            $response->jsonError('Etapas nao fornecidas', 400);

            return;
        }

        $db = TenantAwareDatabase::getInstance();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                'UPDATE pipeline_stages 
                 SET position = :position, name = :name, color_token = :color, 
                     stage_type = :type, win_probability = :probability
                 WHERE id = :id AND pipeline_id = :pipeline_id AND tenant_id = :tenant_id'
            );

            $paramsBase = TenantAwareDatabase::mergeTenantParams([':pipeline_id' => $pipelineId]);

            foreach ($stages as $index => $stage) {
                $stmt->execute(array_merge($paramsBase, [
                    ':id' => $stage['id'],
                    ':position' => $index + 1,
                    ':name' => $stage['name'],
                    ':color' => $stage['color_token'] ?? $stage['color'] ?? '#6B7280',
                    ':type' => $stage['stage_type'] ?? $stage['type'] ?? 'intermediate',
                    ':probability' => $stage['win_probability'] ?? 0,
                ]));
            }

            $db->commit();

            App::log("Etapas do pipeline {$pipelineId} atualizadas");

            $response->jsonSuccess([], 'Etapas atualizadas com sucesso');
        } catch (\Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            App::logError('Erro ao atualizar etapas', $e);
            $response->jsonError('Erro ao atualizar etapas', 500);
        }
    }
}
