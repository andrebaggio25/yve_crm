<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\WhatsApp\WebhookProcessor;

class WebhookController
{
    public function evolution(Request $request, Response $response): void
    {
        $token = (string) ($request->getParam('token') ?? '');
        if ($token === '') {
            $response->jsonError('Token invalido', 404);

            return;
        }

        $inst = Database::fetch(
            'SELECT id, tenant_id FROM whatsapp_instances WHERE webhook_token = :t LIMIT 1',
            [':t' => $token]
        );

        if (!$inst) {
            $response->jsonError('Instancia nao encontrada', 404);

            return;
        }

        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = [];
        }

        try {
            (new WebhookProcessor())->handle($payload, (int) $inst['tenant_id'], (int) $inst['id']);
            $response->jsonSuccess([], 'OK');
        } catch (\Throwable $e) {
            // Logar erro para debug mas responder 200 para nao fazer a Evolution reenviar
            \App\Core\App::logError('Webhook processing failed: ' . $e->getMessage(), $e);
            $response->jsonSuccess(['processed' => false, 'error' => 'Processing failed but acknowledged'], 'OK');
        }
    }
}
