<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\App;

class TagController
{
    public function apiList(Request $request, Response $response): void
    {
        try {
            $tags = Database::fetchAll(
                "SELECT t.*, COUNT(ti.lead_id) as leads_count
                 FROM lead_tags t
                 LEFT JOIN lead_tag_items ti ON t.id = ti.tag_id
                 GROUP BY t.id
                 ORDER BY t.name"
            );

            $response->jsonSuccess(['tags' => $tags]);
        } catch (\Exception $e) {
            App::logError('Erro ao listar tags', $e);
            $response->jsonError('Erro ao carregar tags', 500);
        }
    }

    public function apiCreate(Request $request, Response $response): void
    {
        $data = $request->getJsonInput();

        if (empty($data['name'])) {
            $response->jsonError('Nome da tag e obrigatorio', 422);
            return;
        }

        try {
            $id = Database::insert('lead_tags', [
                'name' => $data['name'],
                'color' => $data['color'] ?? '#6B7280'
            ]);

            $tag = Database::fetch("SELECT * FROM lead_tags WHERE id = :id", [':id' => $id]);

            $response->jsonSuccess(['tag' => $tag], 'Tag criada com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao criar tag', $e);
            $response->jsonError('Erro ao criar tag', 500);
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
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['color'])) $updateData['color'] = $data['color'];

        if (empty($updateData)) {
            $response->jsonError('Nenhum dado para atualizar', 400);
            return;
        }

        try {
            Database::update('lead_tags', $updateData, 'id = :id', [':id' => $id]);

            $tag = Database::fetch("SELECT * FROM lead_tags WHERE id = :id", [':id' => $id]);

            $response->jsonSuccess(['tag' => $tag], 'Tag atualizada com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao atualizar tag', $e);
            $response->jsonError('Erro ao atualizar tag', 500);
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
            Database::query("DELETE FROM lead_tag_items WHERE tag_id = :id", [':id' => $id]);
            Database::delete('lead_tags', 'id = :id', [':id' => $id]);

            $response->jsonSuccess([], 'Tag excluida com sucesso');
        } catch (\Exception $e) {
            App::logError('Erro ao excluir tag', $e);
            $response->jsonError('Erro ao excluir tag', 500);
        }
    }
}
