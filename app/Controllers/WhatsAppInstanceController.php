<?php

namespace App\Controllers;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\TenantAwareDatabase;
use App\Services\WhatsApp\EvolutionApiService;

/**
 * Configuracao WhatsApp por tenant.
 * As credenciais da API (URL + API Key) vem das configuracoes globais do superadmin.
 * Cada tenant configura apenas o nome da instancia e conecta seu numero.
 */
class WhatsAppInstanceController
{
    /**
     * Busca configuracoes globais da Evolution API (definidas pelo superadmin).
     * Usa .env como fallback se nao houver config no banco.
     */
    private function getGlobalConfig(): array
    {
        // Busca do tenant id=0 (configuracoes do sistema)
        $row = Database::fetch("SELECT settings_json FROM tenants WHERE id = 0 LIMIT 1");
        $settings = [];
        if ($row && !empty($row['settings_json'])) {
            $decoded = json_decode($row['settings_json'], true);
            $settings = is_array($decoded) ? $decoded : [];
        }

        // Usar .env como fallback se nao houver config no banco
        $apiUrl = $settings['evolution_default_api_url'] ?? \App\Core\Env::get('EVOLUTION_API_URL', '');
        $apiKey = $settings['evolution_global_api_key'] ?? \App\Core\Env::get('EVOLUTION_API_KEY', '');
        
        // Enabled: se tiver URL e Key, considera habilitado (a menos que esteja explicitamente desabilitado no banco)
        $enabledFromDb = $settings['evolution_enabled'] ?? null;
        if ($enabledFromDb !== null) {
            $enabled = $enabledFromDb !== false;
        } else {
            // Se nao tem config no banco, habilita se tiver credenciais no .env
            $enabled = !empty($apiUrl) && !empty($apiKey);
        }

        return [
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'enabled' => $enabled,
        ];
    }

    public function page(Request $request, Response $response): void
    {
        $global = $this->getGlobalConfig();

        $response->view('settings.whatsapp', [
            'title' => 'WhatsApp',
            'pageTitle' => 'Integracao WhatsApp',
            'globalEnabled' => $global['enabled'],
            'globalConfigured' => !empty($global['api_url']) && !empty($global['api_key']),
        ]);
    }

    public function apiList(Request $request, Response $response): void
    {
        try {
            $tid = \App\Core\TenantContext::getEffectiveTenantId();
            App::log("[WhatsApp] apiList - Tenant ID: {$tid}");

            $rows = TenantAwareDatabase::fetchAll(
                'SELECT id, name, instance_name, status, phone_number, phone_connected, webhook_token, created_at, updated_at FROM whatsapp_instances WHERE tenant_id = :tenant_id ORDER BY id ASC',
                TenantAwareDatabase::mergeTenantParams()
            );
            App::log("[WhatsApp] apiList - Instancias encontradas: " . count($rows));

            // Busca info do tenant para preview do nome da instancia
            $tenant = Database::fetch('SELECT slug FROM tenants WHERE id = :id', [':id' => $tid]);
            App::log("[WhatsApp] apiList - Tenant slug: " . ($tenant['slug'] ?? 'null'));

            // Adiciona info se a integracao global esta configurada
            $global = $this->getGlobalConfig();
            App::log("[WhatsApp] apiList - Global config: enabled=" . ($global['enabled'] ? 'true' : 'false') . ", url=" . ($global['api_url'] ? 'set' : 'empty') . ", key=" . ($global['api_key'] ? 'set' : 'empty'));

            $response->jsonSuccess([
                'instances' => $rows,
                'global' => [
                    'enabled' => $global['enabled'],
                    'configured' => !empty($global['api_url']) && !empty($global['api_key']),
                ],
                'tenant_slug' => $tenant['slug'] ?? ('tenant' . $tid),
            ]);
        } catch (\Throwable $e) {
            App::logError('[WhatsApp] apiList erro: ' . $e->getMessage(), $e);
            $response->jsonError('Erro ao listar', 500);
        }
    }

    /**
     * Cria instancia automaticamente baseada no slug do tenant.
     * Nome da instancia: {tenant-slug}-yve (ex: yve-beauty-yve)
     */
    public function apiCreate(Request $request, Response $response): void
    {
        App::log('[WhatsApp] apiCreate - Iniciando criacao de instancia');
        
        // Busca configuracoes globais
        $global = $this->getGlobalConfig();
        App::log('[WhatsApp] apiCreate - Global config: enabled=' . ($global['enabled'] ? 'true' : 'false') . ', url=' . ($global['api_url'] ?: 'empty') . ', key=' . ($global['api_key'] ? 'set' : 'empty'));

        if (!$global['enabled']) {
            App::log('[WhatsApp] apiCreate - ERRO: Integracao desabilitada');
            $response->jsonError('Integracao WhatsApp desabilitada pelo administrador', 403);
            return;
        }

        if (empty($global['api_url']) || empty($global['api_key'])) {
            App::log('[WhatsApp] apiCreate - ERRO: URL ou API Key vazios');
            $response->jsonError('Integracao WhatsApp nao configurada. Contate o administrador.', 503);
            return;
        }

        // Busca info do tenant atual para gerar nome unico
        $tid = \App\Core\TenantContext::getEffectiveTenantId();
        App::log("[WhatsApp] apiCreate - Tenant ID: {$tid}");
        
        $tenant = Database::fetch('SELECT name, slug FROM tenants WHERE id = :id', [':id' => $tid]);
        if (!$tenant) {
            App::log('[WhatsApp] apiCreate - ERRO: Tenant nao encontrado');
            $response->jsonError('Tenant nao encontrado', 404);
            return;
        }
        App::log("[WhatsApp] apiCreate - Tenant: name={$tenant['name']}, slug={$tenant['slug']}");

        // Gera nome da instancia: {slug}-yve ou tenant{id}-yve se slug vazio
        $baseName = !empty($tenant['slug']) ? $tenant['slug'] : 'tenant' . $tid;
        $instanceName = $baseName . '-yve';
        App::log("[WhatsApp] apiCreate - Nome da instancia: {$instanceName}");

        // Verifica se ja existe instancia para este tenant
        $existing = TenantAwareDatabase::fetch(
            'SELECT id FROM whatsapp_instances WHERE tenant_id = :tenant_id',
            TenantAwareDatabase::mergeTenantParams()
        );
        if ($existing) {
            App::log("[WhatsApp] apiCreate - ERRO: Ja existe instancia para tenant {$tid}");
            $response->jsonError('Ja existe uma instancia configurada para este tenant.', 422);
            return;
        }

        try {
            $token = bin2hex(random_bytes(16));
            App::log("[WhatsApp] apiCreate - Token gerado: {$token}");

            // Criar instancia no banco local primeiro
            App::log('[WhatsApp] apiCreate - Criando instancia no banco local...');
            $id = TenantAwareDatabase::insert('whatsapp_instances', [
                'name' => $tenant['name'] ?? 'Principal',
                'instance_name' => $instanceName,
                'api_url' => $global['api_url'],
                'api_key' => $global['api_key'],
                'status' => 'pending',
                'phone_connected' => false,
                'webhook_token' => $token,
            ]);
            App::log("[WhatsApp] apiCreate - Instancia criada no banco local, ID: {$id}");

            // Construir URL do webhook (mas nao enviar na criacao inicial - causa erro 400)
            $webhookUrl = '';
            if (!empty($_SERVER['HTTP_HOST'])) {
                $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
                $webhookUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/webhook/evolution/' . $token;
            }
            App::log("[WhatsApp] apiCreate - Webhook URL (para uso futuro): {$webhookUrl}");

            // Criar instancia na Evolution API (SEM webhook - evita erro "Invalid url property")
            App::log('[WhatsApp] apiCreate - Chamando Evolution API para criar instancia...');
            $evo = new EvolutionApiService();
            $createRes = $evo->createInstance($global['api_url'], $global['api_key'], $instanceName, null);
            App::log('[WhatsApp] apiCreate - Resposta da Evolution: ' . json_encode($createRes));

            if (!$createRes['ok']) {
                // Se falhou na Evolution, remover do banco local (rollback)
                TenantAwareDatabase::query(
                    'DELETE FROM whatsapp_instances WHERE id = :id AND tenant_id = :tid',
                    [':id' => $id, ':tid' => $tid]
                );
                
                // Extrair mensagem de erro da Evolution (pode estar em diferentes formatos)
                $errorMsg = 'Erro desconhecido';
                $errorDetail = '';
                
                if (is_array($createRes['body'])) {
                    if (isset($createRes['body']['response']['message'])) {
                        // Erro de validacao: {response: {message: [[...]]}}
                        $messages = $createRes['body']['response']['message'];
                        if (is_array($messages)) {
                            $errorDetail = json_encode($messages);
                        } else {
                            $errorDetail = $messages;
                        }
                    }
                    $errorMsg = $createRes['body']['message'] 
                        ?? $createRes['body']['error'] 
                        ?? $createRes['body']['status']
                        ?? json_encode($createRes['body']);
                } elseif (is_string($createRes['body'])) {
                    $errorMsg = $createRes['body'];
                }
                
                $fullError = "HTTP {$createRes['http']} - {$errorMsg}";
                if ($errorDetail) {
                    $fullError .= " | Detalhe: {$errorDetail}";
                }
                
                App::logError("[WhatsApp] WA create on Evolution failed: {$fullError}, raw: " . ($createRes['raw'] ?? 'empty'));
                $response->jsonError('Erro ao criar instancia na Evolution API: ' . $fullError, 500);
                return;
            }

            $row = TenantAwareDatabase::fetch(
                'SELECT * FROM whatsapp_instances WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            // Configurar webhook na Evolution API
            $webhookConfigured = false;
            $webhookError = null;
            if (!empty($webhookUrl)) {
                App::log("[WhatsApp] apiCreate - Configurando webhook: {$webhookUrl}");
                $webhookRes = $evo->setWebhook($global['api_url'], $global['api_key'], $instanceName, $webhookUrl);
                App::log('[WhatsApp] apiCreate - Resposta setWebhook: ' . json_encode($webhookRes));

                if ($webhookRes['ok']) {
                    $webhookConfigured = true;
                    App::log("[WhatsApp] apiCreate - Webhook configurado com sucesso para instancia {$instanceName}");
                } else {
                    $webhookError = "HTTP {$webhookRes['http']} - " . (is_array($webhookRes['body']) ? json_encode($webhookRes['body']) : substr($webhookRes['raw'] ?? '', 0, 200));
                    App::logError("[WhatsApp] apiCreate - Falha ao configurar webhook: {$webhookError}");
                }
            }

            App::log("Instancia WhatsApp '{$instanceName}' criada para tenant {$tid}");

            $message = "Instancia '{$instanceName}' criada automaticamente.";
            if ($webhookConfigured) {
                $message .= " Webhook configurado com sucesso.";
            } elseif ($webhookError) {
                $message .= " A instancia foi criada, mas o webhook nao foi configurado automaticamente. Clique em 'Configurar Webhook' apos conectar.";
            }
            $message .= " Clique em 'Conectar' para ativar.";

            $response->jsonSuccess([
                'instance' => $row,
                'instance_name' => $instanceName,
                'webhook_configured' => $webhookConfigured,
                'webhook_error' => $webhookError,
                'message' => $message
            ], 'Instancia criada');
        } catch (\Throwable $e) {
            App::logError('WA create', $e);
            $response->jsonError('Erro ao criar instancia: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtem QR code para conexao.
     */
    public function apiQrCode(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            $row = TenantAwareDatabase::fetch(
                'SELECT api_url, api_key, instance_name, phone_number FROM whatsapp_instances WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$row) {
                $response->jsonError('Instancia nao encontrada', 404);
                return;
            }

            $evo = new EvolutionApiService();
            $res = $evo->getQrCode($row['api_url'], $row['api_key'], $row['instance_name']);

            // Atualiza status baseado na resposta
            $status = 'disconnected';
            $qrCode = null;
            $pairingCode = null;

            if ($res['ok'] && !empty($res['body'])) {
                // API v2 retorna QR em 'base64' (imagem pronta) ou 'code' (texto)
                // Preferir base64 para exibir imagem diretamente
                $qrCode = $res['body']['base64'] ?? $res['body']['code'] ?? $res['body']['qrcode'] ?? null;
                $pairingCode = $res['body']['pairingCode'] ?? null;
                $status = $qrCode ? 'awaiting_qr' : 'checking';
            }

            $response->jsonSuccess([
                'qr_code' => $qrCode,
                'pairing_code' => $pairingCode,
                'status' => $status,
                'phone_locked' => $row['phone_number'], // Numero ja conectado anteriormente (para seguranca)
            ]);
        } catch (\Throwable $e) {
            App::logError('WA qr code', $e);
            $response->jsonError('Erro ao obter QR code', 500);
        }
    }

    /**
     * Verifica status da conexao e atualiza info da instancia.
     */
    public function apiCheckStatus(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        App::log("[WhatsApp] apiCheckStatus - ID recebido: {$id}");
        
        if ($id <= 0) {
            App::log("[WhatsApp] apiCheckStatus - ID invalido");
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            App::log("[WhatsApp] apiCheckStatus - Buscando instancia no banco...");
            $row = TenantAwareDatabase::fetch(
                'SELECT api_url, api_key, instance_name, phone_number FROM whatsapp_instances WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$row) {
                $response->jsonError('Instancia nao encontrada', 404);
                return;
            }

            $evo = new EvolutionApiService();

            // Busca estado da conexao
            $stateRes = $evo->getConnectionState($row['api_url'], $row['api_key'], $row['instance_name']);
            App::log("[WhatsApp] apiCheckStatus - Resposta connectionState: " . json_encode($stateRes));
            
            // Evolution API retorna state em $body['instance']['state']
            $state = $stateRes['body']['instance']['state'] ?? $stateRes['body']['state'] ?? 'unknown';
            $isConnected = in_array(strtolower($state), ['open', 'connected']);
            App::log("[WhatsApp] apiCheckStatus - State: {$state}, IsConnected: " . ($isConnected ? 'true' : 'false'));

            $phoneNumber = $row['phone_number'];
            $phoneFormatted = '';

            // Se conectado, busca informacoes da instancia para obter numero
            if ($isConnected) {
                $infoRes = $evo->getInstanceInfo($row['api_url'], $row['api_key'], $row['instance_name']);
                App::log("[WhatsApp] apiCheckStatus - Resposta getInstanceInfo: " . json_encode($infoRes));
                if ($infoRes['ok'] && !empty($infoRes['body'])) {
                    // fetchInstances retorna array, pegamos primeira instancia
                    $instanceInfo = is_array($infoRes['body']) ? $infoRes['body'][0] : ($infoRes['body']['instance'] ?? null);
                    if ($instanceInfo) {
                        // Numero esta em ownerJid (formato: 554191788844@s.whatsapp.net)
                        $newPhone = $instanceInfo['ownerJid'] ?? null;
                        if ($newPhone) {
                            // Remove o @s.whatsapp.net
                            $newPhone = str_replace('@s.whatsapp.net', '', $newPhone);
                            if ($newPhone !== $phoneNumber) {
                                // Numero mudou - atualiza
                                $phoneNumber = $newPhone;
                                TenantAwareDatabase::update(
                                    'whatsapp_instances',
                                    ['phone_number' => $phoneNumber],
                                    'id = :id',
                                    [':id' => $id]
                                );
                            }
                        }
                    }
                }

                // Formata numero
                if ($phoneNumber) {
                    $phoneFormatted = $this->formatPhone($phoneNumber);
                }
            }

            // Atualiza status no banco
            $newStatus = $isConnected ? 'connected' : ($state === 'close' ? 'disconnected' : 'pending');
            App::log("[WhatsApp] apiCheckStatus - Novo status: {$newStatus}, atualizando banco...");
            
            TenantAwareDatabase::update(
                'whatsapp_instances',
                [
                    'status' => $newStatus,
                    'phone_connected' => $isConnected,
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'id = :id',
                [':id' => $id]
            );

            App::log("[WhatsApp] apiCheckStatus - Retornando: status={$newStatus}, state={$state}, connected=" . ($isConnected ? 'true' : 'false'));
            $response->jsonSuccess([
                'status' => $newStatus,
                'state' => $state,
                'connected' => $isConnected,
                'phone_number' => $phoneNumber,
                'phone_formatted' => $phoneFormatted,
                'phone_locked' => !empty($row['phone_number']), // Se ja teve numero, esta "travado"
            ]);
        } catch (\Throwable $e) {
            App::logError('WA check status', $e);
            $response->jsonError('Erro ao verificar status', 500);
        }
    }

    public function apiConnection(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            $row = TenantAwareDatabase::fetch(
                'SELECT api_url, api_key, instance_name FROM whatsapp_instances WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$row) {
                $response->jsonError('Nao encontrado', 404);
                return;
            }

            $evo = new EvolutionApiService();
            $res = $evo->getConnectionState($row['api_url'], $row['api_key'], $row['instance_name']);
            $response->jsonSuccess(['evolution' => $res]);
        } catch (\Throwable $e) {
            App::logError('WA connection', $e);
            $response->jsonError('Erro', 500);
        }
    }

    /**
     * Desconecta a instancia (logout).
     */
    public function apiDisconnect(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        if ($id <= 0) {
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            $row = TenantAwareDatabase::fetch(
                'SELECT api_url, api_key, instance_name FROM whatsapp_instances WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$row) {
                $response->jsonError('Instancia nao encontrada', 404);
                return;
            }

            $evo = new EvolutionApiService();
            $res = $evo->logout($row['api_url'], $row['api_key'], $row['instance_name']);

            if ($res['ok']) {
                // Atualiza status
                TenantAwareDatabase::update(
                    'whatsapp_instances',
                    [
                        'status' => 'disconnected',
                        'phone_connected' => false,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ],
                    'id = :id',
                    [':id' => $id]
                );
                $response->jsonSuccess([], 'Desconectado com sucesso');
            } else {
                $response->jsonError('Erro ao desconectar: ' . ($res['body']['message'] ?? 'Erro desconhecido'), 500);
            }
        } catch (\Throwable $e) {
            App::logError('WA disconnect', $e);
            $response->jsonError('Erro ao desconectar', 500);
        }
    }

    /**
     * Configura webhook para uma instancia existente.
     */
    public function apiConfigureWebhook(Request $request, Response $response): void
    {
        $id = (int) ($request->getParam('id') ?? 0);
        App::log("[WhatsApp] apiConfigureWebhook - ID recebido: {$id}");
        
        if ($id <= 0) {
            App::log("[WhatsApp] apiConfigureWebhook - ID invalido");
            $response->jsonError('ID invalido', 400);
            return;
        }

        try {
            App::log("[WhatsApp] apiConfigureWebhook - Buscando instancia no banco...");
            $row = TenantAwareDatabase::fetch(
                'SELECT api_url, api_key, instance_name, webhook_token FROM whatsapp_instances WHERE id = :id AND tenant_id = :tenant_id',
                TenantAwareDatabase::mergeTenantParams([':id' => $id])
            );

            if (!$row) {
                App::log("[WhatsApp] apiConfigureWebhook - Instancia nao encontrada");
                $response->jsonError('Instancia nao encontrada', 404);
                return;
            }
            App::log("[WhatsApp] apiConfigureWebhook - Instancia encontrada: {$row['instance_name']}");

            // Construir URL do webhook
            $webhookUrl = '';
            App::log("[WhatsApp] apiConfigureWebhook - HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'vazio'));
            if (!empty($_SERVER['HTTP_HOST'])) {
                $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
                $webhookUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/webhook/evolution/' . $row['webhook_token'];
            }
            App::log("[WhatsApp] apiConfigureWebhook - URL construida: {$webhookUrl}");

            if (empty($webhookUrl)) {
                $response->jsonError('Nao foi possivel construir URL do webhook', 500);
                return;
            }

            App::log("[WhatsApp] apiConfigureWebhook - Chamando Evolution API para configurar webhook...");
            $evo = new EvolutionApiService();
            $res = $evo->setWebhook($row['api_url'], $row['api_key'], $row['instance_name'], $webhookUrl);
            App::log("[WhatsApp] apiConfigureWebhook - Resposta Evolution: " . json_encode($res));

            if ($res['ok']) {
                App::log("[WhatsApp] Webhook configurado para instancia {$row['instance_name']}: {$webhookUrl}");
                $response->jsonSuccess([], 'Webhook configurado com sucesso');
            } else {
                $errorMsg = is_array($res['body']) ? json_encode($res['body']) : ($res['raw'] ?? 'Erro desconhecido');
                App::logError("[WhatsApp] Falha ao configurar webhook: {$errorMsg}");
                $response->jsonError('Falha ao configurar webhook: ' . $errorMsg, 500);
            }
        } catch (\Throwable $e) {
            App::logError('WA configure webhook: ' . $e->getMessage(), $e);
            $response->jsonError('Erro ao configurar webhook: ' . $e->getMessage(), 500);
        }
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 13 && str_starts_with($phone, '55')) {
            // Brasil: 55 + DDD + numero
            return '+' . substr($phone, 0, 2) . ' (' . substr($phone, 2, 2) . ') ' . substr($phone, 4, 5) . '-' . substr($phone, 9);
        }
        return '+' . $phone;
    }
}
