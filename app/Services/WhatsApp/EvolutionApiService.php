<?php

namespace App\Services\WhatsApp;

use App\Core\App;

/**
 * Cliente HTTP para Evolution API.
 */
class EvolutionApiService
{
    /**
     * Envia mensagem de texto.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function sendText(string $baseUrl, string $apiKey, string $instanceName, string $phoneDigits, string $text): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/message/sendText/' . rawurlencode($instanceName);

        $payload = json_encode([
            'number' => $phoneDigits,
            'text' => $text,
        ], JSON_UNESCAPED_UNICODE);

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Obtem estado da conexao.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function getConnectionState(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/connectionState/' . rawurlencode($instanceName);

        return $this->request('GET', $url, $apiKey, null);
    }

    /**
     * Obtem QR code para conexao.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function getQrCode(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/connect/' . rawurlencode($instanceName);

        return $this->request('GET', $url, $apiKey, null);
    }

    /**
     * Obtem informacoes da instancia (numero conectado, etc).
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function getInstanceInfo(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/fetchInstances?instanceName=' . rawurlencode($instanceName);

        return $this->request('GET', $url, $apiKey, null);
    }

    /**
     * Desconecta a instancia.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function logout(string $baseUrl, string $apiKey, string $instanceName): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/logout/' . rawurlencode($instanceName);

        return $this->request('DELETE', $url, $apiKey, null);
    }

    /**
     * Cria uma nova instancia na Evolution API.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function createInstance(string $baseUrl, string $apiKey, string $instanceName, ?string $webhookUrl = null): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/instance/create';

        // Payload base conforme documentacao Evolution API v2
        // Teste 5 comprovou que 'integration' é obrigatório
        // Teste 1 falhou com webhook - só enviamos webhook se tiver URL válida
        $data = [
            'instanceName' => $instanceName,
            'integration' => 'WHATSAPP-BAILEYS',
            'qrcode' => true,
        ];

        // Só adiciona webhook se tiver URL válida (evita "Invalid url property")
        if (!empty($webhookUrl) && filter_var($webhookUrl, FILTER_VALIDATE_URL)) {
            $data['webhook'] = $webhookUrl;
            $data['webhook_by_events'] = true;
            $data['events'] = ['MESSAGES_UPSERT', 'CONNECTION_UPDATE'];
        }

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] createInstance payload: " . $payload);
        }

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * Configura webhook para uma instancia.
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    public function setWebhook(string $baseUrl, string $apiKey, string $instanceName, string $webhookUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . '/webhook/set/' . rawurlencode($instanceName);

        // Payload conforme documentacao Evolution API v2
        // https://docs.evoapicloud.com/instances/events/webhook
        $data = [
            'webhook' => [
                'enabled' => true,
                'url' => $webhookUrl,
                'byEvents' => false,
                'base64' => false,
                'events' => [
                    'MESSAGES_UPSERT',
                    'MESSAGES_UPDATE',
                    'MESSAGES_DELETE',
                    'CONNECTION_UPDATE',
                    'QRCODE_UPDATED',
                    'PRESENCE_UPDATE',
                ],
            ],
        ];

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] setWebhook payload: " . $payload);
        }

        return $this->request('POST', $url, $apiKey, $payload);
    }

    /**
     * @return array{ok:bool,http:int,body:mixed,raw:string}
     */
    private function request(string $method, string $url, string $apiKey, ?string $jsonBody): array
    {
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] Request: {$method} {$url}");
            App::log("[EvolutionAPI] Payload: " . ($jsonBody ?: 'null'));
        }
        
        $ch = curl_init($url);
        if ($ch === false) {
            if (class_exists('App\Core\App')) {
                App::log('[EvolutionAPI] ERRO: curl_init falhou');
            }
            return ['ok' => false, 'http' => 0, 'body' => null, 'raw' => 'curl_init failed'];
        }

        $headers = [
            'apikey: ' . $apiKey,
            'Accept: application/json',
        ];
        if ($jsonBody !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($jsonBody !== null && $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        $raw = (string) curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] Response HTTP: {$http}");
            App::log("[EvolutionAPI] Response raw: " . substr($raw, 0, 500));
        }

        if ($err !== '') {
            if (class_exists('App\Core\App')) {
                App::logError("[EvolutionAPI] Curl error: {$err}");
            }
            return ['ok' => false, 'http' => $http, 'body' => null, 'raw' => $raw];
        }

        $body = json_decode($raw, true);
        $ok = $http >= 200 && $http < 300;
        if (class_exists('App\Core\App')) {
            App::log("[EvolutionAPI] Result: ok=" . ($ok ? 'true' : 'false'));
        }

        return ['ok' => $ok, 'http' => $http, 'body' => $body, 'raw' => $raw];
    }
}
