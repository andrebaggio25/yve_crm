<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\App;

class TemplateController
{
    public function index(Request $request, Response $response): void
    {
        try {
            // Tenta fazer JOIN com as novas colunas (migration 013)
            $templates = Database::fetchAll(
                "SELECT mt.*, 
                        p.name as pipeline_name, 
                        ps.name as stage_name 
                 FROM message_templates mt 
                 LEFT JOIN pipelines p ON mt.pipeline_id = p.id 
                 LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id 
                 ORDER BY mt.pipeline_id IS NULL, mt.position, mt.name"
            );
        } catch (\Exception $e) {
            // Se falhar (migration 013 nao executada), faz query simples
            $templates = Database::fetchAll(
                "SELECT * FROM message_templates ORDER BY stage_type, name"
            );
            // Adiciona campos vazios para compatibilidade com a view
            foreach ($templates as &$t) {
                $t['pipeline_name'] = null;
                $t['stage_name'] = null;
                $t['position'] = 1;
            }
            unset($t);
        }

        $response->view('templates.index', [
            'title' => 'Templates',
            'pageTitle' => 'Templates de Mensagem',
            'templates' => $templates
        ]);
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $pipelineId = $request->get('pipeline_id');
            $stageId = $request->get('stage_id');
            
            $sql = "SELECT mt.*, 
                           p.name as pipeline_name, 
                           ps.name as stage_name 
                    FROM message_templates mt 
                    LEFT JOIN pipelines p ON mt.pipeline_id = p.id 
                    LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id 
                    WHERE 1=1";
            $params = [];
            
            // Filtro por pipeline especifico
            if ($pipelineId) {
                $sql .= " AND (mt.pipeline_id = :pipeline_id OR mt.pipeline_id IS NULL)";
                $params[':pipeline_id'] = $pipelineId;
            }
            
            // Filtro por etapa especifica
            if ($stageId) {
                $sql .= " AND (mt.stage_id = :stage_id OR mt.stage_id IS NULL)";
                $params[':stage_id'] = $stageId;
            }
            
            $sql .= " ORDER BY mt.pipeline_id IS NULL, mt.position, mt.name";
            
            $templates = Database::fetchAll($sql, $params);

            $response->jsonSuccess(['templates' => $templates]);
        } catch (\Exception $e) {
            App::logError('Erro ao listar templates', $e);
            $response->jsonError('Erro ao carregar templates', 500);
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

        // Converter pipeline_id e stage_id: se vazio ou 0, guardar como NULL
        $pipelineId = !empty($data['pipeline_id']) ? (int) $data['pipeline_id'] : null;
        $stageId = !empty($data['stage_id']) ? (int) $data['stage_id'] : null;
        
        // Se stage_id e informado, pipeline_id e obrigatorio
        if ($stageId && !$pipelineId) {
            $response->jsonError('Pipeline e obrigatorio quando uma etapa e especificada', 422);
            return;
        }

        try {
            $id = Database::insert('message_templates', [
                'name' => $data['name'],
                'slug' => $slug,
                'channel' => $data['channel'] ?? 'whatsapp',
                'stage_type' => $data['stage_type'] ?? 'any',
                'pipeline_id' => $pipelineId,
                'stage_id' => $stageId,
                'position' => isset($data['position']) ? (int) $data['position'] : 1,
                'content' => $data['content'],
                'variables' => json_encode($data['variables'] ?? ['nome', 'produto']),
                'is_active' => ($data['is_active'] ?? true) ? 1 : 0
            ]);

            $template = Database::fetch(
                "SELECT mt.*, p.name as pipeline_name, ps.name as stage_name 
                 FROM message_templates mt 
                 LEFT JOIN pipelines p ON mt.pipeline_id = p.id 
                 LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id 
                 WHERE mt.id = :id", 
                [':id' => $id]
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
        
        // Tratar campos especiais
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
        
        // Validacao: se stage_id informado, pipeline_id deve existir
        if (!empty($updateData['stage_id']) && empty($updateData['pipeline_id'])) {
            $response->jsonError('Pipeline e obrigatorio quando uma etapa e especificada', 422);
            return;
        }

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);
            return;
        }

        try {
            Database::update('message_templates', $updateData, 'id = :id', [':id' => $id]);

            $template = Database::fetch(
                "SELECT mt.*, p.name as pipeline_name, ps.name as stage_name 
                 FROM message_templates mt 
                 LEFT JOIN pipelines p ON mt.pipeline_id = p.id 
                 LEFT JOIN pipeline_stages ps ON mt.stage_id = ps.id 
                 WHERE mt.id = :id", 
                [':id' => $id]
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
            Database::delete('message_templates', 'id = :id', [':id' => $id]);

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
