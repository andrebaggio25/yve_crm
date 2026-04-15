<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantAwareDatabase;

class TemplateController
{
    public function index(Request $request, Response $response): void
    {
        $tp = TenantAwareDatabase::mergeTenantParams();

        try {
            $templates = TenantAwareDatabase::fetchAll(
                "SELECT mt.*, 
                        p.name as pipeline_name, 
                        ps.name as stage_name 
                 FROM message_templates mt 
                 LEFT JOIN pipelines p ON mt.pipeline_id = p.id AND p.tenant_id = :tenant_id
                 LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id AND ps.tenant_id = :tenant_id
                 WHERE mt.tenant_id = :tenant_id
                 ORDER BY mt.pipeline_id IS NULL, mt.position, mt.name",
                $tp
            );
        } catch (\Exception $e) {
            $templates = TenantAwareDatabase::fetchAll(
                'SELECT * FROM message_templates WHERE tenant_id = :tenant_id ORDER BY stage_type, name',
                $tp
            );
            foreach ($templates as &$t) {
                $t['pipeline_name'] = null;
                $t['stage_name'] = null;
                $t['position'] = $t['position'] ?? 1;
            }
            unset($t);
        }

        $response->view('templates.index', [
            'title' => 'Templates',
            'pageTitle' => 'Templates de Mensagem',
            'templates' => $templates,
        ]);
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $pipelineId = $request->get('pipeline_id');
            $stageId = $request->get('stage_id');

            // Query simplificada sem JOINs para evitar erros de coluna inexistente
            $sql = "SELECT * FROM message_templates WHERE tenant_id = :tenant_id";
            $params = [];

            if ($pipelineId) {
                $sql .= ' AND (pipeline_id = :pipeline_id OR pipeline_id IS NULL)';
                $params[':pipeline_id'] = $pipelineId;
            }

            if ($stageId) {
                $sql .= ' AND (stage_id = :stage_id OR stage_id IS NULL)';
                $params[':stage_id'] = $stageId;
            }

            $sql .= ' ORDER BY position, name';

            $templates = TenantAwareDatabase::fetchAll($sql, TenantAwareDatabase::mergeTenantParams($params));

            // Adicionar pipeline_name e stage_name manualmente
            foreach ($templates as &$template) {
                $template['pipeline_name'] = null;
                $template['stage_name'] = null;
                if ($template['pipeline_id']) {
                    $pipeline = TenantAwareDatabase::fetch(
                        'SELECT name FROM pipelines WHERE id = :id AND tenant_id = :tenant_id',
                        TenantAwareDatabase::mergeTenantParams([':id' => $template['pipeline_id']])
                    );
                    if ($pipeline) {
                        $template['pipeline_name'] = $pipeline['name'];
                    }
                }
                if ($template['stage_id']) {
                    $stage = TenantAwareDatabase::fetch(
                        'SELECT name FROM pipeline_stages WHERE id = :id AND tenant_id = :tenant_id',
                        TenantAwareDatabase::mergeTenantParams([':id' => $template['stage_id']])
                    );
                    if ($stage) {
                        $template['stage_name'] = $stage['name'];
                    }
                }
            }
            unset($template);

            $response->jsonSuccess(['templates' => $templates]);
        } catch (\Exception $e) {
            App::logError('Erro ao listar templates: ' . $e->getMessage(), $e);
            $response->jsonError('Erro ao carregar templates: ' . $e->getMessage(), 500);
        }
    }

    public function apiCreate(Request $request, Response $response): void
    {
        $data = $request->getJsonInput();

        if (empty($data['name']) || empty($data['content'])) {
            $response->jsonError('Nome e conteudo sao obrigatorios', 422);

            return;
        }

        $slug = $this->slugify($data['name']);

        $pipelineId = !empty($data['pipeline_id']) ? (int) $data['pipeline_id'] : null;
        $stageId = !empty($data['stage_id']) ? (int) $data['stage_id'] : null;

        if ($stageId && !$pipelineId) {
            $response->jsonError('Pipeline e obrigatorio quando uma etapa e especificada', 422);

            return;
        }

        try {
            $id = TenantAwareDatabase::insert('message_templates', [
                'name' => $data['name'],
                'slug' => $slug,
                'channel' => $data['channel'] ?? 'whatsapp',
                'stage_type' => $data['stage_type'] ?? 'any',
                'pipeline_id' => $pipelineId,
                'stage_id' => $stageId,
                'position' => isset($data['position']) ? (int) $data['position'] : 1,
                'content' => $data['content'],
                'variables' => json_encode($data['variables'] ?? ['nome', 'produto']),
                'is_active' => ($data['is_active'] ?? true) ? 1 : 0,
            ]);

            $template = TenantAwareDatabase::fetch(
                "SELECT mt.*, p.name as pipeline_name, ps.name as stage_name 
                 FROM message_templates mt 
                 LEFT JOIN pipelines p ON mt.pipeline_id = p.id AND p.tenant_id = :tenant_id
                 LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id AND ps.tenant_id = :tenant_id
                 WHERE mt.id = :id AND mt.tenant_id = :tenant_id",
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            $response->jsonSuccess(['template' => $template], 'Template criado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao criar template', $e);
            $response->jsonError('Erro ao criar template', 500);
        }
    }

    public function apiUpdate(Request $request, Response $response): void
    {
        $id = $request->getParam('id');
        $data = $request->getJsonInput();

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        $updateData = [];
        $fields = ['name', 'content', 'channel', 'stage_type', 'pipeline_id', 'stage_id', 'position'];
        foreach ($fields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (isset($data['pipeline_id'])) {
            $updateData['pipeline_id'] = !empty($data['pipeline_id']) ? (int) $data['pipeline_id'] : null;
        }
        if (isset($data['stage_id'])) {
            $updateData['stage_id'] = !empty($data['stage_id']) ? (int) $data['stage_id'] : null;
        }
        if (isset($data['position'])) {
            $updateData['position'] = (int) $data['position'];
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'] ? 1 : 0;
        }

        if (!empty($updateData['stage_id']) && empty($updateData['pipeline_id']) && !isset($data['pipeline_id'])) {
            $response->jsonError('Pipeline e obrigatorio quando uma etapa e especificada', 422);

            return;
        }

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);

            return;
        }

        try {
            TenantAwareDatabase::update('message_templates', $updateData, 'id = :id', [':id' => $id]);

            $template = TenantAwareDatabase::fetch(
                "SELECT mt.*, p.name as pipeline_name, ps.name as stage_name 
                 FROM message_templates mt 
                 LEFT JOIN pipelines p ON mt.pipeline_id = p.id AND p.tenant_id = :tenant_id
                 LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id AND ps.tenant_id = :tenant_id
                 WHERE mt.id = :id AND mt.tenant_id = :tenant_id",
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            $response->jsonSuccess(['template' => $template], 'Template atualizado com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao atualizar template', $e);
            $response->jsonError('Erro ao atualizar template', 500);
        }
    }

    public function apiDelete(Request $request, Response $response): void
    {
        $id = $request->getParam('id');

        if (!$id) {
            $response->jsonError('ID nao fornecido', 400);

            return;
        }

        try {
            TenantAwareDatabase::delete('message_templates', 'id = :id', [':id' => $id]);

            $response->jsonSuccess([], 'Template excluido com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao excluir template', $e);
            $response->jsonError('Erro ao excluir template', 500);
        }
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        $text = strtolower($text);

        return $text ?: 'template';
    }
}
