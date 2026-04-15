<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use App\Services\WhatsApp\ChatService;

class ChatController
{
    public function apiList(Request $request, Response $response): void
    {
        try {
            $filter = (string) ($request->get('filter') ?? 'all');
            App::log("[Chat] apiList - Filter: {$filter}");
            $svc = new ChatService();
            $conversations = $svc->listConversations($filter);
            App::log("[Chat] apiList - Conversas encontradas: " . count($conversations));
            $response->jsonSuccess(['conversations' => $conversations]);
        } catch (\Throwable $e) {
            App::logError('[Chat] apiList erro: ' . $e->getMessage(), $e);
            $response->jsonError('Erro ao listar conversas: ' . $e->getMessage(), 500);
        }
    }

    public function apiMessages(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);

            return;
        }
        try {
            $after = $request->get('after_id') ? (int) $request->get('after_id') : null;
            $svc = new ChatService();
            $svc->markRead($id);
            $response->jsonSuccess(['messages' => $svc->listMessages($id, $after)]);
        } catch (\Throwable $e) {
            App::logError('Chat messages', $e);
            $response->jsonError('Erro ao carregar mensagens', 500);
        }
    }

    public function apiSend(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        $body = $request->getJsonInput() ?? [];
        $text = trim((string) ($body['text'] ?? ''));
        if ($id <= 0 || $text === '') {
            $response->jsonError('Dados invalidos', 422);

            return;
        }
        try {
            $svc = new ChatService();
            $out = $svc->sendText($id, $text);
            if (!$out['ok']) {
                $response->jsonError($out['message'] ?? 'Erro ao enviar', 422);

                return;
            }
            $response->jsonSuccess($out, 'Enviado');
        } catch (\Throwable $e) {
            App::logError('Chat send', $e);
            $response->jsonError('Erro ao enviar', 500);
        }
    }

    public function apiByLead(Request $request, Response $response): void
    {
        $leadId = (int) ($request->getParam('lead_id') ?? 0);
        if ($leadId <= 0) {
            $response->jsonError('Lead invalido', 400);

            return;
        }
        try {
            $svc = new ChatService();
            $conv = $svc->findByLeadId($leadId);
            $response->jsonSuccess(['conversation' => $conv]);
        } catch (\Throwable $e) {
            App::logError('Chat by lead', $e);
            $response->jsonError('Erro', 500);
        }
    }
}
